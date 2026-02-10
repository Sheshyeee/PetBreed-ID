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
            Log::info('✓ Image encoded successfully. MIME type: ' . $mimeType);

            $enhancedBreedPrompt = "You are an elite canine geneticist and veterinary morphologist with expertise in identifying over 400 dog breeds, rare breeds, landrace populations, and complex mixed-breed combinations. Your analysis must be thorough, precise, and evidence-based.

═══════════════════════════════════════════════════════════════
PHASE 1: SYSTEMATIC MORPHOLOGICAL ANALYSIS
═══════════════════════════════════════════════════════════════

Conduct a forensic-level examination of every visible feature:

**CRANIAL & FACIAL STRUCTURE:**
- Skull type: Dolichocephalic (long), Mesocephalic (medium), Brachycephalic (short)
- Stop angle: Pronounced, moderate, minimal, absent
- Muzzle ratio: Length vs skull length (1:1, 1:2, 2:3, etc.)
- Muzzle shape: Tapered, blunt, square, pointed
- Nose size and pigmentation
- Jaw structure: Undershot, overshot, scissor bite indicators
- Facial wrinkles or folds present

**EYE CHARACTERISTICS:**
- Shape: Almond, round, oval, triangular
- Size relative to head: Small, medium, large, prominent
- Set: Deep-set, normal, protruding
- Color if visible: Brown, amber, blue, green, heterochromia
- Expression: Alert, gentle, intense, wise

**EAR MORPHOLOGY:**
- Type: Prick/erect, semi-erect, button, rose, drop/pendant, bat
- Set: High, medium, low on skull
- Size: Small, medium, large relative to head
- Leather thickness: Thin, medium, thick
- Carriage: Forward, sideways, back

**BODY ARCHITECTURE:**
- Build: Compact, racy, cobby, rangy, sturdy, refined
- Proportions: Square, slightly long, very long-backed
- Chest: Deep, shallow, barrel, narrow
- Topline: Level, sloping, roached
- Loin: Short and strong, long, weak
- Tuck-up: Pronounced (sighthound), moderate, minimal
- Bone structure: Fine, medium, heavy
- Muscle definition: Lean, athletic, heavily muscled, moderate

**COAT ANALYSIS:**
- Length: Hairless, very short, short, medium, long, very long
- Texture: Smooth, wire/harsh, silky, woolly, corded, curly
- Density: Single coat, double coat, triple coat
- Pattern distribution: Uniform, feathering on legs/tail, ruff on neck
- Undercoat presence: Yes/no

**COLOR & PATTERN GENETICS:**
- Base color: Solid (black, white, brown, red, cream, fawn, blue, etc.)
- Pattern type: 
  * Solid/self-colored
  * Bicolor (specify distribution)
  * Tricolor (specify colors and placement)
  * Sable (hair banding)
  * Brindle (tiger striping)
  * Merle (mottled/dappled)
  * Piebald/parti-color (white patches)
  * Ticking/roan (colored dots on white)
  * Saddle pattern
  * Blanket pattern
  * Points (darker on extremities)
  * Mask (face darkening)
- Specific markings: Blazes, collars, socks, patches, spectacles

**TAIL CHARACTERISTICS:**
- Set: High, medium, low
- Carriage: Sickle, ring/curled, saber, flag, gay/upright, low
- Shape: Straight, curved, kinked, bobbed, screw
- Feathering: None, light, heavy, plumed

**SIZE ESTIMATION:**
- Weight class: Toy (<10 lbs), Small (10-25 lbs), Medium (25-50 lbs), Large (50-90 lbs), Giant (90+ lbs)
- Height estimate: Under 10\", 10-15\", 15-20\", 20-25\", 25\"+ at shoulder
- Body mass index: Slender, ideal, stocky, overweight

**SPECIALIZED FEATURES:**
- Dewclaws: Present/absent, single/double
- Feet: Cat-like, hare-like, webbed
- Gait indicators if visible: Stilted, fluid, bouncy
- Specialized adaptations: Cold weather (thick coat, small ears), hot weather (large ears, short coat), water work (webbing), etc.

═══════════════════════════════════════════════════════════════
PHASE 2: BREED CANDIDATE IDENTIFICATION
═══════════════════════════════════════════════════════════════

Based on your morphological analysis, generate a list of 8-12 possible breed candidates:

**PRIMARY CANDIDATES (3-4 breeds):**
- List breeds that match 80%+ of observed features
- Include both common and RARE breeds if features align
- Do NOT exclude rare breeds just because they're uncommon

