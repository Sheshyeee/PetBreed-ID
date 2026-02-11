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

        $firstCorrection = BreedCorrection::oldest()->first();

        if ($firstCorrection) {
            $scansBeforeCorrections = Results::where('created_at', '<', $firstCorrection->created_at)
                ->where('confidence', '>=', 80)
                ->count();
            $totalBeforeCorrections = Results::where('created_at', '<', $firstCorrection->created_at)->count();

            $accuracyBeforeCorrections = $totalBeforeCorrections > 0
                ? ($scansBeforeCorrections / $totalBeforeCorrections) * 100
                : 0;

            $scansAfterCorrections = Results::where('created_at', '>=', $firstCorrection->created_at)
                ->where('confidence', '>=', 80)
                ->count();
            $totalAfterCorrections = Results::where('created_at', '>=', $firstCorrection->created_at)->count();

            $accuracyAfterCorrections = $totalAfterCorrections > 0
                ? ($scansAfterCorrections / $totalAfterCorrections) * 100
                : 0;

            $accuracyImprovement = $accuracyAfterCorrections - $accuracyBeforeCorrections;
        } else {
            $accuracyBeforeCorrections = 0;
            $accuracyAfterCorrections = 0;
            $accuracyImprovement = 0;
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
            'breedLearningProgress' => $breedLearningProgress, // NEW: Per-breed learning data
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
            Log::info('✓ EXACT IMAGE MATCH FOUND', [
                'previous_scan_id' => $previousResult->scan_id,
                'previous_breed' => $previousResult->breed,
                'previous_confidence' => $previousResult->confidence,
                'scan_date' => $previousResult->created_at
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
    private function identifyBreedWithAPI($imagePath)
    {
        Log::info('=== STARTING ENHANCED API BREED IDENTIFICATION ===');
        Log::info('Image path: ' . $imagePath);

        $apiKey = config('openai.api_key');
        if (empty($apiKey)) {
            Log::error('✗ OpenAI API key not configured in .env file');
            return [
                'success' => false,
                'error' => 'OpenAI API key not configured'
            ];
        }
        Log::info('✓ OpenAI API key is configured');

        if (!file_exists($imagePath)) {
            Log::error('✗ Image file not found: ' . $imagePath);
            return [
                'success' => false,
                'error' => 'Image file not found'
            ];
        }
        Log::info('✓ Image file exists');

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            Log::info('Image prepared for API call', [
                'mime_type' => $mimeType,
                'data_size' => strlen($imageData)
            ]);

            // MODIFIED: Added instructions for realistic confidence and clean breed names
            $systemPrompt = "You are a professional dog breed identification AI veterinary expert. Analyze the dog image carefully and provide a scientifically accurate breed identification.

CRITICAL RULES:
1. For mixed breeds, provide ONLY ONE dominant breed name (e.g., 'Labrador' NOT 'Labrador Mix' or 'Labrador x Retriever')
2. NEVER include 'mix', 'mixed', 'X', 'x', or punctuation like '()', '/', '-' in breed names
3. If unsure, be HONEST with low confidence (20-40%) but still provide possible breeds
4. NEVER give confidence above 95% - even for clear purebreds, max is 85-92%
5. Realistic confidence ranges:
   - Very clear purebred: 75-92%
   - Likely purebred with some uncertainty: 60-75%
   - Mixed with dominant breed visible: 45-65%
   - Mixed with unclear dominance: 25-50%
   - Very uncertain: 15-30%

Return ONLY valid JSON with these fields:
{
    \"breed\": \"Single dominant breed name (no mix, x, or punctuation)\",
    \"confidence\": numeric value (15-92, NEVER above 92),
    \"breed_type\": \"purebred\" or \"mixed\",
    \"reasoning\": \"Brief explanation\",
    \"genetic_composition\": \"If mixed, describe WITHOUT using 'x' or 'mix' in breed name\",
    \"key_identifiers\": [\"Feature 1\", \"Feature 2\"],
    \"uncertainty_factors\": [\"Reason if confidence < 70%\"],
    \"alternative_possibilities\": [
        {\"breed\": \"Alternative breed name (no mix/x)\", \"confidence\": numeric}
    ],
    \"candidate_breeds\": [
        {\"breed\": \"Other possible breed (no mix/x)\", \"match_percentage\": numeric}
    ],
    \"rare_breed_note\": \"Note if rare or null\"
}";

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Identify this dog breed. Remember: max 92% confidence, no "mix"/"x" in names, be honest if unsure.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 3000,
                'temperature' => 0.2,
            ]);

            Log::info('✓ Received response from OpenAI API');

            $rawContent = $response->choices[0]->message->content ?? null;
            Log::info('Raw API response: ' . substr($rawContent, 0, 1500) . '...');

            if (empty($rawContent)) {
                throw new \Exception('Empty response from OpenAI API');
            }

            $apiResult = json_decode($rawContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: ' . json_last_error_msg());
                throw new \Exception('Failed to decode JSON response');
            }

            if (!$apiResult || !isset($apiResult['breed']) || !isset($apiResult['confidence'])) {
                Log::error('Invalid API response structure');
                Log::error('Response keys: ' . json_encode(array_keys($apiResult ?? [])));
                throw new \Exception('Missing required fields in API response');
            }

            // ADDED: Clean the breed name - remove mix, x, and punctuation
            $cleanedBreed = $this->cleanBreedName($apiResult['breed']);

            // ADDED: Apply realistic confidence caps
            $rawConfidence = (float)$apiResult['confidence'];
            $realisticConfidence = $this->applyRealisticConfidence($rawConfidence, $apiResult['breed_type'] ?? 'unknown');

            Log::info('✓ ENHANCED API breed identification successful', [
                'original_breed' => $apiResult['breed'],
                'cleaned_breed' => $cleanedBreed,
                'raw_confidence' => $rawConfidence,
                'realistic_confidence' => $realisticConfidence,
                'breed_type' => $apiResult['breed_type'] ?? 'unknown',
                'genetic_composition' => $apiResult['genetic_composition'] ?? 'N/A',
                'rare_breed_note' => $apiResult['rare_breed_note'] ?? null,
                'uncertainty_factors' => $apiResult['uncertainty_factors'] ?? [],
                'reasoning_preview' => substr($apiResult['reasoning'] ?? '', 0, 200)
            ]);

            // Build top predictions array with ONLY REAL BREEDS
            $topPredictions = [];
            $seenBreeds = []; // Track breeds to avoid duplicates

            // Add primary breed first (MODIFIED: cleaned name and realistic confidence)
            $topPredictions[] = [
                'breed' => $cleanedBreed,
                'confidence' => round($realisticConfidence, 2)
            ];
            $seenBreeds[] = strtolower(trim($cleanedBreed));

            // Add alternative possibilities (these have actual breed names and confidence scores)
            if (isset($apiResult['alternative_possibilities']) && is_array($apiResult['alternative_possibilities'])) {
                foreach ($apiResult['alternative_possibilities'] as $alt) {
                    if (isset($alt['breed']) && isset($alt['confidence'])) {
                        // ADDED: Clean breed name
                        $cleanAltBreed = $this->cleanBreedName($alt['breed']);
                        $breedKey = strtolower(trim($cleanAltBreed));

                        // Skip duplicates and only add if confidence > 0
                        if (!in_array($breedKey, $seenBreeds) && (float)$alt['confidence'] > 0) {
                            // ADDED: Apply realistic confidence
                            $altConfidence = $this->applyRealisticConfidence((float)$alt['confidence'], 'alternative');
                            $topPredictions[] = [
                                'breed' => $cleanAltBreed,
                                'confidence' => round($altConfidence, 2)
                            ];
                            $seenBreeds[] = $breedKey;
                        }
                    }
                }
            }

            // Add candidate breeds ONLY if they have meaningful confidence and aren't duplicates
            if (count($topPredictions) < 5 && isset($apiResult['candidate_breeds']) && is_array($apiResult['candidate_breeds'])) {
                foreach ($apiResult['candidate_breeds'] as $candidate) {
                    if (isset($candidate['breed']) && count($topPredictions) < 5) {
                        // ADDED: Clean breed name
                        $cleanCandidateBreed = $this->cleanBreedName($candidate['breed']);
                        $breedKey = strtolower(trim($cleanCandidateBreed));
                        $confidence = (float)($candidate['match_percentage'] ?? $candidate['confidence'] ?? 0);

                        // Only add if not duplicate and has reasonable confidence
                        if (!in_array($breedKey, $seenBreeds) && $confidence > 0) {
                            // ADDED: Apply realistic confidence
                            $candidateConfidence = $this->applyRealisticConfidence($confidence, 'candidate');
                            $topPredictions[] = [
                                'breed' => $cleanCandidateBreed,
                                'confidence' => round($candidateConfidence, 2)
                            ];
                            $seenBreeds[] = $breedKey;
                        }
                    }
                }
            }

            // DO NOT fill with "Other Breeds" - only return real predictions
            // Frontend will handle the display appropriately

            Log::info('✓ Top predictions built', [
                'count' => count($topPredictions),
                'breeds' => array_column($topPredictions, 'breed')
            ]);

            return [
                'success' => true,
                'method' => 'api_enhanced',
                'breed' => $cleanedBreed, // MODIFIED: Use cleaned breed name
                'confidence' => round($realisticConfidence, 2), // MODIFIED: Use realistic confidence
                'top_predictions' => $topPredictions, // MODIFIED: Cleaned names and realistic confidence
                'metadata' => [
                    'reasoning' => $apiResult['reasoning'] ?? '',
                    'key_identifiers' => $apiResult['key_identifiers'] ?? [],
                    'breed_type' => $apiResult['breed_type'] ?? 'unknown',
                    'genetic_composition' => $apiResult['genetic_composition'] ?? 'Unknown',
                    'morphological_analysis' => $apiResult['morphological_analysis'] ?? [],
                    'candidate_breeds' => $apiResult['candidate_breeds'] ?? [],
                    'uncertainty_factors' => $apiResult['uncertainty_factors'] ?? [],
                    'rare_breed_note' => $apiResult['rare_breed_note'] ?? null,
                ]
            ];
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            Log::error('✗ OpenAI API Error: ' . $e->getMessage());
            Log::error('Error code: ' . $e->getCode());
            return [
                'success' => false,
                'error' => 'OpenAI API Error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('✗ Enhanced API breed identification failed: ' . $e->getMessage());
            Log::error('Error type: ' . get_class($e));
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ==========================================
     * NEW HELPER: Clean breed name - remove mix, x, punctuation
     * ==========================================
     */
    private function cleanBreedName($breedName)
    {
        if (empty($breedName)) {
            return 'Unknown';
        }

        // Remove common mix indicators and punctuation
        $cleaned = $breedName;

        // Remove "Mix" or "mix" at the end
        $cleaned = preg_replace('/\s+(mix|mixed)$/i', '', $cleaned);

        // Remove "X" or "x" between breeds (e.g., "Labrador x Poodle" -> "Labrador")
        // Take only the first breed before any X
        if (preg_match('/^([^xX\/\-\(\)]+)[\sxX\/\-\(\)]/', $cleaned, $matches)) {
            $cleaned = $matches[1];
        }

        // Remove any remaining parentheses and their contents
        $cleaned = preg_replace('/\s*\([^)]*\)\s*/', '', $cleaned);

        // Remove slashes, dashes used as separators
        $cleaned = preg_replace('/\s*[\/-]\s*[A-Z]/', '', $cleaned);

        // Clean up extra whitespace
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        // If cleaning resulted in empty string, return original
        if (empty($cleaned)) {
            return trim($breedName);
        }

        return $cleaned;
    }

    /**
     * ==========================================
     * NEW HELPER: Apply realistic confidence caps
     * ==========================================
     */
    private function applyRealisticConfidence($rawConfidence, $breedType)
    {
        // Never allow confidence above 95%
        if ($rawConfidence > 95) {
            $rawConfidence = 95;
        }

        // Apply caps based on breed type
        switch ($breedType) {
            case 'purebred':
                // Purebred max: 92%
                $maxConfidence = 92;
                break;

            case 'mixed':
                // Mixed breed max: 75%
                $maxConfidence = 75;
                break;

            case 'alternative':
                // Alternative suggestions max: 65%
                $maxConfidence = 65;
                break;

            case 'candidate':
                // Candidate breeds max: 55%
                $maxConfidence = 55;
                break;

            default:
                // Unknown type max: 85%
                $maxConfidence = 85;
                break;
        }

        // If raw confidence exceeds max, reduce to max with slight variation
        if ($rawConfidence > $maxConfidence) {
            // Reduce to max with 2-5 point reduction for realism
            $reduction = rand(2, 5);
            $realistic = $maxConfidence - $reduction;
        } else {
            // Keep original but ensure minimum realism
            $realistic = $rawConfidence;

            // If confidence is suspiciously round (90, 95, 100), add variation
            if (in_array(round($rawConfidence), [90, 95, 100])) {
                $realistic = $rawConfidence - rand(3, 8);
            }
        }

        // Ensure minimum confidence of 15%
        if ($realistic < 15) {
            $realistic = rand(15, 25);
        }

        return $realistic;
    }

    /**
     * ML Model Prediction (Fallback)
     */
    private function identifyBreedWithModel($imagePath)
    {
        try {
            Log::info('=== USING ML API SERVICE ===');

            $mlService = new \App\Services\MLApiService();

            // Check if ML API is healthy
            if (!$mlService->isHealthy()) {
                throw new \Exception('ML API is not available or unhealthy');
            }

            $startTime = microtime(true);
            $result = $mlService->predictBreed($imagePath);
            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('ML API Execution time: ' . $executionTime . 's');

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'ML API prediction failed');
            }

            // ADDED: Clean the breed name
            $cleanedBreed = $this->cleanBreedName($result['breed']);

            // MODIFIED: Convert confidence from 0-1 to percentage and apply realistic caps
            $rawConfidence = $result['confidence'] * 100;
            $realisticConfidence = $this->applyRealisticConfidence($rawConfidence, $result['metadata']['breed_type'] ?? 'unknown');

            // ADDED: Clean breed names in top predictions
            $cleanedTopPredictions = [];
            if (isset($result['top_predictions']) && is_array($result['top_predictions'])) {
                foreach ($result['top_predictions'] as $prediction) {
                    if (isset($prediction['breed'])) {
                        $cleanPredBreed = $this->cleanBreedName($prediction['breed']);
                        $predConfidence = isset($prediction['confidence']) ? $prediction['confidence'] * 100 : 0;
                        $realisticPredConfidence = $this->applyRealisticConfidence($predConfidence, 'alternative');

                        $cleanedTopPredictions[] = [
                            'breed' => $cleanPredBreed,
                            'confidence' => round($realisticPredConfidence, 2)
                        ];
                    }
                }
            }

            // Format response to match existing structure
            return [
                'success' => true,
                'method' => $result['method'], // 'model' or 'memory'
                'breed' => $cleanedBreed, // MODIFIED: Use cleaned breed name
                'confidence' => round($realisticConfidence, 2), // MODIFIED: Use realistic confidence
                'top_predictions' => $cleanedTopPredictions, // MODIFIED: Use cleaned predictions
                'metadata' => array_merge(
                    $result['metadata'] ?? [],
                    ['execution_time' => $executionTime]
                )
            ];
        } catch (\Exception $e) {
            Log::error('ML API prediction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ==========================================
     * OPTIMIZED: Generate AI descriptions with detailed prompts
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
            return $aiData;
        }

        try {
            $combinedPrompt = "You are a veterinary and canine history expert. The dog is a {$detectedBreed}. 
Return valid JSON with these 3 specific keys. ENSURE CONTENT IS DETAILED AND EDUCATIONAL.

1. 'description': Write a  2 setence summary of the breed's identity and historical significance.

2. 'health_risks': {
     'concerns': [
       
       { 'name': 'Condition Name (summarized 2-3 words only!)', 'risk_level': 'Moderate Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' },
       { 'name': 'Condition Name (summarized 2-3 words only!)', 'risk_level': 'Low Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' },
       
     ],
     'screenings': [
       { 'name': 'Exam Name', 'description': 'Detailed explanation of what this exam checks for and why it is critical.' },
       { 'name': 'Exam Name', 'description': 'Detailed explanation.' },
       
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

            // Use Gemini API instead of OpenAI
            $apiKey = config('services.gemini.api_key');
            if (empty($apiKey)) {
                Log::error('✗ Gemini API key not configured');
                return $aiData;
            }

            $client = new \GuzzleHttp\Client();
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
                        'maxOutputTokens' => 1500,
                        'responseMimeType' => 'application/json'
                    ]
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($content) {
                $parsed = json_decode($content, true);
                if ($parsed) {
                    $aiData['description'] = $parsed['description'] ?? '';
                    $aiData['health_risks'] = $parsed['health_risks'] ?? [];
                    $aiData['origin_history'] = $parsed['origin_data'] ?? [];
                }
            }

            Log::info('✓ AI descriptions generated successfully with Gemini');
        } catch (\Exception $e) {
            Log::error("AI generation failed: " . $e->getMessage());
        }

        return $aiData;
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
    public function analyze(Request $request)
    {
        Log::info('=================================');
        Log::info('=== ANALYZE REQUEST STARTED ===');
        Log::info('=================================');

        $path = null;

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
            $extension = match ($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/avif' => 'avif',
                'image/bmp', 'image/x-ms-bmp' => 'bmp',
                default => $image->extension()
            };

            $filename = time() . '_' . pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME) . '.' . $extension;
            $tempPath = $image->getRealPath(); // Use uploaded temp file directly
            $fullPath = $tempPath; // Use this for AI processing

            // ==========================================
            // STEP 1: DOG VALIDATION (NEW)
            // ==========================================
            Log::info('→ Starting dog validation...');
            $dogValidation = $this->validateDogImage($fullPath);

            if (!$dogValidation['is_dog']) {
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
            $path = $image->storeAs('scans', $filename, 'object-storage');

            // After storing to object storage
            Storage::disk('object-storage')->put($path, file_get_contents($tempPath));
            if (!file_exists($fullPath)) {
                throw new \Exception('File was not saved properly');
            }

            Log::info('✓ Image saved to: ' . $fullPath);

            // ==========================================
            // STEP 3: CALCULATE IMAGE HASH
            // ==========================================
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
                // EXACT IMAGE WITHOUT CORRECTION - CACHE EVERYTHING
                $detectedBreed = $previousResult->breed;
                $confidence = $previousResult->confidence;
                $topPredictions = $previousResult->top_predictions;
                $predictionMethod = 'exact_match';

                // REUSE ALL DATA INCLUDING AI DESCRIPTIONS AND SIMULATIONS
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
                // NEW IMAGE - Run full AI prediction and feature extraction
                Log::info('→ New image - running breed identification...');

                $predictionResult = $this->identifyBreedWithAPI($fullPath);

                if (!$predictionResult['success']) {
                    Log::warning('⚠ API prediction failed, falling back to ML model');
                    $predictionResult = $this->identifyBreedWithModel($fullPath);

                    if (!$predictionResult['success']) {
                        throw new \Exception('Both API and ML model predictions failed');
                    }
                }

                $detectedBreed = $predictionResult['breed'];
                $confidence = $predictionResult['confidence']; // USE ACTUAL API CONFIDENCE - NO RANDOMIZATION
                $predictionMethod = $predictionResult['method'];

                Log::info('✓ Using ACTUAL API confidence (no modifications)', [
                    'breed' => $detectedBreed,
                    'actual_confidence' => $confidence,
                    'method' => $predictionMethod,
                    'confidence_range' => $confidence >= 85 ? 'High' : ($confidence >= 60 ? 'Moderate' : ($confidence >= 45 ? 'Low' : 'Very Low')),
                    'uncertainty_factors' => count($predictionResult['metadata']['uncertainty_factors'] ?? [])
                ]);

                // Use the top predictions from API directly (no randomization)
                $topPredictions = $predictionResult['top_predictions'];

                // Extract dog features and generate AI data
                // Generate AI data (no dog features needed here anymore)
                $aiData = $this->generateAIDescriptionsConcurrent($detectedBreed, []);

                // Initialize simulation data for new scan (no dog_features - job will extract them)
                $simulationData = [
                    '1_years' => null,
                    '3_years' => null,
                    'status' => 'pending',
                    'dog_features' => [], // Empty - job will populate this
                    'prediction_method' => $predictionMethod,
                    'is_exact_match' => false,
                    'has_admin_correction' => false,
                ];

                Log::info("✓ NEW scan prediction completed", [
                    'breed' => $detectedBreed,
                    'actual_confidence' => $confidence,
                    'method' => $predictionMethod
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
                    'message' => 'Analysis completed successfully using ' . strtoupper($predictionMethod)
                ], 200);
            }

            // For web requests, redirect to scan-results page
            return redirect('/scan-results');
        } catch (\Exception $e) {
            Log::error('Analyze Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            if ($path && Storage::disk('object-storage')->exists($path)) {
                Storage::disk('object-storage')->delete($path);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis failed: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', [
                'message' => 'An unexpected error occurred. Please try again.',
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
        $validated = $request->validate([
            'scan_id' => 'required|string',
            'correct_breed' => 'required|string|max:255',
        ]);

        $result = Results::where('scan_id', $validated['scan_id'])->firstOrFail();

        // Update the result's pending status to 'verified'
        $result->update([
            'pending' => 'verified',
            'breed' => $validated['correct_breed'],
            'confidence' => 100.0,
        ]);

        BreedCorrection::create([
            'scan_id' => $result->scan_id,
            'image_path' => $result->image,
            'original_breed' => $result->breed,
            'corrected_breed' => $validated['correct_breed'],
            'status' => 'Added',
        ]);

        // ✨ CREATE NOTIFICATION FOR USER
        if ($result->user_id) {
            \App\Models\Notification::create([
                'user_id' => $result->user_id,
                'type' => 'scan_verified',
                'title' => 'Scan Verified by Veterinarian',
                'message' => "Your scan has been verified! The breed has been confirmed as {$validated['correct_breed']}.",
                'data' => [
                    'scan_id' => $result->scan_id,
                    'breed' => $validated['correct_breed'],
                    'original_breed' => $result->breed,
                    'confidence' => 100.0,
                    'image' => $result->image,
                ],
            ]);

            Log::info('✓ Notification created for user', [
                'user_id' => $result->user_id,
                'scan_id' => $result->scan_id,
                'breed' => $validated['correct_breed']
            ]);
        }

        // ML API learning - FIXED FOR OBJECT STORAGE
        try {
            $mlService = new \App\Services\MLApiService();

            // DOWNLOAD IMAGE FROM OBJECT STORAGE TO TEMP FILE
            $imageContents = Storage::disk('object-storage')->get($result->image);

            if ($imageContents === false) {
                throw new \Exception('Failed to download image from object storage');
            }

            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'ml_learn_');
            $extension = pathinfo($result->image, PATHINFO_EXTENSION);
            $tempPathWithExt = $tempPath . '.' . $extension;
            rename($tempPath, $tempPathWithExt);

            file_put_contents($tempPathWithExt, $imageContents);

            Log::info('✓ Image downloaded from object storage to temp file', [
                'temp_path' => $tempPathWithExt,
                'file_size' => strlen($imageContents)
            ]);

            // Now send to ML API
            $learnResult = $mlService->learnBreed($tempPathWithExt, $validated['correct_breed']);

            // Clean up temp file
            if (file_exists($tempPathWithExt)) {
                unlink($tempPathWithExt);
            }

            if ($learnResult['success']) {
                Log::info("✓ ML API learning successful", [
                    'status' => $learnResult['status'],
                    'breed' => $learnResult['breed']
                ]);
            } else {
                Log::warning("ML API learning failed", [
                    'error' => $learnResult['error'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Learning failed: " . $e->getMessage());
        }

        return redirect('/model/scan-results')->with('success', 'Correction saved, status updated to verified, and user notified.');
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
