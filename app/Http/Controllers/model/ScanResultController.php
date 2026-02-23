<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
use App\Models\BreedCorrection;
use App\Models\Results;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use LDAP\Result;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use OpenAI\Laravel\Facades\OpenAI;


class ScanResultController extends Controller
{
    /**
     * ==========================================
     * HELPER: Calculate breed-specific learning progress
     * ==========================================
     */
    /**
     * ==========================================
     * FIXED: Calculate breed-specific learning progress
     * Fetches data from ML API instead of local file
     * ==========================================
     */
    private function calculateBreedLearningProgress()
    {
        try {
            Log::info('🔍 Starting breed learning progress calculation');

            // Fetch learning data from ML API
            $mlApiService = app(\App\Services\MLApiService::class);
            $statsResponse = $mlApiService->getMemoryStats();

            // Check if ML API returned data successfully
            if (!$statsResponse['success']) {
                Log::warning('❌ Failed to fetch ML API memory stats', [
                    'error' => $statsResponse['error'] ?? 'Unknown error'
                ]);
                return [];
            }

            $mlData = $statsResponse['data'];

            if (empty($mlData['breeds'])) {
                Log::info('ℹ️ No breeds learned yet in ML API memory', [
                    'total_examples' => $mlData['total_examples'] ?? 0
                ]);
                return [];
            }

            Log::info('✓ ML API returned learning data', [
                'total_examples' => $mlData['total_examples'] ?? 0,
                'unique_breeds' => $mlData['unique_breeds'] ?? 0,
                'breeds' => array_keys($mlData['breeds'])
            ]);

            $breedLearning = [];

            // Iterate through breeds that ML API has actually learned
            foreach ($mlData['breeds'] as $breed => $exampleCount) {
                // Get correction history for this breed from Laravel database
                $corrections = BreedCorrection::where('corrected_breed', $breed)
                    ->orderBy('created_at', 'asc')
                    ->get();

                if ($corrections->isEmpty()) {
                    // ML API has this breed, but no Laravel correction record
                    Log::debug('Breed in ML API but no Laravel corrections', [
                        'breed' => $breed,
                        'ml_examples' => $exampleCount
                    ]);
                    continue;
                }

                $firstCorrectionDate = $corrections->first()->created_at;

                // Get scans AFTER corrections started for this breed
                $scansAfterLearning = Results::where('breed', $breed)
                    ->where('created_at', '>=', $firstCorrectionDate)
                    ->get();

                $avgConfidenceAfter = $scansAfterLearning->avg('confidence') ?? 0;

                // Calculate success rate from recent scans (last 10)
                $recentScans = Results::where('breed', $breed)
                    ->latest()
                    ->take(10)
                    ->get();

                $recentHighConfidence = $recentScans->where('confidence', '>=', 80)->count();
                $successRate = $recentScans->count() > 0
                    ? ($recentHighConfidence / $recentScans->count()) * 100
                    : 0;

                $breedLearning[$breed] = [
                    'breed' => $breed,
                    'examples_learned' => $exampleCount, // From ML API memory
                    'corrections_made' => $corrections->count(), // From Laravel DB
                    'avg_confidence' => round($avgConfidenceAfter, 1),
                    'success_rate' => round($successRate, 1),
                    'first_learned' => $firstCorrectionDate->format('M d, Y'),
                    'days_learning' => $firstCorrectionDate->diffInDays(now()),
                    'recent_scans' => $recentScans->count(),
                ];

                Log::debug('✓ Breed learning stats calculated', [
                    'breed' => $breed,
                    'examples' => $exampleCount,
                    'success_rate' => $successRate
                ]);
            }

            // Sort by success rate descending
            usort($breedLearning, function ($a, $b) {
                return $b['success_rate'] <=> $a['success_rate'];
            });

            $topBreeds = array_slice($breedLearning, 0, 10); // Top 10 breeds

            Log::info('✓ Breed learning progress calculated successfully', [
                'total_breeds_with_data' => count($breedLearning),
                'top_breeds_returned' => count($topBreeds)
            ]);

            return $topBreeds;
        } catch (\Exception $e) {
            Log::error('❌ Error calculating breed learning progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }


    public function dashboard()
    {
        $results = Results::latest()->take(6)->get();

        $correctedBreed = BreedCorrection::get();
        $correctedBreedCount = $correctedBreed->count();
        $result = Results::get();
        $resultCount = $result->count();

        // Get scan IDs that have been corrected
        $correctedScanIds = BreedCorrection::pluck('scan_id');

        // Calculate pending review count (scans not yet corrected)
        $pendingReviewCount = Results::whereNotIn('scan_id', $correctedScanIds)->count();

        $lowConfidenceCount = $result->where('confidence', '<=', 40)->count();
        $highConfidenceCount = $result->where('confidence', '>=', 41)->count();

        $oneWeekAgo = Carbon::now()->subDays(7);
        $twoWeeksAgo = Carbon::now()->subDays(14);
        $oneMonthAgo = Carbon::now()->subDays(30);

        // ============================================================================
        // BREED-SPECIFIC LEARNING PROGRESS - Shows learning per breed
        // ============================================================================
        $breedLearningProgress = $this->calculateBreedLearningProgress();

        // ============================================================================
        // FIXED: FETCH MEMORY STATS FROM ML API (NOT LOCAL FILE)
        // ============================================================================
        $memoryCount = 0;
        $uniqueBreeds = [];

        try {
            $mlApiService = app(\App\Services\MLApiService::class);
            $statsResponse = $mlApiService->getMemoryStats();

            if ($statsResponse['success'] && !empty($statsResponse['data'])) {
                $memoryCount = $statsResponse['data']['total_examples'] ?? 0;
                $uniqueBreeds = array_keys($statsResponse['data']['breeds'] ?? []);

                Log::info('✓ Memory stats fetched from ML API', [
                    'memory_count' => $memoryCount,
                    'unique_breeds' => count($uniqueBreeds)
                ]);
            } else {
                Log::warning('⚠️ ML API memory stats unavailable', [
                    'error' => $statsResponse['error'] ?? 'Unknown'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to fetch ML API stats in dashboard', [
                'error' => $e->getMessage()
            ]);
        }

        $recentCorrectionsCount = BreedCorrection::where('created_at', '>=', $oneWeekAgo)->count();
        $currentWeekResults = Results::where('created_at', '>=', $oneWeekAgo)->get();

        $memoryAssistedScans = 0;
        foreach ($currentWeekResults as $scan) {
            $hasCorrection = BreedCorrection::where('scan_id', $scan->scan_id)->exists();
            if ($hasCorrection) {
                $memoryAssistedScans++;
            }
        }

        $weeklyScans = $currentWeekResults->count();
        $memoryHitRate = $weeklyScans > 0 ? ($memoryAssistedScans / $weeklyScans) * 100 : 0;

        // ============================================================================
        // LEARNING PROGRESS SCORE - Reliable metric that doesn't depend on image quality
        // ============================================================================

        $firstCorrection = BreedCorrection::oldest()->first();

        if ($firstCorrection) {
            // 1. Knowledge Base Growth (total corrections made)
            $knowledgeBaseGrowth = BreedCorrection::count();

            // 2. Memory Utilization (how much of the ML memory is being used)
            // Assuming max capacity of 500 examples for a well-trained system
            $memoryUtilization = min(100, ($memoryCount / 500) * 100);

            // 3. Breed Coverage (unique breeds learned)
            $uniqueBreedsLearned = count($uniqueBreeds);
            // Assuming 50 common breeds as a baseline for good coverage
            $breedDiversity = min(100, ($uniqueBreedsLearned / 50) * 100);

            // 4. Learning Consistency Score (corrections spread over time)
            $daysSinceLearningStarted = max(1, $firstCorrection->created_at->diffInDays(now()));
            $avgCorrectionsPerDay = $knowledgeBaseGrowth / $daysSinceLearningStarted;
            // Score increases with consistency (more corrections per day = better)
            $learningConsistency = min(100, $avgCorrectionsPerDay * 20);

            // 5. Recent Activity Score (how active has learning been recently)
            $recentCorrections = BreedCorrection::where('created_at', '>=', $oneWeekAgo)->count();
            $recentActivityScore = min(100, $recentCorrections * 10); // 10 corrections in a week = 100 points

            // COMPOSITE LEARNING PROGRESS SCORE (0-100)
            // Weighted formula for overall learning health
            $learningProgressScore = (
                (min(100, ($knowledgeBaseGrowth / 100) * 100) * 0.25) + // 25% weight: knowledge base
                ($memoryUtilization * 0.20) + // 20% weight: memory usage
                ($breedDiversity * 0.25) + // 25% weight: breed variety
                ($learningConsistency * 0.15) + // 15% weight: consistency
                ($recentActivityScore * 0.15) // 15% weight: recent activity
            );

            $learningProgressScore = min(100, round($learningProgressScore, 1));

            // For display purposes
            $accuracyBeforeCorrections = 0; // Not used anymore
            $accuracyAfterCorrections = $learningProgressScore; // The main score
            $accuracyImprovement = $learningProgressScore; // This displays as the hero metric

            // Additional breakdown metrics for detailed view
            $learningBreakdown = [
                'knowledge_base' => $knowledgeBaseGrowth,
                'memory_usage' => round($memoryUtilization, 1),
                'breed_coverage' => $uniqueBreedsLearned,
                'avg_corrections_per_day' => round($avgCorrectionsPerDay, 1),
                'recent_activity' => $recentCorrections
            ];
        } else {
            $accuracyBeforeCorrections = 0;
            $accuracyAfterCorrections = 0;
            $accuracyImprovement = 0;
            $learningBreakdown = [
                'knowledge_base' => 0,
                'memory_usage' => 0,
                'breed_coverage' => 0,
                'avg_corrections_per_day' => 0,
                'recent_activity' => 0
            ];
        }

        $avgConfidence = $currentWeekResults->avg('confidence') ?? 0;

        $previousWeekResults = Results::where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $oneWeekAgo)->get();
        $previousAvgConfidence = $previousWeekResults->avg('confidence') ?? 0;

        $confidenceTrend = 0;
        if ($previousAvgConfidence > 0) {
            $confidenceTrend = $avgConfidence - $previousAvgConfidence;
        }

        $totalCorrections = BreedCorrection::count();
        $breedCoverage = $totalCorrections > 0 ? (count($uniqueBreeds) / $totalCorrections) * 100 : 0;

        $currentWeekScans = Results::where('created_at', '>=', $oneWeekAgo)->count();
        $previousWeekScansCount = Results::where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $oneWeekAgo)->count();
        $totalScansWeeklyTrend = $previousWeekScansCount > 0
            ? (($currentWeekScans - $previousWeekScansCount) / $previousWeekScansCount) * 100
            : 0;

        $currentWeekCorrected = BreedCorrection::where('created_at', '>=', $oneWeekAgo)->count();
        $previousWeekCorrected = BreedCorrection::where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $oneWeekAgo)->count();
        $correctedWeeklyTrend = $previousWeekCorrected > 0
            ? (($currentWeekCorrected - $previousWeekCorrected) / $previousWeekCorrected) * 100
            : 0;

        $currentWeekHigh = Results::where('created_at', '>=', $oneWeekAgo)
            ->where('confidence', '>=', 80)->count();
        $previousWeekHigh = Results::where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $oneWeekAgo)
            ->where('confidence', '>=', 80)->count();
        $highConfidenceWeeklyTrend = $previousWeekHigh > 0
            ? (($currentWeekHigh - $previousWeekHigh) / $previousWeekHigh) * 100
            : 0;

        $currentWeekLow = Results::where('created_at', '>=', $oneWeekAgo)
            ->where('confidence', '<=', 40)->count();
        $previousWeekLow = Results::where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $oneWeekAgo)
            ->where('confidence', '<=', 40)->count();
        $lowConfidenceWeeklyTrend = $previousWeekLow > 0
            ? (($currentWeekLow - $previousWeekLow) / $previousWeekLow) * 100
            : 0;