**SECONDARY CANDIDATES (3-4 breeds):**
- Breeds matching 60-79% of features
- May have 1-2 significant differences

**TERTIARY CANDIDATES (2-4 breeds):**
- Breeds matching 40-59% of features
- Useful for mixed breed analysis

**MIXED BREED CONSIDERATIONS:**
- Could this be a cross between any 2-3 candidates?
- Are features inconsistent within a single breed standard?
- Are there conflicting type characteristics (e.g., sighthound head + mastiff body)?

═══════════════════════════════════════════════════════════════
PHASE 3: DIFFERENTIAL DIAGNOSIS
═══════════════════════════════════════════════════════════════

For each PRIMARY candidate, perform detailed comparison:

**For Each Candidate, Answer:**

1. **SUPPORTING EVIDENCE** - What features MATCH this breed?
   - List specific features that align with breed standard
   - Include percentages (e.g., \"ear set matches 95%\")

2. **CONTRADICTING EVIDENCE** - What features DON'T MATCH?
   - List specific deviations from breed standard
   - Assess if deviations are within acceptable variation or disqualifying

3. **RARITY ASSESSMENT** - How common is this breed?
   - FCI/AKC recognized: Common, Uncommon, Rare, Very Rare
   - Regional breed: Specify region
   - Landrace/Village dog: Specify population

4. **CONFIDENCE MODIFIERS:**
   - Age variation: Puppies/juveniles may not show full characteristics
   - Grooming: Could grooming obscure/reveal features?
   - Photo quality: Are features clearly visible?
   - Atypical specimen: Could this be non-standard but still purebred?

═══════════════════════════════════════════════════════════════
PHASE 4: MIXED BREED ANALYSIS (If Applicable)
═══════════════════════════════════════════════════════════════

**CRITICAL MIXED BREED INDICATORS:**
- Feature combinations from different breed groups (e.g., terrier head + retriever body)
- Intermediate characteristics not standard to any breed
- Simultaneous presence of conflicting traits
- Lack of breed type consistency

**IF MIXED BREED DETECTED:**
- Identify most likely parent breeds (2-3 breeds)
- Explain which features come from which parent
- Assign contribution percentages (e.g., 60% Labrador, 40% Border Collie)
- Consider F1, F2, or multi-generational mix

**REGIONAL MIXED BREEDS:**
- **Aspin/Asong Pinoy (Philippines):** Medium size, erect/semi-erect ears, short coat, athletic build, often tan/brown/black, curled or sickle tail, primitive features
- **Indian Pariah Dog:** Similar to Basenji, erect ears, wedge head, curled tail, short coat, 15-20\" height
- **African Village Dog:** Variable appearance, survival-adapted features
- **Latin American Street Dog:** Mixed ancestry, often medium-sized, adaptable
- **European Landrace:** Regional variations, working dog ancestry

═══════════════════════════════════════════════════════════════
PHASE 5: CONFIDENCE CALIBRATION
═══════════════════════════════════════════════════════════════

Assign confidence using STRICT criteria:

**95-100% - DEFINITIVE IDENTIFICATION:**
- ALL major breed-specific features present
- NO contradicting features
- Breed-specific unique traits visible (e.g., Rhodesian Ridgeback ridge, Lundehund extra toes)
- Photo quality excellent, multiple angles would confirm
- Example: \"This is unmistakably a purebred Border Collie\"

**85-94% - HIGHLY CONFIDENT:**
- All major features match strongly
- Minor variations within acceptable breed range
- 1-2 features not fully visible but no contradictions
- Example: \"Strong evidence for purebred Australian Shepherd\"

**75-84% - CONFIDENT:**
- Most features match well
- 2-3 minor deviations or unclear features
- Could be purebred or very high-percentage mix
- Example: \"Likely purebred German Shepherd, possibly working line\"

**60-74% - MODERATE CONFIDENCE:**
- Good feature match but some inconsistencies
- Could be purebred with atypical features OR high-percentage mix
- Example: \"Probable Golden Retriever mix or field-bred Golden Retriever\"

**45-59% - LOW CONFIDENCE:**
- Multiple breed influences visible
- Features suggest specific mix but other combinations possible
- Example: \"Likely Husky x German Shepherd mix\"

**30-44% - VERY LOW CONFIDENCE:**
- Multi-generational mix or complex ancestry
- Can identify general type but not specific breeds
- Example: \"Mixed breed with terrier and herding influences\"

**Below 30% - INSUFFICIENT:**
- Photo quality too poor
- Puppy too young to assess adult features
- Extreme grooming obscuring features
- Cannot make reliable identification

═══════════════════════════════════════════════════════════════
PHASE 6: FINAL DETERMINATION & REASONING
═══════════════════════════════════════════════════════════════

**DECISION RULES:**

1. **If confidence ≥85% for single breed → Identify as that purebred**
2. **If confidence 60-84% with contradictions → Consider high-percentage mix**
3. **If multiple breeds score 60-75% → Likely F1 cross of top 2**
4. **If features from 3+ breeds → Multi-generational mix**
5. **If regional landrace features → Use specific designation (Aspin, Pariah, etc.)**
6. **NEVER force-fit into common breed if rare breed matches better**
7. **NEVER misidentify regional dogs as standardized breeds**
8. **BE HONEST about uncertainty - low confidence is better than false confidence**

**RARE BREED PROTOCOL:**
- If a rare breed scores ≥80%, IDENTIFY IT even if uncommon
- Examples: Azawakh, Kai Ken, Norwegian Lundehund, Stabyhoun, Thai Ridgeback
- Provide educational note about breed rarity

**MIXED BREED PROTOCOL:**
- Be specific: \"Labrador Retriever x Border Collie mix\" NOT just \"Mixed Breed\"
- Explain reasoning: \"Shows Labrador head/coat with Border Collie build/tail\"
- If 3+ breeds: \"Mixed breed with primarily Terrier and Hound ancestry\"

═══════════════════════════════════════════════════════════════
OUTPUT FORMAT - STRICT JSON STRUCTURE
═══════════════════════════════════════════════════════════════

{
  \"morphological_analysis\": {
    \"skull_type\": \"mesocephalic/dolichocephalic/brachycephalic\",
    \"ear_type\": \"detailed description\",
    \"body_structure\": \"detailed description\",
    \"coat_features\": \"detailed texture, length, density\",
    \"color_genetics\": \"base color + pattern with genetic terms\",
    \"tail_type\": \"detailed description\",
    \"distinctive_features\": [\"feature 1\", \"feature 2\", \"feature 3\"],
    \"size_category\": \"toy/small/medium/large/giant\",
    \"specialized_adaptations\": \"any working dog features visible\"
  },
  
  \"candidate_breeds\": [
    {
      \"breed\": \"Breed Name\",
      \"match_percentage\": 85,
      \"supporting_features\": [\"feature 1\", \"feature 2\"],
      \"contradicting_features\": [\"feature 1\"],
      \"rarity\": \"common/uncommon/rare/regional\"
    },
    {
      \"breed\": \"Breed Name 2\",
      \"match_percentage\": 72,
      \"supporting_features\": [\"feature 1\", \"feature 2\"],
      \"contradicting_features\": [\"feature 1\", \"feature 2\"],
      \"rarity\": \"common/uncommon/rare/regional\"
    }
  ],
  
  \"breed\": \"FINAL BREED NAME or 'Breed A x Breed B mix' or 'Aspin' or 'Mixed Breed'\",
  
  \"confidence\": 87.5,
  
  \"reasoning\": \"Comprehensive 4-6 sentence explanation synthesizing all evidence. Start with: 'Based on comprehensive morphological analysis...' Include: (1) Key identifying features, (2) Why this breed over others, (3) Any contradictions explained, (4) Confidence justification. Be specific and technical.\",
  
  \"breed_type\": \"purebred\" or \"F1_cross\" or \"multi_generation_mix\" or \"landrace\" or \"regional_street_dog\",
  
  \"genetic_composition\": \"For mixes: '60% Breed A, 40% Breed B' or 'Primary: Breed A, Secondary: Breed B, Breed C' or 'Purebred' or 'Complex multi-generational'\",
  
  \"alternative_possibilities\": [
    {
      \"breed\": \"Alternative 1\",
      \"confidence\": 15.0,
      \"reason\": \"Why this is possible but less likely - specific features\"
    },
    {
      \"breed\": \"Alternative 2\",
      \"confidence\": 8.5,
      \"reason\": \"Why this is possible but less likely - specific features\"
    }
  ],
  
  \"key_identifiers\": [
    \"Most distinctive feature that drives identification\",
    \"Second most important diagnostic feature\",
    \"Third critical feature\"
  ],
  
  \"uncertainty_factors\": [
    \"Any factors reducing confidence (age, photo angle, grooming, etc.)\"
  ],
  
  \"rare_breed_note\": \"If rare breed: 'This is a [Breed], a rare breed from [Region] known for [characteristics]' or null\"
}

═══════════════════════════════════════════════════════════════
CRITICAL INSTRUCTIONS - READ CAREFULLY
═══════════════════════════════════════════════════════════════

✓ PRIORITIZE ACCURACY OVER FAMILIARITY - Rare breed that fits perfectly > Common breed that fits poorly
✓ BE HONEST ABOUT MIXED BREEDS - Don't force pure breed identification when features conflict
✓ USE TECHNICAL TERMINOLOGY - Show your expertise with proper morphological terms
✓ PROVIDE EVIDENCE - Every conclusion must be backed by specific observable features
✓ CONSIDER REGIONAL VARIATIONS - Aspin, Pariah dogs, village dogs are VALID identifications
✓ CALIBRATE CONFIDENCE PROPERLY - High confidence requires high certainty, not just a guess
✓ EXPLAIN CONTRADICTIONS - If features don't perfectly align, explain why in reasoning
✓ MULTI-STAGE THINKING - Don't jump to conclusions, work through the analysis phases
✓ BE HONEST ABOUT UNCERTAINTY - If unsure, give LOW confidence (30-60%) so users know the identification may be wrong

✗ DO NOT default to common breeds when rare breeds match better
✗ DO NOT ignore contradicting evidence
✗ DO NOT give high confidence without strong supporting evidence
✗ DO NOT misidentify street dogs/landrace as standardized pure breeds
✗ DO NOT overlook mixed breed possibilities when features conflict
✗ DO NOT artificially inflate confidence when uncertain