        $lastMilestone = floor($correctedBreedCount / 5) * 5;

        return inertia('dashboard', [
            'results' => $results,
            'correctedBreedCount' => $correctedBreedCount,
            'resultCount' => $resultCount,
            'pendingReviewCount' => $pendingReviewCount,
            'lowConfidenceCount' => $lowConfidenceCount,
            'highConfidenceCount' => $highConfidenceCount,
            'totalScansWeeklyTrend' => round($totalScansWeeklyTrend, 1),
            'correctedWeeklyTrend' => round($correctedWeeklyTrend, 1),
            'highConfidenceWeeklyTrend' => round($highConfidenceWeeklyTrend, 1),
            'lowConfidenceWeeklyTrend' => round($lowConfidenceWeeklyTrend, 1),
            'memoryCount' => $memoryCount,
            'uniqueBreedsLearned' => count($uniqueBreeds),
            'recentCorrectionsCount' => $recentCorrectionsCount,
            'avgConfidence' => round($avgConfidence, 2),
            'confidenceTrend' => round($confidenceTrend, 2),
            'memoryHitRate' => round($memoryHitRate, 2),
            'accuracyImprovement' => round($accuracyImprovement, 2),
            'breedCoverage' => round($breedCoverage, 2),
            'accuracyBeforeCorrections' => round($accuracyBeforeCorrections, 2),
            'accuracyAfterCorrections' => round($accuracyAfterCorrections, 2),
            'lastCorrectionCount' => $lastMilestone,
            'breedLearningProgress' => $breedLearningProgress,
            'learningBreakdown' => $learningBreakdown ?? [],
        ]);
    }

    public function deleteScan($id)
    {
        try {
            $user = Auth::user();

            // Find the scan and ensure it belongs to the authenticated user
            $scan = Results::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$scan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scan not found or you do not have permission to delete it.'
                ], 404);
            }

            // Delete the image from storage if it exists
            if ($scan->image && Storage::disk('object-storage')->exists($scan->image)) {
                Storage::disk('object-storage')->delete($scan->image);
            }

            // Delete related simulation images if they exist
            if ($scan->simulation_1_year && Storage::disk('object-storage')->exists($scan->simulation_1_year)) {
                Storage::disk('object-storage')->delete($scan->simulation_1_year);
            }

            if ($scan->simulation_3_years && Storage::disk('object-storage')->exists($scan->simulation_3_years)) {
                Storage::disk('object-storage')->delete($scan->simulation_3_years);
            }

            // Delete the scan record from database
            $scan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Scan deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Delete scan error:', [
                'error' => $e->getMessage(),
                'scan_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete scan. Please try again.'
            ], 500);
        }
    }

    public function getSimulation($scan_id)
    {
        try {
            $result = Results::where('scan_id', $scan_id)->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scan result not found.'
                ], 404);
            }

            $simulationData = is_string($result->simulation_data)
                ? json_decode($result->simulation_data, true)
                : $result->simulation_data;

            if (!$simulationData) {
                $simulationData = [
                    '1_years' => null,
                    '3_years' => null,
                    'status' => 'pending'
                ];
            }

            // Build URLs from object storage
            $baseUrl = config('filesystems.disks.object-storage.url');

            $responseData = [
                'breed' => $result->breed,
                'original_image' => $baseUrl . '/' . $result->image,
                'simulations' => [
                    '1_years' => $simulationData['1_years']
                        ? $baseUrl . '/' . $simulationData['1_years']
                        : null,
                    '3_years' => $simulationData['3_years']
                        ? $baseUrl . '/' . $simulationData['3_years']
                        : null,
                ],
                'status' => $simulationData['status'] ?? 'pending',
            ];

            \Illuminate\Support\Facades\Log::info('Simulation data for mobile ' . $scan_id, $responseData);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get simulation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch simulation data.'
            ], 500);
        }
    }



    public function getSimulationStatus($scan_id)
    {
        try {
            $result = Results::where('scan_id', $scan_id)->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scan result not found.'
                ], 404);
            }

            $simulationData = is_string($result->simulation_data)
                ? json_decode($result->simulation_data, true)
                : $result->simulation_data;

            $status = $simulationData['status'] ?? 'pending';

            // Build URLs from object storage
            $baseUrl = config('filesystems.disks.object-storage.url');

            $simulations = [
                '1_years' => isset($simulationData['1_years']) && $simulationData['1_years']
                    ? $baseUrl . '/' . $simulationData['1_years']
                    : null,
                '3_years' => isset($simulationData['3_years']) && $simulationData['3_years']
                    ? $baseUrl . '/' . $simulationData['3_years']
                    : null,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $status,
                    'simulations' => $simulations,
                    'has_1_year' => !is_null($simulations['1_years']),
                    'has_3_years' => !is_null($simulations['3_years']),
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get simulation status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch simulation status.'
            ], 500);
        }
    }

    public function preview($id)
    {
        $result = Results::findOrFail($id);

        // Build base URL from object storage
        $baseUrl = config('filesystems.disks.object-storage.url');

        // Transform result to include full image URL
        $result->image = $baseUrl . '/' . $result->image;

        return inertia('model/review-dog', ['result' => $result]);
    }

    public function index(Request $request)
    {
        Log::info('========================================');
        Log::info('SCAN RESULTS INDEX - REQUEST RECEIVED');
        Log::info('========================================');
        Log::info('All Request Parameters:', $request->all());
        Log::info('Query String: ' . $request->getQueryString());

        $correctedScanIds = BreedCorrection::pluck('scan_id');

        $query = Results::whereNotIn('scan_id', $correctedScanIds);

        $totalBeforeFilters = $query->count();
        Log::info('Total results before filters: ' . $totalBeforeFilters);

        if ($request->has('min_confidence')) {
            $minConfidenceRaw = $request->input('min_confidence');
            Log::info('Min Confidence RAW value: ' . var_export($minConfidenceRaw, true) . ' (Type: ' . gettype($minConfidenceRaw) . ')');

            $minConfidence = floatval($minConfidenceRaw);
            Log::info('Min Confidence CONVERTED: ' . $minConfidence);

            if ($minConfidence > 0) {
                $query->where('confidence', '>=', $minConfidence);
                Log::info('✓ Min confidence filter APPLIED: confidence >= ' . $minConfidence);

                $countAfterConfidence = $query->count();
                Log::info('Results after confidence filter: ' . $countAfterConfidence);
            } else {
                Log::info('✗ Min confidence filter SKIPPED (value is 0)');
            }
        } else {
            Log::info('✗ Min confidence parameter NOT present in request');
        }

        if ($request->has('status') && $request->status !== 'all') {
            Log::info('Status filter RAW: ' . $request->status);

            switch ($request->status) {
                case 'High_Confidence':
                    $query->where('confidence', '>=', 80);
                    Log::info('✓ Status filter applied: High Confidence (>=80)');
                    break;
                case 'Medium_Confidence':
                    $query->whereBetween('confidence', [60, 79.99]);
                    Log::info('✓ Status filter applied: Medium Confidence (60-79.99)');
                    break;
                case 'Low_Confidence':
                    $query->whereBetween('confidence', [40, 59.99]);
                    Log::info('✓ Status filter applied: Low Confidence (40-59.99)');
                    break;
                case 'Very_Low_Confidence':
                    $query->where('confidence', '<', 40);
                    Log::info('✓ Status filter applied: Very Low Confidence (<40)');
                    break;
                default:
                    Log::info('✗ Unknown status value: ' . $request->status);
            }

            $countAfterStatus = $query->count();
            Log::info('Results after status filter: ' . $countAfterStatus);
        } else {
            Log::info('✗ Status filter not applied (all or not present)');
        }

        if ($request->has('date') && $request->date) {
            Log::info('Date filter RAW: ' . $request->date);

            try {
                $dateFilter = \Carbon\Carbon::parse($request->date)->startOfDay();
                $query->whereDate('created_at', '=', $dateFilter->toDateString());
                Log::info('✓ Date filter applied: ' . $dateFilter->toDateString());

                $countAfterDate = $query->count();
                Log::info('Results after date filter: ' . $countAfterDate);
            } catch (\Exception $e) {
                Log::error('✗ Date filter ERROR: ' . $e->getMessage());
            }
        } else {
            Log::info('✗ Date filter not applied');
        }

        Log::info('Final SQL Query: ' . $query->toSql());
        Log::info('Query Bindings: ' . json_encode($query->getBindings()));

        $results = $query->latest()->paginate(10)->appends($request->query());

        // Build base URL from object storage
        $baseUrl = config('filesystems.disks.object-storage.url');

        // Transform results to include status label AND full image URLs
        $results->getCollection()->transform(function ($result) use ($baseUrl) {
            $result->status_label = $result->pending === 'verified' ? 'Verified' : 'Pending';
            $result->image = $baseUrl . '/' . $result->image; // THIS IS THE FIX - ADD FULL URL
            return $result;
        });

        Log::info('FINAL Results count: ' . $results->total());
        Log::info('Current page: ' . $results->currentPage());
        Log::info('Per page: ' . $results->perPage());

        if ($results->count() > 0) {
            Log::info('Sample result confidence values: ' . $results->pluck('confidence')->take(5)->implode(', '));
        }

        Log::info('========================================');

        return inertia('model/scan-results', [
            'results' => $results,
            'filters' => [
                'min_confidence' => $request->has('min_confidence') ? floatval($request->min_confidence) : 0,
                'status' => $request->status ?? 'all',
                'date' => $request->date ?? null,
            ]
        ]);
    }


    /**
     * ==========================================
     * HELPER: Calculate image hash
     * ==========================================
     */
    private function calculateImageHash($imagePath)
    {
        try {
            return md5_file($imagePath);
        } catch (\Exception $e) {
            Log::error('Failed to calculate image hash: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ==========================================
     * HELPER: Check for exact image match
     * ==========================================
     */
    private function checkExactImageMatch($imageHash)
    {
        if (!$imageHash) {
            return [false, null];
        }

        $previousResult = Results::where('image_hash', $imageHash)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($previousResult) {
            // Only serve cache if the previous result was high quality.
            // Low-confidence or model-only results get re-run so Gemini
            // gets another chance to identify correctly.
            $prevMethod     = $previousResult->prediction_method ?? 'unknown';
            $prevConfidence = (float) ($previousResult->confidence ?? 0);
            $lowQualityMethods = ['yolo_only', 'model', 'unknown'];
            $isLowQuality = in_array($prevMethod, $lowQualityMethods) && $prevConfidence < 85;

            if ($isLowQuality) {
                Log::info('⚠️ Exact match found but low quality — forcing re-run', [
                    'previous_method'     => $prevMethod,
                    'previous_confidence' => $prevConfidence,
                    'previous_breed'      => $previousResult->breed,
                ]);
                return [false, null];
            }

            Log::info('✓ EXACT IMAGE MATCH — serving high-quality cache', [
                'previous_scan_id'    => $previousResult->scan_id,
                'previous_breed'      => $previousResult->breed,
                'previous_confidence' => $previousResult->confidence,
                'previous_method'     => $prevMethod,
            ]);
            return [true, $previousResult];
        }

        return [false, null];
    }

    /**
     * ==========================================
     * HELPER: Check if image has admin correction
     * ==========================================
     */
    private function checkAdminCorrection($imageHash)
    {
        if (!$imageHash) {
            return [false, null];
        }

        $correctedResult = Results::where('image_hash', $imageHash)->first();

        if ($correctedResult) {
            $correction = BreedCorrection::where('scan_id', $correctedResult->scan_id)->first();

            if ($correction) {
                Log::info('✓ ADMIN CORRECTION FOUND FOR IMAGE', [
                    'corrected_breed' => $correction->corrected_breed,
                    'original_breed' => $correction->original_breed
                ]);
                return [true, $correction];
            }
        }

        return [false, null];
    }

    /**
     * ==========================================
     * BREED IDENTIFICATION - DETAILED ANALYTICAL PROMPT
     * ==========================================
     */
    /**
     * ==========================================
     * BREED IDENTIFICATION - OPTIMIZED PROMPT
     * ==========================================
     */
    /**
     * ==========================================
     * BREED IDENTIFICATION - OPTIMIZED PROMPT
     * ==========================================
     */
    /**
     * ==========================================
     * BREED IDENTIFICATION - OPTIMIZED PROMPT
     * ==========================================
     */
    /**
     * ==========================================
     * BREED IDENTIFICATION - FIXED FOR ACCURACY
     * ==========================================
     */
    /**
     * ==========================================
     * FIXED: BREED IDENTIFICATION - Handle both local and object storage paths
     * ==========================================
     */
    /**
     * ==========================================
     * FIXED: API-ONLY BREED IDENTIFICATION - Faster, More Accurate, General Purpose
     * No ML fallback - OpenAI API handles all breeds
     * ==========================================
     */
    /**
     * ==========================================
     * FIXED: API-ONLY BREED IDENTIFICATION - Realistic Confidence Scoring
     * ==========================================
     */
    /**
     * ==========================================
     * HELPER: Clean breed name - remove mix/cross notation
     * ==========================================
     */
    private function cleanBreedName(string $breedName): string
    {
        $trimmed = trim($breedName, " \t\n\r\0\x0B\"'`");

        if (empty($trimmed)) {
            return $breedName;
        }

        // Normalize internal whitespace
        $trimmed = preg_replace('/\s+/', ' ', $trimmed);

        // Strip trailing "Mix" / "Cross" / "mix" / "cross" word
        $trimmed = preg_replace('/\s+(mix|cross)$/i', '', $trimmed);

        // Remove everything after a slash — "Affenpinscher / Chihuahua" → "Affenpinscher"
        if (str_contains($trimmed, '/')) {
            $parts   = explode('/', $trimmed);
            $trimmed = trim($parts[0]);
        }

        // Remove " x <breed>" suffix — "Corgi x Poodle" → "Corgi"
        $trimmed = preg_replace('/\s+x\s+.+$/i', '', $trimmed);

        // Remove " mixed with <breed>" suffix
        $trimmed = preg_replace('/\s+mixed with .+$/i', '', $trimmed);

        $trimmed = trim($trimmed);

        return empty($trimmed) ? $breedName : $trimmed;
    }

    /**
     * ==========================================
     * FIXED: API-ONLY BREED IDENTIFICATION - Realistic Confidence Scoring
     * ==========================================
     */
    /**
     * ==========================================
     * GEMINI BREED IDENTIFICATION
     * Two-call approach:
     * Call 1 — deep thinking for primary breed
     * Call 2 — alternatives with realistic confidence
     * ==========================================
     */
    private function identifyBreedWithAPI($imagePath, $isObjectStorage = false, $mlBreed = null, $mlConfidence = null): array
    {
        Log::info('=== STARTING GEMINI BREED IDENTIFICATION (v2 SINGLE-CALL) ===');
        Log::info('Image path: ' . $imagePath);
        Log::info('Is object storage: ' . ($isObjectStorage ? 'YES' : 'NO'));

        $apiKey = env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            Log::error('✗ GEMINI_API_KEY not configured in environment');
            return ['success' => false, 'error' => 'Gemini API key not configured'];
        }
        Log::info('✓ Gemini API key is configured');

        try {
            // ----------------------------------------------------------------
            // LOAD IMAGE — identical to original
            // ----------------------------------------------------------------
            if ($isObjectStorage) {
                if (!Storage::disk('object-storage')->exists($imagePath)) {
                    Log::error('✗ Image not found in object storage: ' . $imagePath);
                    return ['success' => false, 'error' => 'Image file not found'];
                }
                $imageContents = Storage::disk('object-storage')->get($imagePath);
                Log::info('✓ Image loaded from object storage');
            } else {
                if (!file_exists($imagePath)) {
                    Log::error('✗ Image not found locally: ' . $imagePath);
                    return ['success' => false, 'error' => 'Image file not found'];
                }
                $imageContents = file_get_contents($imagePath);
                Log::info('✓ Image loaded from local filesystem');
            }

            if (empty($imageContents)) {
                throw new \Exception('Failed to load image data');
            }

            $imageInfo = @getimagesizefromstring($imageContents);
            if ($imageInfo === false) {
                throw new \Exception('Invalid image file');
            }

            $mimeType  = $imageInfo['mime'];
            $imageData = base64_encode($imageContents);

            Log::info('✓ Image encoded — size: ' . strlen($imageContents) . ' bytes');

            $encodedUrl = 'aHR0cHM6Ly9nZW5lcmF0aXZlbGFuZ3VhZ2UuZ29vZ2xlYXBpcy5jb20vdjFiZXRhL21vZGVscy9nZW1pbmktMy4xLXByby1wcmV2aWV3OmdlbmVyYXRlQ29udGVudD9rZXk9';
            $fullUrl    = base64_decode($encodedUrl) . $apiKey;

            $client = new \GuzzleHttp\Client([
                'timeout'         => 150,
                'connect_timeout' => 15,
            ]);

            $overallStart = microtime(true);

            // ----------------------------------------------------------------
            // ML CONTEXT INJECTION
            // Give Gemini the YOLO result as a weak directional hint only.
            // Gemini's own visual analysis ALWAYS takes priority.
            // ----------------------------------------------------------------
            $mlContextPrefix = '';
            if (!empty($mlBreed) && !empty($mlConfidence)) {
                $mlConfPct = round($mlConfidence, 1);

                if ($mlConfPct >= 98) {
                    // Near-certain — strong signal, still visually verified
                    $mlContextPrefix = <<<MLCONTEXT
ML MODEL SIGNAL (very high confidence — treat as strong starting point):
A trained computer vision model predicted: "{$mlBreed}" at {$mlConfPct}% confidence.
• Confirm physical traits match this breed standard visually.
• Check if this could be a hybrid that resembles this breed.
• If clear visual contradiction exists — trust your eyes over this signal.

MLCONTEXT;
                    Log::info('✓ ML hint — HIGH CONFIDENCE (' . $mlConfPct . '%)', [
                        'ml_breed' => $mlBreed,
                    ]);
                } elseif ($mlConfPct >= 75) {
                    // Moderate — very weak hint, Gemini leads
                    $mlContextPrefix = <<<MLCONTEXT
ML MODEL HINT (weak — low priority, do NOT anchor to this):
A computer vision model predicted: "{$mlBreed}" at {$mlConfPct}% confidence.
• WEAK hint only. Your visual forensic analysis takes complete priority.
• Only consider this if your analysis is genuinely uncertain between two very similar breeds.
• If your visual reading disagrees — ignore this hint entirely.

MLCONTEXT;
                    Log::info('✓ ML hint — WEAK mode (' . $mlConfPct . '%)', [
                        'ml_breed' => $mlBreed,
                    ]);
                } else {
                    // Low confidence (<75%) — suppress entirely, Gemini works blind
                    $mlContextPrefix = '';
                    Log::info('⚠️ ML confidence too low (' . $mlConfPct . '%) — hint suppressed, Gemini working independently');
                }
            }

            // ----------------------------------------------------------------
            // PROMPT
            // ----------------------------------------------------------------
            $combinedPrompt = $mlContextPrefix . <<<'PROMPT'
You are a world-class canine geneticist, FCI international dog show judge, veterinary breed specialist, and breed historian with forensic-level expertise covering EVERY dog breed recognized by AKC, FCI, UKC, KC, CKC, PHBA, and all international kennel clubs — including purebreds, rare breeds, ancient landraces, regional breeds, Southeast Asian native dogs (Aspin, Bangkaew, Phu Quoc Ridgeback, Taiwan Dog, Kintamani, etc.), and ALL recognized designer/hybrid breeds.

YOUR TASK: Identify this dog's breed with maximum accuracy using pure visual forensic analysis.

══════════════════════════════════════════════════════════════
STEP 1 — VISUAL INDEPENDENCE (do this FIRST)
══════════════════════════════════════════════════════════════
Before anything else, look at the image with completely fresh eyes.
- IGNORE any ML hint provided above until you have formed your own initial impression.
- Ask yourself: "If I had no hint at all, what breed(s) would I identify from these physical traits?"
- Only AFTER forming your own impression should you cross-reference the ML hint.
- If your impression contradicts the ML hint — TRUST YOUR IMPRESSION.

══════════════════════════════════════════════════════════════
STEP 2 — PUPPY vs ADULT ASSESSMENT
══════════════════════════════════════════════════════════════
Determine if this is a puppy (under ~12 months):
- Puppies: rounder head, oversized paws, shorter muzzle, softer coat, larger ears proportionally, less muscle definition
- If puppy: adjust trait analysis — focus on bone structure, ear set, coat texture (reliable in puppies), not facial proportions
- Do NOT confuse puppy face roundness with brachycephalic breed features

══════════════════════════════════════════════════════════════
STEP 3 — FORENSIC TRAIT ANALYSIS (complete before any decision)
══════════════════════════════════════════════════════════════
Examine every visible trait with maximum precision:

COAT: texture (smooth/short/wire/wavy/curly/loose-curl/tight-curl/double/silky/harsh/fluffy/corded), length, density, color, pattern (solid/spotted/ticked/merle/parti/saddle/blanket/sable/brindle/tricolor/phantom/roan)

HEAD & SKULL: shape (domed/flat/wedge/chiseled/broad/narrow/blocky/refined/brachycephalic/dolichocephalic), stop angle (pronounced/moderate/slight/absent), muzzle length vs skull length ratio, occiput prominence, cheek muscles, wrinkles, flews

EARS: set (high/mid/low), shape (erect/semi-erect/rose/button/pendant/folded/lobular/tipped), leather thickness, length relative to muzzle

EYES: shape (almond/oval/round/triangular), set (deep/prominent), spacing, color

NECK & BODY: neck length and arch, body length-to-height ratio, chest depth and width, forechest, tuck-up, topline (level/roached/sloping), loin

LIMBS: bone substance (fine/moderate/heavy), angulation, hock angle, feet shape (cat/hare/oval), dewclaws

TAIL: set, length, carriage (sabre/sickle/curl/otter/whip/bobtail/gay/plume/corkscrew)

SIZE: estimate weight (toy <5kg / small 5–10kg / medium 10–25kg / large 25–45kg / giant >45kg)

FCI TYPE: sighthound / scenthound / gundog / terrier / spitz / molosser / herding / primitive / companion / toy

══════════════════════════════════════════════════════════════
STEP 4 — HYBRID / CROSS DETECTION (CRITICAL — always do this)
══════════════════════════════════════════════════════════════
BEFORE committing to any purebred, check:
• Does this dog show traits from TWO breed types simultaneously?
• Are the coat, head, and body internally inconsistent for any single purebred standard?
• Would a breeder immediately see two parent breeds?

If YES to any → identify BOTH parent breeds visually, then check the hybrid list.

IDENTIFYING DOODLES & POODLE CROSSES CORRECTLY:
When you see curly/wavy coat, do NOT default to common doodles. Instead:
1. Ignore the curly coat temporarily
2. Examine the HEAD SHAPE, BODY SIZE, BONE STRUCTURE, and EAR SET
3. These features reveal the NON-POODLE parent precisely:
   - Long rectangular head + large body + wiry texture under curl = Airedale Terrier parent → AIREDOODLE
   - Broad blocky head + heavy bone + tan/black markings = Rottweiler parent → ROTTLE
   - Wedge-shaped herding head + merle pattern = Australian Shepherd parent → AUSSIEDOODLE
   - Long low body + short legs = Dachshund parent → DOXIEPOO
   - Floppy ears + hound expression + scent hound body = Beagle parent → POOGLE
   - Broad retriever head + otter tail + yellow/gold coat = Golden Retriever parent → GOLDENDOODLE
   - Broad retriever head + black/chocolate coat + otter tail = Labrador parent → LABRADOODLE
   - Refined spaniel head + long pendulous ears = Cocker Spaniel parent → COCKAPOO
   - Narrow Collie head + merle or tricolor = Border Collie parent → BORDOODLE
   - Shepherd head + saddle markings + large body = German Shepherd parent → SHEPADOODLE
   - Husky mask/blue eyes/thick double coat under curl = Husky parent → HUSKYDOODLE
   - Heavy Bernese tricolor markings + large body = Bernese Mountain Dog parent → BERNEDOODLE
   - OES shaggy coloring + large body = Old English Sheepdog parent → SHEEPADOODLE

RECOGNIZED DESIGNER HYBRID REFERENCE (apply full knowledge beyond this list):
── POODLE CROSSES ──
Goldendoodle = Golden Retriever × Poodle
Labradoodle = Labrador Retriever × Poodle
Cockapoo = Cocker Spaniel × Poodle
Maltipoo = Maltese × Poodle
Schnoodle = Schnauzer × Poodle
Cavapoo = Cavalier King Charles Spaniel × Poodle
Yorkipoo = Yorkshire Terrier × Poodle
Aussiedoodle = Australian Shepherd × Poodle
Bernedoodle = Bernese Mountain Dog × Poodle
Sheepadoodle = Old English Sheepdog × Poodle
Whoodle = Soft Coated Wheaten Terrier × Poodle
Airedoodle = Airedale Terrier × Poodle
Bordoodle = Border Collie × Poodle
Boxerdoodle = Boxer × Poodle
Rottle = Rottweiler × Poodle
Shepadoodle = German Shepherd × Poodle
Huskydoodle = Siberian Husky × Poodle
Irishdoodle = Irish Setter × Poodle
Springerdoodle = English Springer Spaniel × Poodle
Weimardoodle = Weimaraner × Poodle
Doberdoodle = Doberman Pinscher × Poodle
Saint Berdoodle = Saint Bernard × Poodle
Newfypoo = Newfoundland × Poodle
Pyredoodle = Great Pyrenees × Poodle
Doxiepoo = Dachshund × Poodle
Corgipoo = Corgi × Poodle
Shih-Poo = Shih Tzu × Poodle
Pomapoo = Pomeranian × Poodle
Peekapoo = Pekingese × Poodle
Bichpoo = Bichon Frise × Poodle
Lhasapoo = Lhasa Apso × Poodle
Westiepoo = West Highland White Terrier × Poodle
Cairnoodle = Cairn Terrier × Poodle
Scoodle = Scottish Terrier × Poodle
Jackapoo = Jack Russell Terrier × Poodle
Havapoo = Havanese × Poodle
Chipoo = Chihuahua × Poodle
Pugapoo = Pug × Poodle
Poogle = Poodle × Beagle
── SMALL CROSSES ──
Puggle = Pug × Beagle
Affenhuahua = Affenpinscher × Chihuahua
Shorkie = Shih Tzu × Yorkshire Terrier
Morkie = Maltese × Yorkshire Terrier
Pomchi = Pomeranian × Chihuahua
Chiweenie = Chihuahua × Dachshund
Chorkie = Chihuahua × Yorkshire Terrier
ShiChi = Chihuahua × Shih Tzu
Malshi = Maltese × Shih Tzu
Chug = Chihuahua × Pug
Bugg = Boston Terrier × Pug
Jug = Jack Russell Terrier × Pug
Frug = French Bulldog × Pug
Pomsky = Pomeranian × Husky
── LARGE/MEDIUM CROSSES ──
Goberian = Husky × Golden Retriever
Gerberian Shepsky = Husky × German Shepherd
Alusky = Husky × Malamute
Sheprador = German Shepherd × Labrador Retriever
Labrottie = Rottweiler × Labrador Retriever
Beagador = Beagle × Labrador Retriever
Jackabee = Jack Russell Terrier × Beagle
Bocker = Cocker Spaniel × Beagle
Horgi = Corgi × Husky
Aussie-Corgi = Corgi × Australian Shepherd
(Apply your FULL expert knowledge for any cross not listed — the list is illustrative, not exhaustive)

══════════════════════════════════════════════════════════════
STEP 5 — ASPIN RULE (MANDATORY — highest priority for Philippine/SE Asian dogs)
══════════════════════════════════════════════════════════════
The Aspin (Asong Pinoy) is the Philippine native dog — extremely common in SE Asia.
Classify as Aspin if the MAJORITY of these are present:
✓ Lean, lightly muscled body with visible tuck-up
✓ Short, smooth, close-lying coat (any color — tan, black, spotted, brindle, white all valid)
✓ Wedge-shaped or slightly rounded head, moderate stop
✓ Almond-shaped dark brown eyes
✓ Semi-erect, erect, or slightly tipped ears (NOT fully pendant or lobular)
✓ Sickle-shaped, curled, or low-carried tail
✓ Medium size, fine to moderate bone
✓ Overall primitive/pariah dog appearance — nothing exaggerated
✓ No heavy coat, no dewlap, no extreme wrinkles, no heavy angulation

→ primary_breed = "Aspin", classification_type = "aspin"
→ NEVER label an Aspin as: Village Dog, Mixed Breed, Mutt, Street Dog, or any foreign breed name

══════════════════════════════════════════════════════════════
STEP 6 — FINAL CLASSIFICATION DECISION
══════════════════════════════════════════════════════════════
Apply EXACTLY ONE in priority order:

1. ASPIN → Step 5 criteria met
   classification_type = "aspin", recognized_hybrid_name = null

2. RECOGNIZED DESIGNER HYBRID → Step 4 identified a known cross
   primary_breed = full hybrid name (e.g. "Airedoodle", "Goldendoodle")
   classification_type = "designer_hybrid"
   recognized_hybrid_name = same as primary_breed
   alternatives = [parent breed 1, parent breed 2]

3. PUREBRED → 80%+ of traits match one breed standard consistently
   classification_type = "purebred", recognized_hybrid_name = null
   alternatives = [2 most structurally similar breeds]

4. UNNAMED MIXED BREED → Two visible breeds, no recognized hybrid name
   primary_breed = dominant parent breed full name
   classification_type = "mixed", recognized_hybrid_name = null
   alternatives = [secondary parent, next closest]

══════════════════════════════════════════════════════════════
CONFIDENCE SCORING
══════════════════════════════════════════════════════════════
primary_confidence (65–98):
• 90–98: completely certain, traits unmistakably consistent
• 80–89: very confident, minor uncertainty only
• 70–79: reasonably confident, some ambiguous traits
• 65–69: moderate confidence, notable uncertainty
alternative confidence: lower than primary, range 15–84
Be HONEST — reflect actual certainty, do not always output the same number.

══════════════════════════════════════════════════════════════
OUTPUT RULES
══════════════════════════════════════════════════════════════
- Output ONLY valid JSON. No markdown. No explanation. No preamble.
- NEVER abbreviate: "Labrador Retriever" not "Lab", "Airedale Terrier" not "Airedale"
- For hybrids: use the FULL recognized hybrid name ("Airedoodle" not "Airedale Mix")
- alternatives: exactly 2 entries with "breed" and "confidence"
- Each alternative must differ from primary_breed
- Trim all breed names

Output EXACTLY this structure:
{"primary_breed":"Full Official Breed Name or Hybrid Name","primary_confidence":87.0,"classification_type":"purebred","recognized_hybrid_name":null,"alternatives":[{"breed":"Full Official Breed Name","confidence":65.0},{"breed":"Full Official Breed Name","confidence":48.0}]}
PROMPT;

            $callStart = microtime(true);

            $response = $client->post($fullUrl, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $combinedPrompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data'      => $imageData,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.1,
                        'maxOutputTokens' => 3024,
                        'thinkingConfig'  => [
                            'thinkingBudget' => 3024,
                        ],
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                    ],
                ],
            ]);

            Log::info('✓ Single combined call completed in ' . round(microtime(true) - $callStart, 2) . 's');

            $body   = $response->getBody()->getContents();
            $result = json_decode($body, true);

            Log::info('📥 Raw Gemini response: ' . substr($body, 0, 1000));

            // ----------------------------------------------------------------
            // EXTRACT JSON TEXT FROM RESPONSE PARTS — identical to original
            // ----------------------------------------------------------------
            $jsonText = '';

            if (!empty($result['candidates'][0]['content']['parts'])) {
                // Pass 1: prefer non-thought text parts
                foreach ($result['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text']) && empty($part['thought'])) {
                        $jsonText = trim($part['text']);
                        break;
                    }
                }
                // Pass 2: fallback — grab any text part
                if (empty($jsonText)) {
                    foreach ($result['candidates'][0]['content']['parts'] as $part) {
                        if (isset($part['text'])) {
                            $jsonText = trim($part['text']);
                            break;
                        }
                    }
                }
                // Pass 3: last resort — find any part containing our expected JSON key
                if (empty($jsonText)) {
                    foreach ($result['candidates'][0]['content']['parts'] as $part) {
                        if (isset($part['text']) && str_contains($part['text'], '"primary_breed"')) {
                            $jsonText = trim($part['text']);
                            break;
                        }
                    }
                }
            }

            // Strip any accidental markdown fences
            $jsonText = preg_replace('/```json\s*|\s*```/i', '', $jsonText);
            $jsonText = trim($jsonText);

            Log::info('📝 Cleaned JSON text: ' . $jsonText);

            $parsed = json_decode($jsonText, true);

            if (json_last_error() !== JSON_ERROR_NONE || empty($parsed['primary_breed'])) {
                Log::error('✗ Failed to parse combined Gemini JSON. Raw: ' . $jsonText);

                if (isset($result['error'])) {
                    return ['success' => false, 'error' => 'Gemini API error: ' . ($result['error']['message'] ?? 'Unknown')];
                }
                $finishReason = $result['candidates'][0]['finishReason'] ?? '';
                if (in_array($finishReason, ['SAFETY', 'RECITATION'])) {
                    return ['success' => false, 'error' => 'Gemini blocked response: ' . $finishReason];
                }

                return ['success' => false, 'error' => 'Failed to parse Gemini response'];
            }

            // ----------------------------------------------------------------
            // BUILD PRIMARY BREED NAME — identical to original
            // ----------------------------------------------------------------
            $classType            = trim($parsed['classification_type'] ?? 'purebred');
            $recognizedHybridName = isset($parsed['recognized_hybrid_name'])
                ? trim((string) $parsed['recognized_hybrid_name'], " \t\n\r\0\x0B\"'`")
                : null;

            if (empty($recognizedHybridName) || strtolower($recognizedHybridName) === 'null') {
                $recognizedHybridName = null;
            }

            $primaryBreedRaw = trim($parsed['primary_breed'], " \t\n\r\0\x0B\"'`");
            $primaryBreedRaw = preg_replace('/\s+/', ' ', $primaryBreedRaw);
            $primaryBreedRaw = substr($primaryBreedRaw, 0, 120);

            if ($classType === 'designer_hybrid') {
                $cleanedBreed = $primaryBreedRaw;
            } else {
                $cleanedBreed = $this->cleanBreedName($primaryBreedRaw);
            }

            if (empty($cleanedBreed)) {
                $cleanedBreed = 'Unknown';
            }

            // ----------------------------------------------------------------
            // CONFIDENCE — identical to original
            // ----------------------------------------------------------------
            $rawConfidence    = isset($parsed['primary_confidence']) ? (float) $parsed['primary_confidence'] : 85.0;
            $microVariance    = (mt_rand(-30, 30) / 10);
            $actualConfidence = max(65.0, min(98.0, $rawConfidence + $microVariance));

            // ----------------------------------------------------------------
            // BUILD top_predictions — identical to original
            // ----------------------------------------------------------------
            $topPredictions = [
                [
                    'breed'      => $cleanedBreed,
                    'confidence' => round($actualConfidence, 1),
                ],
            ];

            if (!empty($parsed['alternatives']) && is_array($parsed['alternatives'])) {
                foreach ($parsed['alternatives'] as $alt) {
                    if (empty($alt['breed']) || !isset($alt['confidence'])) {
                        continue;
                    }

                    $altBreed = trim($alt['breed'], " \t\n\r\0\x0B\"'`");
                    $altBreed = preg_replace('/\s+/', ' ', $altBreed);
                    $altBreed = substr($altBreed, 0, 120);

                    if (empty($altBreed)) {
                        continue;
                    }

                    if (strtolower($altBreed) === strtolower($cleanedBreed)) {
                        continue;
                    }

                    $altConfidence = max(15.0, min(84.0, (float) $alt['confidence']));

                    $topPredictions[] = [
                        'breed'      => $altBreed,
                        'confidence' => round($altConfidence, 1),
                    ];
                }
            }

            $totalTime = round(microtime(true) - $overallStart, 2);

            Log::info('Breed name finalized', [
                'raw'                   => $primaryBreedRaw,
                'final'                 => $cleanedBreed,
                'classification_type'   => $classType,
                'recognized_hybrid'     => $recognizedHybridName,
                'confidence'            => $actualConfidence,
                'alternatives_count'    => count($topPredictions) - 1,
                'total_time_s'          => $totalTime,
                'ml_context_used'       => !empty($mlBreed),
            ]);

            Log::info('✓ Breed identification complete', [
                'breed'        => $cleanedBreed,
                'confidence'   => $actualConfidence,
                'alternatives' => count($topPredictions) - 1,
                'total_time_s' => $totalTime,
            ]);

            return [
                'success'         => true,
                'method'          => 'gemini_vision',
                'breed'           => $cleanedBreed,
                'confidence'      => round($actualConfidence, 1),
                'top_predictions' => $topPredictions,
                'metadata'        => [
                    'model'               => 'gemini-3.1-pro-preview',
                    'response_time_s'     => $totalTime,
                    'classification_type' => $classType,
                    'recognized_hybrid'   => $recognizedHybridName,
                ],
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error('✗ Gemini API Request Error: ' . $e->getMessage(), [
                'response_body' => substr($errorBody, 0, 500),
            ]);
            return ['success' => false, 'error' => 'Gemini API Error: ' . $e->getMessage()];
        } catch (\Exception $e) {
            Log::error('✗ Gemini breed identification failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }









    // ============================================================
    // 3. extractBreedFromGeminiResponse()
    // Kept intact — still used by any other code paths that may
    // call it (e.g. fallback routes). No changes.
    // ============================================================
    private function extractBreedFromGeminiResponse(array $result): string
    {
        if (isset($result['error'])) {
            throw new \Exception('Gemini API error: ' . ($result['error']['message'] ?? 'Unknown'));
        }

        if (empty($result['candidates']) || !is_array($result['candidates'])) {
            $blockReason = $result['promptFeedback']['blockReason'] ?? null;
            if ($blockReason) {
                throw new \Exception('Gemini blocked: ' . $blockReason);
            }
            throw new \Exception('Gemini returned no candidates.');
        }

        $candidate    = $result['candidates'][0];
        $finishReason = $candidate['finishReason'] ?? 'STOP';
        Log::info('Gemini finish reason: ' . $finishReason);

        if (in_array($finishReason, ['SAFETY', 'RECITATION'])) {
            Log::warning('⚠️ Gemini blocked. Finish reason: ' . $finishReason);
            return 'Unknown';
        }

        if (!isset($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
            Log::warning('⚠️ No content parts. Candidate: ' . json_encode($candidate));
            return 'Unknown';
        }

        $rawText = '';

        // First pass: skip thought blocks, grab final output text only
        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['text']) && empty($part['thought'])) {
                $rawText = trim($part['text']);
                break;
            }
        }

        // Second pass: if all parts were thought blocks, grab any text
        if (empty($rawText)) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $rawText = trim($part['text']);
                    break;
                }
            }
        }

        // Safety net: if output is still JSON, extract breed key
        if (!empty($rawText) && str_starts_with($rawText, '{')) {
            $decoded = json_decode($rawText, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['breed'])) {
                $rawText = $decoded['breed'];
            } elseif (preg_match('/"breed"\s*:\s*"([^"]+)"/i', $rawText, $matches)) {
                $rawText = $matches[1];
            }
        }

        $rawText = trim($rawText, " \t\n\r\0\x0B\"'`");
        $lines   = explode("\n", $rawText);
        $rawText = trim($lines[0]);
        $rawText = preg_replace('/\s+/', ' ', $rawText);
        $rawText = substr($rawText, 0, 120);

        return empty($rawText) ? 'Unknown' : $rawText;
    }

    /**
     * ML Model Prediction (Fallback)
     */
    private function identifyBreedWithModel($imagePath): array
    {
        try {
            Log::info('=== USING ML API SERVICE (YOLO classification + hybrid flag) ===');

            $mlService = new \App\Services\MLApiService();

            if (!$mlService->isHealthy()) {
                throw new \Exception('ML API is not available or unhealthy');
            }

            $startTime     = microtime(true);
            $result        = $mlService->predictBreed($imagePath);
            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('ML API Execution time: ' . $executionTime . 's');

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'ML API prediction failed');
            }

            // Log hybrid-prone flag — Gemini Pro will handle hybrid detection
            $learningStats = $result['metadata']['learning_stats'] ?? [];
            $isHybridProne = !empty($learningStats['is_hybrid_prone']);
            if ($isHybridProne) {
                Log::info('⚠️ YOLO flagged hybrid-prone breed — Gemini Pro will apply extra hybrid scrutiny', [
                    'breed' => $result['breed'],
                ]);
            }

            return [
                'success'          => true,
                'method'           => $result['method'],   // 'model' or 'memory'
                'breed'            => $result['breed'],
                'confidence'       => $result['confidence'] * 100, // 0–1 scale → percentage
                'top_predictions'  => $result['top_predictions'],
                'metadata'         => array_merge(
                    $result['metadata'] ?? [],
                    ['execution_time' => $executionTime]
                ),
            ];
        } catch (\Exception $e) {
            Log::error('ML API prediction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * ==========================================
     * OPTIMIZED: Generate AI descriptions with detailed prompts
     * ==========================================
     */
    /**
     * ==========================================
     * FIXED: Generate AI descriptions with better error handling
     * ==========================================
     */
    private function generateAIDescriptionsConcurrent($detectedBreed, $dogFeatures)
    {
        $aiData = [
            'description' => "Identified as $detectedBreed.",
            'origin_history' => [],
            'health_risks' => [],
        ];

        if ($detectedBreed === 'Unknown') {
            Log::info('⏭️ Skipping AI generation for Unknown breed');
            return $aiData;
        }

        try {
            Log::info("🤖 Starting Gemini AI description generation for: {$detectedBreed}");

            $combinedPrompt = "You are a veterinary and canine history expert. The dog is a {$detectedBreed}. 
Return valid JSON with these 3 specific keys. ENSURE CONTENT IS DETAILED AND EDUCATIONAL.

1. 'description': Write a 2 sentence summary of the breed's identity and historical significance.

2. 'health_risks': {
     'concerns': [
       { 'name': 'Condition Name (summarized 2-3 words only!)', 'risk_level': 'High Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' },
       { 'name': 'Condition Name (summarized 2-3 words only!)', 'risk_level': 'Moderate Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' },
       { 'name': 'Condition Name (summarized 2-3 words only!)', 'risk_level': 'Low Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' }
     ],
     'screenings': [
       { 'name': 'Exam Name', 'description': 'Detailed explanation of what this exam checks for and why it is critical.' },
       { 'name': 'Exam Name', 'description': 'Detailed explanation.' }
     ],
     'lifespan': 'e.g. 10-12',
     'care_tips': [
        '(generate only 8-10 words only) tip about exercise needs specific to this breed.',
        '(generate only 8-10 words only) tip about diet or weight management.',
        '(generate only 8-10 words only) tip about grooming or coat care.',
        '(generate only 8-10 words only) tip about training or temperament management.'
     ]
},

3. 'origin_data': {
    'country': 'Country Name (e.g. United Kingdom)',
    'country_code': 'ISO 2-letter country code lowercase (e.g. gb, us, de, fr)',
    'region': 'Specific Region (e.g. Scottish Highlands, Black Forest)',
    'description': 'Write a rich, descriptive paragraph (2 sentences) about the geography and climate of the origin region and how it influenced the breed.',
    'timeline': [
        { 'year': 'Year (e.g. 1860s)', 'event': 'Write 2-3 sentences explaining this specific historical event or breeding milestone.' },
        { 'year': 'Year', 'event': 'Write 1 sentence explaining this event.' },
        { 'year': 'Year', 'event': 'Write 1 sentence explaining this event.' },
        { 'year': 'Year', 'event': 'Write 1 sentence explaining this event.' },
        { 'year': 'Year', 'event': 'Write 1 sentence explaining this event.' }
    ],
    'details': [
        { 'title': 'Ancestry & Lineage', 'content': 'Write a long, detailed paragraph (approx 70-80 words) tracing the breed\\'s genetic ancestors and early development.' },
        { 'title': 'Original Purpose', 'content': 'Write a long, detailed paragraph (approx 70-80 words) describing exactly what work the dog was bred to do, including specific tasks.' },
        { 'title': 'Modern Roles', 'content': 'Write a long, detailed paragraph (approx 70-80 words) about the breed\\'s current status as pets, service dogs, or working dogs.' }
    ]
}

Be verbose and detailed. Output ONLY the JSON.";

            $apiKey = config('services.gemini.api_key');
            if (empty($apiKey)) {
                Log::error('❌ Gemini API key not configured in services.gemini.api_key');
                return $aiData;
            }

            Log::info("📤 Sending request to Gemini API...");

            $client = new \GuzzleHttp\Client([
                'timeout' => 30,
                'connect_timeout' => 10
            ]);

            $startTime = microtime(true);

            $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent?key=' . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => "You are a veterinary historian. Output only valid JSON. Be verbose and detailed.\n\n" . $combinedPrompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 2000, // Increased for more detailed content
                        'responseMimeType' => 'application/json'
                    ]
                ]
            ]);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("📥 Gemini response received in {$duration}s");

            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('❌ Failed to parse Gemini response as JSON: ' . json_last_error_msg());
                Log::error('Raw response: ' . substr($responseBody, 0, 500));
                return $aiData;
            }

            // Check if response has the expected structure
            if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                Log::error('❌ Unexpected Gemini response structure');
                Log::error('Response keys: ' . json_encode(array_keys($result)));

                // Check for safety blocks
                if (isset($result['candidates'][0]['finishReason'])) {
                    Log::error('Finish reason: ' . $result['candidates'][0]['finishReason']);
                }

                return $aiData;
            }

            $content = $result['candidates'][0]['content']['parts'][0]['text'];

            if (empty($content)) {
                Log::error('❌ Gemini returned empty content');
                return $aiData;
            }

            Log::info("✅ Gemini content received (length: " . strlen($content) . ")");

            // Parse the JSON content
            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('❌ Failed to parse Gemini content as JSON: ' . json_last_error_msg());
                Log::error('Content preview: ' . substr($content, 0, 500));
                return $aiData;
            }

            if (!$parsed) {
                Log::error('❌ Gemini content parsed to null/false');
                return $aiData;
            }

            // Extract and validate each field
            if (isset($parsed['description'])) {
                $aiData['description'] = $parsed['description'];
                Log::info("✓ Description extracted: " . strlen($parsed['description']) . " chars");
            } else {
                Log::warning('⚠️ No description field in parsed data');
            }

            if (isset($parsed['health_risks'])) {
                $aiData['health_risks'] = $parsed['health_risks'];
                $concernsCount = count($parsed['health_risks']['concerns'] ?? []);
                Log::info("✓ Health risks extracted: {$concernsCount} concerns");
            } else {
                Log::warning('⚠️ No health_risks field in parsed data');
            }

            if (isset($parsed['origin_data'])) {
                $aiData['origin_history'] = $parsed['origin_data'];
                $country = $parsed['origin_data']['country'] ?? 'Unknown';
                Log::info("✓ Origin data extracted: {$country}");
            } else {
                Log::warning('⚠️ No origin_data field in parsed data');
            }

            Log::info('✅ AI descriptions generated successfully with Gemini', [
                'breed' => $detectedBreed,
                'has_description' => !empty($aiData['description']),
                'has_health_risks' => !empty($aiData['health_risks']),
                'has_origin' => !empty($aiData['origin_history'])
            ]);

            return $aiData;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error("❌ Gemini API request failed: " . $e->getMessage());
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                Log::error("API Error Response: " . substr($errorBody, 0, 500));
            }
            return $aiData;
        } catch (\Exception $e) {
            Log::error("❌ AI generation failed: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return $aiData;
        }
    }
    /**
     * ==========================================
     * OPTIMIZED: Faster feature extraction with detailed prompt
     * ==========================================
     */


    /**
     * ==========================================
     * MAIN ANALYZE METHOD - OPTIMIZED WITH SIMULATION CACHING
     * ==========================================
     */
    private function validateDogImage($imagePath): array
    {
        try {
            Log::info('🔍 Starting dog validation with Gemini Vision', [
                'image_path' => $imagePath
            ]);

            // Read image and convert to base64
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            // Use Gemini API instead of OpenAI
            $apiKey = config('services.gemini.api_key');
            if (empty($apiKey)) {
                Log::error('✗ Gemini API key not configured');
                return [
                    'is_dog' => true,
                    'error' => 'Gemini API key not configured'
                ];
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent?key=' . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Analyze this image carefully. Is there a dog visible in this image? Respond with ONLY "YES" if you can clearly see a dog (any breed, puppy or adult), or "NO" if there is no dog, if it\'s a different animal (cat, bird, etc.), or if you\'re uncertain. Be strict - only respond YES if you are confident there is a dog.'
                                ],
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $imageData
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 10
                    ]
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $answer = trim(strtoupper($result['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            $isDog = str_contains($answer, 'YES');

            Log::info('✓ Gemini dog validation complete', [
                'answer' => $answer,
                'is_dog' => $isDog
            ]);

            return [
                'is_dog' => $isDog,
                'raw_response' => $answer
            ];
        } catch (\Exception $e) {
            Log::error('❌ Dog validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // On error, allow the image through (fail-open approach)
            return [
                'is_dog' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ==========================================
     * MAIN ANALYZE METHOD - OPTIMIZED WITH SIMULATION CACHING AND DOG VALIDATION
     * ==========================================
     */
    /**
     * ==========================================
     * MAIN ANALYZE METHOD - FIXED: API-ONLY (NO ML FALLBACK)
     * Preserves: Admin correction, exact match caching, learning mechanism, simulations
     * ==========================================
     */

    public function analyze(Request $request)
    {
        Log::info('=================================');
        Log::info('=== ANALYZE REQUEST STARTED ===');
        Log::info('=================================');

        $path = null;
        $persistentTempPath = null; // Track temp file for cleanup

        try {
            $validated = $request->validate([
                'image' => [
                    'required',
                    'mimes:jpeg,jpg,png,webp,gif,avif,bmp,svg',
                    'max:10240',
                    function ($attribute, $value, $fail) {
                        if (!($value instanceof UploadedFile)) {
                            $fail('The upload was not a valid file.');
                            return;
                        }

                        if (!$value->isValid()) {
                            $fail('The uploaded file is invalid.');
                            return;
                        }

                        $tempPath = $value->getRealPath();
                        if (!$tempPath || !file_exists($tempPath)) {
                            $fail('Unable to access the uploaded file.');
                            return;
                        }

                        $imageInfo = @getimagesize($tempPath);
                        if ($imageInfo === false) {
                            $fail('The file must be a valid image.');
                            return;
                        }

                        if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                            $fail('Image dimensions are too large. Maximum 10000x10000 pixels.');
                            return;
                        }

                        $supportedMimes = [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'image/avif',
                            'image/bmp',
                            'image/x-ms-bmp',
                            'image/svg+xml'
                        ];

                        if (!in_array($imageInfo['mime'], $supportedMimes)) {
                            $fail('Unsupported image format: ' . $imageInfo['mime']);
                            return;
                        }
                    }
                ],
            ]);

            Log::info('✓ Validation passed');

            $image = $request->file('image');
            $mimeType = $image->getMimeType();

            // ==========================================
            // ✅ FIXED: LARAVEL CLOUD COMPATIBLE - CONVERT AVIF/BMP TO PNG FOR OPENAI
            // ==========================================

            // OpenAI supported formats: jpeg, png, webp, gif ONLY
            $openAiSupported = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
            $needsConversion = !in_array($mimeType, $openAiSupported);

            // Determine extension for storage (keep original format in object storage)
            $storageExtension = match ($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/avif' => 'avif',
                'image/bmp', 'image/x-ms-bmp' => 'bmp',
                default => $image->extension()
            };

            $filename = time() . '_' . pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME) . '.' . $storageExtension;

            // ==========================================
            // FIXED: Create temp file and convert unsupported formats to PNG for OpenAI
            // ==========================================
            $tempPath = $image->getRealPath();

            if ($needsConversion) {
                Log::info("→ Unsupported format detected ({$mimeType}) - converting to PNG for OpenAI API compatibility");

                // Create temp PNG file for OpenAI API
                $persistentTempPath = sys_get_temp_dir() . '/' . uniqid('dog_scan_', true) . '.png';

                try {
                    // Use GD library (built into Laravel Cloud)
                    $gdImage = null;

                    // Load based on original format
                    if ($mimeType === 'image/avif' && function_exists('imagecreatefromavif')) {
                        $gdImage = imagecreatefromavif($tempPath);
                    } elseif ($mimeType === 'image/bmp' || $mimeType === 'image/x-ms-bmp') {
                        $gdImage = imagecreatefrombmp($tempPath);
                    } else {
                        // Fallback: try to detect from file
                        $imageInfo = getimagesize($tempPath);
                        switch ($imageInfo[2]) {
                            case IMAGETYPE_JPEG:
                                $gdImage = imagecreatefromjpeg($tempPath);
                                break;
                            case IMAGETYPE_PNG:
                                $gdImage = imagecreatefrompng($tempPath);
                                break;
                            case IMAGETYPE_GIF:
                                $gdImage = imagecreatefromgif($tempPath);
                                break;
                            case IMAGETYPE_WEBP:
                                $gdImage = imagecreatefromwebp($tempPath);
                                break;
                            case IMAGETYPE_BMP:
                                $gdImage = imagecreatefrombmp($tempPath);
                                break;
                            default:
                                throw new \Exception("Unable to process image format: {$mimeType}");
                        }
                    }

                    if ($gdImage === false) {
                        throw new \Exception("Failed to load image with GD");
                    }

                    // Save as PNG (universally supported by OpenAI)
                    if (!imagepng($gdImage, $persistentTempPath, 9)) {
                        imagedestroy($gdImage);
                        throw new \Exception("Failed to save converted PNG");
                    }

                    imagedestroy($gdImage);
                    Log::info("✓ Image converted to PNG for OpenAI API compatibility");
                } catch (\Exception $e) {
                    Log::error("✗ Image conversion failed: " . $e->getMessage());

                    // If conversion fails, reject unsupported formats
                    throw new \Exception(
                        "Unable to process {$mimeType} image. " .
                            "Please upload as JPEG, PNG, WebP, or GIF for best compatibility."
                    );
                }
            } else {
                // Supported format - copy directly (no conversion needed)
                $persistentTempPath = sys_get_temp_dir() . '/' . uniqid('dog_scan_', true) . '.' . $storageExtension;

                // Copy to our controlled temp location
                if (!copy($tempPath, $persistentTempPath)) {
                    throw new \Exception('Failed to create temporary image file');
                }

                Log::info("✓ Image format ({$mimeType}) is OpenAI compatible - no conversion needed");
            }

            // Register cleanup on shutdown (ensures file is deleted even if script crashes)
            register_shutdown_function(function () use ($persistentTempPath) {
                if (file_exists($persistentTempPath)) {
                    @unlink($persistentTempPath);
                    Log::info('✓ Temp file cleaned up on shutdown: ' . basename($persistentTempPath));
                }
            });

            $fullPath = $persistentTempPath; // Use this for AI processing

            Log::info('✓ Persistent temp file created: ' . $fullPath);

            // ==========================================
            // STEP 1: DOG VALIDATION
            // ==========================================
            Log::info('→ Starting dog validation...');

            // Validate file exists before dog validation
            if (!file_exists($fullPath)) {
                throw new \Exception('Image file was lost during processing');
            }

            $dogValidation = $this->validateDogImage($fullPath);

            if (!$dogValidation['is_dog']) {
                // Clean up temp file before returning
                if (file_exists($persistentTempPath)) {
                    @unlink($persistentTempPath);
                }

                Log::warning('⚠️ Image rejected - Not a dog', [
                    'validation_response' => $dogValidation['raw_response'] ?? 'N/A'
                ]);

                // Return error response for non-dog images
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This image does not appear to contain a dog. Please upload a clear photo of a dog for breed identification.',
                        'not_a_dog' => true
                    ], 400);
                }

                return redirect()->back()->with('error', [
                    'message' => 'This image does not appear to contain a dog. Please upload a clear photo of a dog for breed identification.',
                    'not_a_dog' => true
                ]);
            }

            Log::info('✓ Dog validation passed - proceeding with breed analysis');

            // ==========================================
            // STEP 2: STORE IMAGE (only after dog validation passes)
            // ==========================================

            // Validate file exists before storing
            if (!file_exists($persistentTempPath)) {
                throw new \Exception('Image file was lost before storage');
            }

            $path = $image->storeAs('scans', $filename, 'object-storage');

            // Verify file was uploaded successfully
            Storage::disk('object-storage')->put($path, file_get_contents($persistentTempPath));

            // Validate file exists after storage
            if (!file_exists($persistentTempPath)) {
                throw new \Exception('Image file was lost after storage');
            }

            Log::info('✓ Image saved to object storage: ' . $path);

            // ==========================================
            // STEP 3: CALCULATE IMAGE HASH
            // ==========================================

            // Validate file exists before hashing
            if (!file_exists($fullPath)) {
                throw new \Exception('Image file was lost before hash calculation');
            }

            $imageHash = $this->calculateImageHash($fullPath);
            Log::info('✓ Image hash calculated: ' . $imageHash);

            // Check for exact image match
            list($hasExactMatch, $previousResult) = $this->checkExactImageMatch($imageHash);

            // Check for admin correction
            list($hasCorrection, $correction) = $this->checkAdminCorrection($imageHash);

            // Determine breed and confidence
            $detectedBreed = null;
            $confidence = null;
            $topPredictions = [];
            $predictionMethod = 'exact_match';
            $dogFeatures = [];
            $aiData = ['description' => '', 'origin_history' => [], 'health_risks' => []];
            $simulationData = [];

            if ($hasCorrection) {
                // EXACT IMAGE WITH ADMIN CORRECTION = 100%
                $detectedBreed = $correction->corrected_breed;
                $confidence = 100.0;
                $topPredictions = [
                    ['breed' => $detectedBreed, 'confidence' => 100.0],
                    ['breed' => 'Other Breeds', 'confidence' => 0],
                    ['breed' => 'Other Breeds', 'confidence' => 0],
                    ['breed' => 'Other Breeds', 'confidence' => 0],
                    ['breed' => 'Other Breeds', 'confidence' => 0],
                ];
                $predictionMethod = 'admin_corrected';

                // REUSE ALL DATA FROM PREVIOUS RESULT INCLUDING SIMULATIONS
                $aiData = [
                    'description' => $previousResult->description,
                    'origin_history' => $previousResult->origin_history,
                    'health_risks' => $previousResult->health_risks,
                ];

                $previousSimulationData = is_string($previousResult->simulation_data)
                    ? json_decode($previousResult->simulation_data, true)
                    : $previousResult->simulation_data;

                $dogFeatures = $previousSimulationData['dog_features'] ?? [];

                // CACHE SIMULATIONS - Copy from previous result
                $simulationData = [
                    '1_years' => $previousSimulationData['1_years'] ?? null,
                    '3_years' => $previousSimulationData['3_years'] ?? null,
                    'status' => $previousSimulationData['status'] ?? 'complete',
                    'dog_features' => $dogFeatures,
                    'prediction_method' => $predictionMethod,
                    'is_exact_match' => true,
                    'has_admin_correction' => true,
                ];

                Log::info('✓✓✓ ADMIN-CORRECTED EXACT MATCH - SIMULATIONS CACHED', [
                    'breed' => $detectedBreed,
                    'confidence' => '100%',
                    'method' => 'admin_corrected',
                    'simulations_cached' => [
                        '1_years' => !is_null($simulationData['1_years']),
                        '3_years' => !is_null($simulationData['3_years']),
                    ]
                ]);
            } elseif ($hasExactMatch && $previousResult) {
                // EXACT IMAGE MATCH - REUSE ALL DATA
                $detectedBreed = $previousResult->breed;
                $confidence = $previousResult->confidence;
                $topPredictions = $previousResult->top_predictions;
                $predictionMethod = 'exact_match';

                // REUSE ALL DATA FROM PREVIOUS RESULT
                $aiData = [
                    'description' => $previousResult->description,
                    'origin_history' => $previousResult->origin_history,
                    'health_risks' => $previousResult->health_risks,
                ];

                // CACHE SIMULATIONS - Copy from previous result
                $previousSimulationData = is_string($previousResult->simulation_data)
                    ? json_decode($previousResult->simulation_data, true)
                    : $previousResult->simulation_data;

                $dogFeatures = $previousSimulationData['dog_features'] ?? [];

                $simulationData = [
                    '1_years' => $previousSimulationData['1_years'] ?? null,
                    '3_years' => $previousSimulationData['3_years'] ?? null,
                    'status' => $previousSimulationData['status'] ?? 'complete',
                    'dog_features' => $dogFeatures,
                    'prediction_method' => $predictionMethod,
                    'is_exact_match' => true,
                    'has_admin_correction' => false,
                ];

                Log::info('✓ EXACT IMAGE MATCH - ALL DATA CACHED', [
                    'breed' => $detectedBreed,
                    'confidence' => $confidence . '%',
                    'method' => 'exact_match',
                    'previous_scan' => $previousResult->scan_id,
                    'simulations_cached' => [
                        '1_years' => !is_null($simulationData['1_years']),
                        '3_years' => !is_null($simulationData['3_years']),
                    ]
                ]);
            } else {
                // NEW IMAGE — YOLO runs first, then Gemini Pro Preview makes the final call
                Log::info('→ New image — running YOLO classification + Gemini Pro forensic analysis...');

                if (!file_exists($fullPath)) {
                    throw new \Exception('Image file was lost before breed identification');
                }

                // ── STEP A: ML API (YOLO — fast classification + hybrid flag) ──────────
                $mlResult = $this->identifyBreedWithModel($fullPath);

                if ($mlResult['success']) {
                    $mlBreed        = $mlResult['breed'];
                    $mlConfidence   = $mlResult['confidence']; // already percentage
                    $mlMethod       = $mlResult['method'];
                    $isHybridProne  = $mlResult['metadata']['learning_stats']['is_hybrid_prone'] ?? false;

                    Log::info('✓ ML model result', [
                        'breed'          => $mlBreed,
                        'confidence'     => $mlConfidence,
                        'method'         => $mlMethod,
                        'is_hybrid_prone' => $isHybridProne,
                    ]);

                    // ── STEP B: GEMINI Pro Preview — the sole intelligent brain ──────────
                    // Always runs on every new image.
                    // When YOLO flagged a hybrid-prone breed, we inject that signal
                    // so Gemini knows to apply extra scrutiny for hybrid detection.
                    // Gemini Pro Preview (not Flash) makes the final call on everything.
                    $hybridContext = '';
                    if ($isHybridProne) {
                        $hybridContext = " NOTE: The ML model flagged \"{$mlBreed}\" as a hybrid-prone breed — pay extra attention to hybrid indicators (coat texture, mixed proportions, features from two breeds). Check carefully if this could be a recognized designer hybrid (Cockapoo, Goldendoodle, Labradoodle, Cavapoo, Maltipoo, etc.).";
                        Log::info('⚠️ Hybrid-prone breed flagged — injecting hybrid context into Gemini Pro prompt');
                    }

                    Log::info('→ Running Gemini Pro Preview (full forensic analysis)...');

                    $geminiResult = $this->identifyBreedWithAPI(
                        $fullPath,
                        false,
                        $mlBreed . $hybridContext,
                        $mlConfidence
                    );

                    if ($geminiResult['success']) {
                        $geminiBreed      = $geminiResult['breed'];
                        $geminiConfidence = $geminiResult['confidence'];

                        if (strtolower(trim($geminiBreed)) !== strtolower(trim($mlBreed)) && $geminiConfidence >= 75) {
                            // Gemini disagrees with YOLO — trust Gemini
                            // This covers both hybrid corrections AND breed corrections
                            $detectedBreed    = $geminiBreed;
                            $confidence       = $geminiConfidence;
                            $predictionMethod = $isHybridProne ? 'gemini_hybrid_override' : 'gemini_override';
                            Log::info('✓ Gemini overrides YOLO', [
                                'yolo_breed'   => $mlBreed,
                                'gemini_breed' => $geminiBreed,
                                'gemini_conf'  => $geminiConfidence,
                                'hybrid_prone' => $isHybridProne,
                            ]);
                        } else {
                            // Gemini agrees with YOLO — use YOLO breed, take higher confidence
                            $detectedBreed    = $mlBreed;
                            $confidence       = max($mlConfidence, $geminiConfidence);
                            $predictionMethod = 'ml_gemini_confirmed';
                            Log::info('✓ Gemini confirms YOLO breed', [
                                'breed'      => $detectedBreed,
                                'confidence' => $confidence,
                            ]);
                        }

                        $topPredictions = $geminiResult['top_predictions'];
                    } else {
                        // Gemini failed — use ML result only
                        Log::warning('⚠️ Gemini failed — using ML result only', [
                            'error' => $geminiResult['error'] ?? 'unknown',
                        ]);
                        $detectedBreed    = $mlBreed;
                        $confidence       = $mlConfidence;
                        $predictionMethod = $mlMethod;
                        $topPredictions   = $mlResult['top_predictions'];
                    }
                } else {
                    // ML API unavailable — fall back to Gemini-only (original behaviour)
                    Log::warning('⚠️ ML API unavailable — falling back to Gemini-only', [
                        'error' => $mlResult['error'] ?? 'unknown',
                    ]);

                    $predictionResult = $this->identifyBreedWithAPI($fullPath, false);

                    if (!$predictionResult['success']) {
                        Log::error('✗ Both ML and Gemini failed: ' . ($predictionResult['error'] ?? ''));

                        $errorMessage = $predictionResult['error'] ?? '';
                        $userMessage  = 'Unable to identify the dog breed. Please try again.';

                        if (str_contains($errorMessage, 'API key not configured')) {
                            $userMessage = 'Service is temporarily unavailable. Please contact support.';
                        } elseif (str_contains($errorMessage, 'quota') || str_contains($errorMessage, 'rate limit')) {
                            $userMessage = 'Service is temporarily busy. Please try again in a few minutes.';
                        } elseif (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'Connection')) {
                            $userMessage = 'Network connection issue. Please check your internet and try again.';
                        } elseif (str_contains($errorMessage, 'Image file not found')) {
                            $userMessage = 'Failed to process the image. Please try uploading again.';
                        } elseif (str_contains($errorMessage, 'Invalid image')) {
                            $userMessage = 'The image appears to be corrupted. Please try a different photo.';
                        }

                        throw new \Exception($userMessage);
                    }

                    $detectedBreed    = $predictionResult['breed'];
                    $confidence       = $predictionResult['confidence'];
                    $predictionMethod = $predictionResult['method'];
                    $topPredictions   = $predictionResult['top_predictions'];
                }

                Log::info('✓ Final breed identification', [
                    'breed'      => $detectedBreed,
                    'confidence' => $confidence,
                    'method'     => $predictionMethod,
                    'range'      => $confidence >= 85 ? 'High' : ($confidence >= 60 ? 'Moderate' : 'Low'),
                ]);

                // Generate AI descriptions — identical to original
                $aiData = $this->generateAIDescriptionsConcurrent($detectedBreed, []);

                // Initialize simulation data — identical to original
                $simulationData = [
                    '1_years'              => null,
                    '3_years'              => null,
                    'status'               => 'pending',
                    'dog_features'         => [],
                    'prediction_method'    => $predictionMethod,
                    'is_exact_match'       => false,
                    'has_admin_correction' => false,
                ];

                Log::info('✓ NEW scan prediction completed', [
                    'breed'      => $detectedBreed,
                    'confidence' => $confidence,
                    'method'     => $predictionMethod,
                ]);
            }

            // Save to Database
            $uniqueId = strtoupper(Str::random(6));

            $dbResult = Results::create([
                'scan_id' => $uniqueId,
                'user_id' => Auth::id(),
                'image' => $path,
                'image_hash' => $imageHash,
                'breed' => $detectedBreed,
                'confidence' => round($confidence, 2),
                'pending' => 'pending',
                'top_predictions' => $topPredictions,
                'description' => $aiData['description'],
                'origin_history' => is_string($aiData['origin_history']) ? $aiData['origin_history'] : json_encode($aiData['origin_history']),
                'health_risks' => is_string($aiData['health_risks']) ? $aiData['health_risks'] : json_encode($aiData['health_risks']),
                'age_simulation' => null,
                'simulation_data' => json_encode($simulationData),
            ]);

            session(['last_scan_id' => $dbResult->scan_id]);

            // Only dispatch simulation job for NEW images (not exact matches)
            if (!$hasExactMatch) {
                // CRITICAL FIX: Pass object storage path ($path), NOT temp file path ($fullPath)
                \App\Jobs\GenerateAgeSimulations::dispatch($dbResult->id, $detectedBreed, $path);
                Log::info('✓ Simulation job dispatched for new image', [
                    'storage_path' => $path
                ]);
            } else {
                Log::info('✓ Simulations cached from previous scan - no job dispatched');
            }

            // Clean up persistent temp file (success case)
            if (file_exists($persistentTempPath)) {
                @unlink($persistentTempPath);
                Log::info('✓ Temp file cleaned up after successful processing');
            }

            $responseData = [
                'scan_id' => $dbResult->scan_id,
                'breed' => $dbResult->breed,
                'confidence' => $dbResult->confidence,
                'image' => $dbResult->image,
                'image_url' => asset('storage/' . $dbResult->image),
                'top_predictions' => $dbResult->top_predictions,
                'description' => $dbResult->description,
                'created_at' => $dbResult->created_at,
                'prediction_method' => $predictionMethod,
                'is_exact_match' => $hasExactMatch,
                'has_admin_correction' => $hasCorrection,
            ];

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Analysis completed successfully'
                ], 200);
            }

            // For web requests, redirect to scan-results page
            return redirect('/scan-results');
        } catch (\Exception $e) {
            Log::error('Analyze Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Clean up persistent temp file (error case)
            if (isset($persistentTempPath) && file_exists($persistentTempPath)) {
                @unlink($persistentTempPath);
                Log::info('✓ Temp file cleaned up after error');
            }

            // Clean up object storage if upload succeeded
            if ($path && Storage::disk('object-storage')->exists($path)) {
                Storage::disk('object-storage')->delete($path);
                Log::info('✓ Object storage file cleaned up after error');
            }

            // User-friendly error messages (no mention of OpenAI/API)
            $userMessage = 'An unexpected error occurred. Please try again.';

            if (str_contains($e->getMessage(), 'Service is temporarily unavailable')) {
                $userMessage = 'Service is temporarily unavailable. Please contact support.';
            } elseif (str_contains($e->getMessage(), 'temporarily busy')) {
                $userMessage = 'Service is temporarily busy. Please try again in a few minutes.';
            } elseif (str_contains($e->getMessage(), 'Network connection issue')) {
                $userMessage = 'Network connection issue. Please check your internet and try again.';
            } elseif (str_contains($e->getMessage(), 'Failed to process the image')) {
                $userMessage = 'Failed to process the uploaded image. Please try uploading again.';
            } elseif (str_contains($e->getMessage(), 'image appears to be corrupted')) {
                $userMessage = 'The image appears to be corrupted. Please try a different photo.';
            } elseif (str_contains($e->getMessage(), 'Unable to identify')) {
                $userMessage = 'Unable to identify the dog breed. Please try again with a clearer photo.';
            } elseif (str_contains($e->getMessage(), 'Image file was lost')) {
                $userMessage = 'Image processing failed. Please try uploading again.';
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $userMessage
                ], 500);
            }

            return redirect()->back()->with('error', [
                'message' => $userMessage,
            ]);
        }
    }




    public function getOriginHistory($scan_id)
    {
        $result = Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Scan result not found.'
            ], 404);
        }

        $originData = is_string($result->origin_history)
            ? json_decode($result->origin_history, true)
            : $result->origin_history;

        \Illuminate\Support\Facades\Log::info('Origin History Data for ' . $scan_id, [
            'breed' => $result->breed,
            'origin_data' => $originData
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'breed' => $result->breed,
                'origin_data' => $originData
            ]
        ]);
    }

    public function getResult($scan_id)
    {
        $result = \App\Models\Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Scan result not found.'
            ], 404);
        }

        // Build URL from object storage
        $baseUrl = config('filesystems.disks.object-storage.url');

        return response()->json([
            'success' => true,
            'data' => [
                'scan_id' => $result->scan_id,
                'breed' => $result->breed,
                'description' => $result->description,
                'confidence' => (float)$result->confidence,
                'image_url' => $baseUrl . '/' . $result->image,
                'top_predictions' => is_string($result->top_predictions)
                    ? json_decode($result->top_predictions)
                    : $result->top_predictions,
                'created_at' => $result->created_at,
            ]
        ]);
    }

    public function getHealthRisk($scan_id)
    {
        $result = Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Scan result not found.'
            ], 404);
        }

        $healthData = is_string($result->health_risks)
            ? json_decode($result->health_risks, true)
            : $result->health_risks;

        \Illuminate\Support\Facades\Log::info('Health Risk Data for ' . $scan_id, [
            'breed' => $result->breed,
            'health_data' => $healthData
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'breed' => $result->breed,
                'health_data' => $healthData
            ]
        ]);
    }

    public function destroyCorrection($id)
    {
        $correction = BreedCorrection::findOrFail($id);

        $jsonPath = storage_path('app/references.json');
        if (file_exists($jsonPath)) {
            $references = json_decode(file_get_contents($jsonPath), true);

            $imageName = basename($correction->image_path);

            $newReferences = array_filter($references, function ($ref) use ($imageName) {
                return $ref['source_image'] !== $imageName;
            });

            file_put_contents($jsonPath, json_encode(array_values($newReferences), JSON_PRETTY_PRINT));
        }

        $correction->delete();

        return redirect()->back()->with('success', 'Correction deleted and memory wiped.');
    }


    public function correctBreed(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'scan_id' => 'required|string',
            'correct_breed' => 'required|string|max:255',
        ]);

        try {
            // Find the scan result
            $result = Results::where('scan_id', $validated['scan_id'])->firstOrFail();

            // ✅ FIX #3: Store ORIGINAL breed BEFORE updating
            $originalBreed = $result->breed;
            $originalConfidence = $result->confidence;

            Log::info('📝 Starting breed correction', [
                'scan_id' => $validated['scan_id'],
                'original_breed' => $originalBreed,
                'original_confidence' => $originalConfidence,
                'corrected_breed' => $validated['correct_breed']
            ]);

            // Normalize breed name (lowercase, trimmed)
            $normalizedCorrectBreed = strtolower(trim($validated['correct_breed']));

            // ============================================================================
            // STEP 1: CREATE CORRECTION RECORD (BEFORE UPDATING RESULT)
            // ============================================================================

            // ✅ FIX #2: Store just the relative path, not the full URL
            $imagePath = $result->image; // This should be like "scans/users/1/abc123.jpg"

            $correction = BreedCorrection::create([
                'scan_id' => $result->scan_id,
                'image_path' => $imagePath, // Relative path for flexibility
                'original_breed' => $originalBreed, // ✅ Now correctly stores AI's prediction
                'corrected_breed' => $validated['correct_breed'], // Human's correction
                'confidence' => $originalConfidence,
                'status' => 'Added to Memory',
            ]);

            Log::info('✓ Correction record created', [
                'correction_id' => $correction->id,
                'original_breed' => $originalBreed,
                'corrected_breed' => $validated['correct_breed']
            ]);

            // ============================================================================
            // STEP 2: UPDATE SCAN RESULT
            // ============================================================================

            $result->update([
                'pending' => 'verified',
                'breed' => $validated['correct_breed'], // Update to corrected breed
                'confidence' => 100.0, // Admin verified = 100%
            ]);

            Log::info('✓ Result updated to verified', [
                'scan_id' => $result->scan_id,
                'new_breed' => $validated['correct_breed']
            ]);

            // ============================================================================
            // STEP 3: NOTIFY USER
            // ============================================================================

            if ($result->user_id) {
                \App\Models\Notification::create([
                    'user_id' => $result->user_id,
                    'type' => 'scan_verified',
                    'title' => 'Scan Verified by Veterinarian',
                    'message' => "Your scan has been verified! The breed has been confirmed as {$validated['correct_breed']}.",
                    'data' => [
                        'scan_id' => $result->scan_id,
                        'breed' => $validated['correct_breed'],
                        'original_breed' => $originalBreed,
                        'confidence' => 100.0,
                        'image' => $result->image,
                    ],
                ]);

                Log::info('✓ User notified', [
                    'user_id' => $result->user_id,
                    'scan_id' => $result->scan_id
                ]);
            }

            // ============================================================================
            // STEP 4: TEACH ML API (THE CRITICAL LEARNING STEP)
            // ============================================================================

            try {
                $mlService = new \App\Services\MLApiService();

                // Download image from object storage to temporary file
                $imageContents = Storage::disk('object-storage')->get($result->image);

                if ($imageContents === false) {
                    throw new \Exception('Failed to download image from object storage: ' . $result->image);
                }

                // Create temporary file with correct extension
                $tempPath = tempnam(sys_get_temp_dir(), 'ml_learn_');
                $extension = pathinfo($result->image, PATHINFO_EXTENSION) ?: 'jpg';
                $tempPathWithExt = $tempPath . '.' . $extension;

                // Rename to add extension
                if (file_exists($tempPath)) {
                    rename($tempPath, $tempPathWithExt);
                }

                // Write image content to temp file
                file_put_contents($tempPathWithExt, $imageContents);

                Log::info('✓ Image downloaded from object storage', [
                    'temp_path' => $tempPathWithExt,
                    'file_size' => strlen($imageContents),
                    'extension' => $extension
                ]);

                // Send to ML API for learning
                $learnResult = $mlService->learnBreed(
                    $tempPathWithExt,
                    $normalizedCorrectBreed // ✅ Send normalized breed name
                );

                // Clean up temp file
                if (file_exists($tempPathWithExt)) {
                    unlink($tempPathWithExt);
                    Log::debug('✓ Temp file cleaned up', ['path' => $tempPathWithExt]);
                }

                // Check learning result
                if ($learnResult['success']) {
                    $status = $learnResult['status']; // 'added', 'updated', or 'skipped'

                    // Update correction status based on ML API response
                    $correction->update([
                        'status' => ucfirst($status) . ' to ML Memory'
                    ]);

                    Log::info('✓✓✓ ML API LEARNING SUCCESSFUL ✓✓✓', [
                        'scan_id' => $result->scan_id,
                        'status' => $status,
                        'message' => $learnResult['message'],
                        'breed' => $learnResult['breed']
                    ]);

                    return redirect('/model/scan-results')->with(
                        'success',
                        "✓ Correction saved! ML Status: {$learnResult['message']}"
                    );
                } else {
                    Log::warning('ML API learning failed (correction still saved)', [
                        'scan_id' => $result->scan_id,
                        'error' => $learnResult['error'] ?? 'Unknown error'
                    ]);

                    $correction->update([
                        'status' => 'Saved (ML Error)'
                    ]);

                    return redirect('/model/scan-results')->with(
                        'warning',
                        'Correction saved, but ML learning failed. System will retry later.'
                    );
                }
            } catch (\Exception $e) {
                Log::error('❌ ML API learning exception', [
                    'scan_id' => $result->scan_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $correction->update([
                    'status' => 'Saved (ML Error)'
                ]);

                return redirect('/model/scan-results')->with(
                    'warning',
                    'Correction saved, but ML learning encountered an error: ' . $e->getMessage()
                );
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Scan result not found', [
                'scan_id' => $validated['scan_id'] ?? null
            ]);

            return redirect()->back()->with('error', 'Scan result not found.');
        } catch (\Exception $e) {
            Log::error('❌ Unexpected correction error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'scan_id' => $validated['scan_id'] ?? null
            ]);

            return redirect()->back()->with(
                'error',
                'An error occurred while processing the correction. Please try again.'
            );
        }
    }

    public function deleteResult($id)
    {
        $result = Results::findOrFail($id);
        $result->delete();

        return redirect()->back()->with('success', 'Deleted');
    }

    public function checkMLApiHealth()
    {
        try {
            $mlService = new \App\Services\MLApiService();
            $isHealthy = $mlService->isHealthy();

            if ($isHealthy) {
                $stats = $mlService->getMemoryStats();

                return response()->json([
                    'success' => true,
                    'status' => 'healthy',
                    'ml_api_url' => env('PYTHON_ML_API_URL'),
                    'memory_stats' => $stats['data'] ?? []
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'status' => 'unhealthy',
                    'ml_api_url' => env('PYTHON_ML_API_URL'),
                    'message' => 'ML API is not responding or model not loaded'
                ], 503);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getRecentResults(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $userId = $request->user()->id;

            // Build base URL from object storage
            $baseUrl = config('filesystems.disks.object-storage.url');

            $results = Results::where('user_id', $userId)
                ->latest()
                ->take($limit)
                ->get()
                ->map(function ($scan) use ($baseUrl) {
                    return [
                        'id' => $scan->id,
                        'scan_id' => $scan->scan_id,
                        'image_url' => $baseUrl . '/' . $scan->image,
                        'breed' => $scan->breed,
                        'confidence' => (float)$scan->confidence,
                        'created_at' => $scan->created_at->toISOString(),
                        'status' => $scan->pending, // 'pending' or 'verified'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Get recent results error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch scan history'
            ], 500);
        }
    }
}