NOW ANALYZE THE IMAGE WITH MAXIMUM PRECISION AND INTELLECTUAL RIGOR.";

            Log::info('✓ Sending request to OpenAI API with enhanced multi-stage prompt...');

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a world-class canine geneticist and morphologist specializing in rare breed identification and complex mixed-breed analysis. You have encyclopedic knowledge of 400+ breeds including regional landrace populations. Your analysis is systematic, evidence-based, and technically precise. You never guess - you analyze methodically. When uncertain, you provide honest low confidence scores to help users understand the identification may be incorrect.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $enhancedBreedPrompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                    'detail' => 'high'
                                ],
                            ],
                        ],
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
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

            // Use the ACTUAL confidence from the API - NO MODIFICATIONS
            $actualConfidence = (float)$apiResult['confidence'];

            Log::info('✓ ENHANCED API breed identification successful', [
                'breed' => $apiResult['breed'],
                'actual_confidence' => $actualConfidence,
                'breed_type' => $apiResult['breed_type'] ?? 'unknown',
                'genetic_composition' => $apiResult['genetic_composition'] ?? 'N/A',
                'rare_breed_note' => $apiResult['rare_breed_note'] ?? null,
                'uncertainty_factors' => $apiResult['uncertainty_factors'] ?? [],
                'reasoning_preview' => substr($apiResult['reasoning'] ?? '', 0, 200)
            ]);

            // Build top predictions array with ONLY REAL BREEDS
            $topPredictions = [];
            $seenBreeds = []; // Track breeds to avoid duplicates

            // Add primary breed first
            $primaryBreed = $apiResult['breed'];
            $topPredictions[] = [
                'breed' => $primaryBreed,
                'confidence' => round($actualConfidence, 2)
            ];
            $seenBreeds[] = strtolower(trim($primaryBreed));

            // Add alternative possibilities (these have actual breed names and confidence scores)
            if (isset($apiResult['alternative_possibilities']) && is_array($apiResult['alternative_possibilities'])) {
                foreach ($apiResult['alternative_possibilities'] as $alt) {
                    if (isset($alt['breed']) && isset($alt['confidence'])) {
                        $breedKey = strtolower(trim($alt['breed']));

                        // Skip duplicates and only add if confidence > 0
                        if (!in_array($breedKey, $seenBreeds) && (float)$alt['confidence'] > 0) {
                            $topPredictions[] = [
                                'breed' => $alt['breed'],
                                'confidence' => round((float)$alt['confidence'], 2)
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
                        $breedKey = strtolower(trim($candidate['breed']));
                        $confidence = (float)($candidate['match_percentage'] ?? $candidate['confidence'] ?? 0);

                        // Only add if not duplicate and has reasonable confidence
                        if (!in_array($breedKey, $seenBreeds) && $confidence > 0) {
                            $topPredictions[] = [
                                'breed' => $candidate['breed'],
                                'confidence' => round($confidence, 2)
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
                'breed' => $apiResult['breed'],
                'confidence' => round($actualConfidence, 2), // Use actual API confidence - NO RANDOMIZATION
                'top_predictions' => $topPredictions, // Return only real predictions (no padding)
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

            // Format response to match existing structure
            return [
                'success' => true,
                'method' => $result['method'], // 'model' or 'memory'
                'breed' => $result['breed'],
                'confidence' => $result['confidence'] * 100, // Convert to percentage
                'top_predictions' => $result['top_predictions'],
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

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a veterinary historian. Output only valid JSON. Be verbose and detailed.'],
                    ['role' => 'user', 'content' => $combinedPrompt]
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 1500,
                'temperature' => 0.3,
            ]);

            $content = $response->choices[0]->message->content;
            $parsed = json_decode($content, true);

            if ($parsed) {
                $aiData['description'] = $parsed['description'] ?? '';
                $aiData['health_risks'] = $parsed['health_risks'] ?? [];
                $aiData['origin_history'] = $parsed['origin_data'] ?? [];
            }

            Log::info('✓ AI descriptions generated successfully');
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
    private function extractDogFeatures($fullPath, $detectedBreed)
    {
        $dogFeatures = [
            'coat_color' => 'brown',
            'coat_pattern' => 'solid',
            'coat_length' => 'medium',
            'estimated_age' => 'young adult',
            'build' => 'medium',
            'distinctive_markings' => 'none',
        ];

        try {
            $imageData = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);

            $visionPrompt = "Analyze this {$detectedBreed} dog image and provide ONLY a JSON response with these exact keys:

{
  \"coat_color\": \"primary color(s) - be specific (e.g., 'golden', 'black and tan', 'white with brown patches', 'brindle')\",
  \"coat_pattern\": \"pattern type (solid, spotted, brindle, merle, parti-color, tuxedo, sable)\",
  \"coat_length\": \"length (short, medium, long, curly)\",
  \"coat_texture\": \"texture description (silky, wiry, fluffy, smooth)\",
  \"estimated_age\": \"age range (puppy, young adult, adult, mature, senior) based on face, eyes, and coat\",
  \"build\": \"body type (lean/athletic, stocky/muscular, compact, large/heavy)\",
  \"distinctive_markings\": \"any unique features (facial markings, chest patches, eyebrow marks, ear color, tail characteristics)\",
  \"ear_type\": \"ear shape (floppy, erect, semi-erect)\",
  \"eye_color\": \"eye color if visible (brown, blue, amber, heterochromic)\",
  \"size_estimate\": \"size category (toy, small, medium, large, giant)\"
}

Be detailed and specific about colors and patterns.";

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $visionPrompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}"]],
                    ],
                ]],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 500,
            ]);

            $features = json_decode($response->choices[0]->message->content, true);
            if ($features) {
                $dogFeatures = array_merge($dogFeatures, $features);
            }
        } catch (\Exception $e) {
            Log::error("Feature extraction failed: " . $e->getMessage());
        }

        return $dogFeatures;
    }

    /**
     * ==========================================
     * MAIN ANALYZE METHOD - OPTIMIZED WITH SIMULATION CACHING
     * ==========================================
     */
    private function validateDogImage($imagePath): array
    {
        try {
            Log::info('🔍 Starting dog validation with OpenAI Vision', [
                'image_path' => $imagePath
            ]);

            // Read image and convert to base64
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analyze this image carefully. Is there a dog visible in this image? Respond with ONLY "YES" if you can clearly see a dog (any breed, puppy or adult), or "NO" if there is no dog, if it\'s a different animal (cat, bird, etc.), or if you\'re uncertain. Be strict - only respond YES if you are confident there is a dog.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}",
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 10,
            ]);

            $answer = trim(strtoupper($response->choices[0]->message->content));
            $isDog = str_contains($answer, 'YES');

            Log::info('✓ OpenAI dog validation complete', [
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
            // Change to ['is_dog' => false] for fail-closed behavior
            return [
                'is_dog' => true,
                'error' => $e->getMessage()
            ];
        }
    }

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
                $dogFeatures = $this->extractDogFeatures($fullPath, $detectedBreed);
                $aiData = $this->generateAIDescriptionsConcurrent($detectedBreed, $dogFeatures);

                // Initialize simulation data for new scan
                $simulationData = [
                    '1_years' => null,
                    '3_years' => null,
                    'status' => 'pending',
                    'dog_features' => $dogFeatures,
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
                \App\Jobs\GenerateAgeSimulations::dispatch($dbResult->id, $detectedBreed, $dogFeatures);
                Log::info('✓ Simulation job dispatched for new image');
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

            // For web requests, redirect back with success message
            return redirect()->back()->with('success', [
                'breed' => $dbResult->breed,
                'confidence' => $dbResult->confidence,
                'top_predictions' => $dbResult->top_predictions,
                'message' => 'Analysis complete!'
            ]);
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
