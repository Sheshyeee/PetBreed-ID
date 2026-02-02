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
    public function dashboard()
    {
        $results = Results::latest()->take(6)->get();

        $correctedBreed = BreedCorrection::get();
        $correctedBreedCount = $correctedBreed->count();
        $result = Results::get();
        $resultCount = $result->count();

        $lowConfidenceCount = $result->where('confidence', '<=', 40)->count();
        $highConfidenceCount = $result->where('confidence', '>=', 41)->count();

        // ==========================================
        // IMPROVED REAL LEARNING METRICS
        // ==========================================

        $oneWeekAgo = Carbon::now()->subDays(7);
        $twoWeeksAgo = Carbon::now()->subDays(14);
        $oneMonthAgo = Carbon::now()->subDays(30);

        // 1. Memory Bank Count (from references.json)
        $jsonPath = storage_path('app/references.json');
        $memoryCount = 0;
        $uniqueBreeds = [];
        if (file_exists($jsonPath)) {
            $references = json_decode(file_get_contents($jsonPath), true);
            if (is_array($references)) {
                $memoryCount = count($references);
                $uniqueBreeds = array_unique(array_column($references, 'label'));
            }
        }

        // 2. Recent Corrections (last 7 days)
        $recentCorrectionsCount = BreedCorrection::where('created_at', '>=', $oneWeekAgo)->count();

        // 3. REAL METRIC: Memory-Assisted Scans
        // Count scans where memory actually helped (is_memory_match = true in results)
        $currentWeekResults = Results::where('created_at', '>=', $oneWeekAgo)->get();

        // Parse simulation_data to check if memory was used
        $memoryAssistedScans = 0;
        foreach ($currentWeekResults as $scan) {
            // Check if this scan used memory (you may need to add a flag in your Results table)
            // For now, we'll check if there's a matching correction
            $hasCorrection = BreedCorrection::where('scan_id', $scan->scan_id)->exists();
            if ($hasCorrection) {
                $memoryAssistedScans++;
            }
        }

        $weeklyScans = $currentWeekResults->count();
        $memoryHitRate = $weeklyScans > 0 ? ($memoryAssistedScans / $weeklyScans) * 100 : 0;

        // 4. REAL METRIC: Before vs After Correction Accuracy
        // Compare scans BEFORE first correction vs AFTER corrections started
        $firstCorrection = BreedCorrection::oldest()->first();

        if ($firstCorrection) {
            // Scans BEFORE any corrections
            $scansBeforeCorrections = Results::where('created_at', '<', $firstCorrection->created_at)
                ->where('confidence', '>=', 80)
                ->count();
            $totalBeforeCorrections = Results::where('created_at', '<', $firstCorrection->created_at)->count();

            $accuracyBeforeCorrections = $totalBeforeCorrections > 0
                ? ($scansBeforeCorrections / $totalBeforeCorrections) * 100
                : 0;

            // Scans AFTER corrections started
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

        // 5. REAL METRIC: Average Confidence (Current Week vs Previous Week)
        $avgConfidence = $currentWeekResults->avg('confidence') ?? 0;

        $previousWeekResults = Results::where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $oneWeekAgo)->get();
        $previousAvgConfidence = $previousWeekResults->avg('confidence') ?? 0;

        $confidenceTrend = 0;
        if ($previousAvgConfidence > 0) {
            $confidenceTrend = $avgConfidence - $previousAvgConfidence;
        }

        // 6. REAL METRIC: Breed Coverage
        // What percentage of corrections have we learned?
        $totalCorrections = BreedCorrection::count();
        $breedCoverage = $totalCorrections > 0 ? (count($uniqueBreeds) / $totalCorrections) * 100 : 0;

        // 7. Weekly Trends for Cards
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

        // 8. Track last milestone for update detection
        $lastMilestone = floor($correctedBreedCount / 5) * 5;

        return inertia('dashboard', [
            'results' => $results,
            'correctedBreedCount' => $correctedBreedCount,
            'resultCount' => $resultCount,
            'lowConfidenceCount' => $lowConfidenceCount,
            'highConfidenceCount' => $highConfidenceCount,

            // Weekly trends
            'totalScansWeeklyTrend' => round($totalScansWeeklyTrend, 1),
            'correctedWeeklyTrend' => round($correctedWeeklyTrend, 1),
            'highConfidenceWeeklyTrend' => round($highConfidenceWeeklyTrend, 1),
            'lowConfidenceWeeklyTrend' => round($lowConfidenceWeeklyTrend, 1),

            // IMPROVED learning metrics with descriptions
            'memoryCount' => $memoryCount,
            'uniqueBreedsLearned' => count($uniqueBreeds),
            'recentCorrectionsCount' => $recentCorrectionsCount,
            'avgConfidence' => round($avgConfidence, 2),
            'confidenceTrend' => round($confidenceTrend, 2),
            'memoryHitRate' => round($memoryHitRate, 2),
            'accuracyImprovement' => round($accuracyImprovement, 2),
            'breedCoverage' => round($breedCoverage, 2),

            // Before/After comparison
            'accuracyBeforeCorrections' => round($accuracyBeforeCorrections, 2),
            'accuracyAfterCorrections' => round($accuracyAfterCorrections, 2),

            // Milestone tracking
            'lastCorrectionCount' => $lastMilestone,
        ]);
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

            // Decode simulation_data from JSON
            $simulationData = is_string($result->simulation_data)
                ? json_decode($result->simulation_data, true)
                : $result->simulation_data;

            // Default structure if no simulation data exists
            if (!$simulationData) {
                $simulationData = [
                    '1_years' => null,
                    '3_years' => null,
                    'status' => 'pending'
                ];
            }

            // Build full URLs for images
            $responseData = [
                'breed' => $result->breed,
                'original_image' => asset('storage/' . $result->image),
                'simulations' => [
                    '1_years' => $simulationData['1_years']
                        ? asset('storage/' . $simulationData['1_years'])
                        : null,
                    '3_years' => $simulationData['3_years']
                        ? asset('storage/' . $simulationData['3_years'])
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

    /**
     * Get simulation status for polling (mobile app)
     * GET /api/v1/results/{scan_id}/simulation-status
     */
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

            $simulations = [
                '1_years' => isset($simulationData['1_years']) && $simulationData['1_years']
                    ? asset('storage/' . $simulationData['1_years'])
                    : null,
                '3_years' => isset($simulationData['3_years']) && $simulationData['3_years']
                    ? asset('storage/' . $simulationData['3_years'])
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

        // Start building the query
        $query = Results::whereNotIn('scan_id', $correctedScanIds);

        // Log initial count
        $totalBeforeFilters = $query->count();
        Log::info('Total results before filters: ' . $totalBeforeFilters);

        // Apply filters
        // 1. Min Confidence Filter
        if ($request->has('min_confidence')) {
            $minConfidenceRaw = $request->input('min_confidence');
            Log::info('Min Confidence RAW value: ' . var_export($minConfidenceRaw, true) . ' (Type: ' . gettype($minConfidenceRaw) . ')');

            $minConfidence = floatval($minConfidenceRaw);
            Log::info('Min Confidence CONVERTED: ' . $minConfidence);

            if ($minConfidence > 0) {
                $query->where('confidence', '>=', $minConfidence);
                Log::info('✓ Min confidence filter APPLIED: confidence >= ' . $minConfidence);

                // Count after this filter
                $countAfterConfidence = $query->count();
                Log::info('Results after confidence filter: ' . $countAfterConfidence);
            } else {
                Log::info('✗ Min confidence filter SKIPPED (value is 0)');
            }
        } else {
            Log::info('✗ Min confidence parameter NOT present in request');
        }

        // 2. Status Filter (based on confidence ranges)
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

        // 3. Date Filter
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

        // Log the final SQL query
        Log::info('Final SQL Query: ' . $query->toSql());
        Log::info('Query Bindings: ' . json_encode($query->getBindings()));

        // Get paginated results
        $results = $query->latest()->paginate(10)->appends($request->query());

        Log::info('FINAL Results count: ' . $results->total());
        Log::info('Current page: ' . $results->currentPage());
        Log::info('Per page: ' . $results->perPage());

        // Log sample of results for debugging
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
     * Original analyze method for web (returns redirect)
     */
    public function analyze(Request $request)
    {
        Log::info('=================================');
        Log::info('=== ANALYZE REQUEST STARTED ===');
        Log::info('=================================');

        $path = null;

        try {
            // Validation
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

            // Store file
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
            $path = $image->storeAs('scans', $filename, 'public');
            $fullPath = storage_path('app/public/' . $path);

            if (!file_exists($fullPath)) {
                throw new \Exception('File was not saved properly');
            }

            // Python Execution
            Log::info('=== PYTHON EXECUTION ===');
            $pythonPath = env('PYTHON_PATH', 'python');
            $scriptPath = base_path('ml/predict.py');
            $jsonPath = storage_path('app/references.json');

            if (!file_exists($scriptPath)) {
                throw new \Exception('Prediction script not found at: ' . $scriptPath);
            }

            $command = sprintf('"%s" "%s" "%s" "%s" 2>&1', $pythonPath, $scriptPath, $fullPath, $jsonPath);
            Log::info('Command: ' . $command);

            $startTime = microtime(true);
            $output = shell_exec($command);
            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('Execution time: ' . $executionTime . 's');

            if (empty($output)) {
                throw new \Exception('No output from prediction script');
            }

            // Parse JSON
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonString = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                $result = json_decode($jsonString, true);
            } else {
                $result = json_decode($output, true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON error: ' . json_last_error_msg());
                throw new \Exception('Invalid JSON from prediction script');
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            // Calculate Confidence
            $confidence = $result['confidence'];
            if ($confidence <= 1.0) {
                $confidence = $confidence * 100;
            }

            $topPredictions = [];
            if (isset($result['top_5']) && is_array($result['top_5'])) {
                foreach ($result['top_5'] as $prediction) {
                    $predConfidence = $prediction['confidence'] ?? 0;
                    if ($predConfidence <= 1.0) {
                        $predConfidence = $predConfidence * 100;
                    }
                    $topPredictions[] = [
                        'breed' => $prediction['breed'] ?? 'Unknown',
                        'confidence' => round($predConfidence, 2)
                    ];
                }
            }

            $detectedBreed = $result['breed'];

            // ==========================================
            // NEW: ANALYZE DOG'S VISUAL FEATURES USING GPT VISION
            // ==========================================
            $dogFeatures = [
                'coat_color' => 'unknown',
                'coat_pattern' => 'solid',
                'coat_length' => 'medium',
                'estimated_age' => 'young adult',
                'build' => 'medium',
                'distinctive_markings' => 'none',
            ];

            try {
                Log::info('=== ANALYZING DOG FEATURES WITH GPT VISION ===');

                // Read and encode the image
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

                $visionResponse = OpenAI::chat()->create([
                    'model' => 'gpt-4.1-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $visionPrompt,
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
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 500,
                ]);

                $featuresContent = $visionResponse->choices[0]->message->content;
                $extractedFeatures = json_decode($featuresContent, true);

                if ($extractedFeatures) {
                    $dogFeatures = array_merge($dogFeatures, $extractedFeatures);
                    Log::info('Dog features extracted: ' . json_encode($dogFeatures));
                } else {
                    Log::warning('Failed to parse dog features, using defaults');
                }
            } catch (\Exception $e) {
                Log::error("GPT Vision analysis failed: " . $e->getMessage());
                // Continue with default features
            }

            // Default values
            $aiData = [
                'description' => "Identified as $detectedBreed.",
                'origin_history' => "History unavailable.",
                'health_risks' => [],
            ];

            try {
                if ($detectedBreed !== 'Unknown') {
                    $prompt = "You are a veterinary and canine history expert. The dog is a {$detectedBreed}. 
Return valid JSON with these 3 specific keys. ENSURE CONTENT IS DETAILED AND EDUCATIONAL.

1. 'description': Write a comprehensive 2 sentence summary of the breed's identity and historical significance.

2. 'health_risks': {
     'concerns': [
       { 'name': 'Condition Name (summarazed 2-3 words only!)', 'risk_level': 'High Risk', 'description': 'Detailed description of why this breed is susceptible.', 'prevention': 'Specific medical and lifestyle prevention tips.' },
       { 'name': 'Condition Name (summarazed 2-3 words only!)', 'risk_level': 'Moderate Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' }
       { 'name': 'Condition Name (summarazed 2-3 words only!)', 'risk_level': 'Low Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' }
       { 'name': 'Condition Name (summarazed 2-3 words only!)', 'risk_level': 'Low Risk', 'description': 'Detailed description of the condition.', 'prevention': 'Practical prevention advice.' }
     ],
     'screenings': [
       { 'name': 'Exam Name', 'description': ' explanation of what this exam checks for and why it is critical.' },
       { 'name': 'Exam Name', 'description': ' explanation.' },
       { 'name': 'Exam Name', 'description': ' explanation.' },
       { 'name': 'Exam Name', 'description': ' explanation.' }
     ],
     'lifespan': 'e.g. 10-12',
     'care_tips': [
        '(generate only 8-10 words only) tip about exercise needs specific to this breed.',
        '(generate only 8-10 words only) tip about diet or weight management.',
        ' (generate only 8-10 words only)tip about grooming or coat care.',
        '(generate only 8-10 words only) tip about training or temperament management.'
     ]
},

3. 'origin_data': {
    'country': 'Country Name (e.g. United Kingdom)',
    'country_code': 'ISO 2-letter country code lowercase (e.g. gb, us, de, fr)',
    'region': 'Specific Region (e.g. Scottish Highlands, Black Forest)',
    'description': 'Write a rich, descriptive paragraph ( 3 sentences) about the geography and climate of the origin region and how it influenced the breed.',
    'timeline': [
        { 'year': 'Year (e.g. 1860s)', 'event': 'Write 2-3 sentences explaining this specific historical event or breeding milestone.' },
        { 'year': 'Year', 'event': 'Write 1 sentences explaining this event.' },
        { 'year': 'Year', 'event': 'Write 1 sentences explaining this event.' },
        { 'year': 'Year', 'event': 'Write 1 sentences explaining this event.' },
        { 'year': 'Year', 'event': 'Write 1 sentences explaining this event.' }
    ],
    'details': [
        { 'title': 'Ancestry & Lineage', 'content': 'Write a long, detailed paragraph (approx 100-110 words) tracing the breed\'s genetic ancestors and early development.' },
        { 'title': 'Original Purpose', 'content': 'Write a long, detailed paragraph (approx 100-110 words) describing exactly what work the dog was bred to do, including specific tasks.' },
        { 'title': 'Modern Roles', 'content': 'Write a long, detailed paragraph (approx 100-110 words) about the breed\'s current status as pets, service dogs, or working dogs.' }
    ]
}";

                    $chatResponse = OpenAI::chat()->create([
                        'model' => 'gpt-4.1-mini',
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a veterinary historian. Output strictly valid JSON. Be verbose and detailed.'],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'max_tokens' => 1500,
                    ]);

                    $content = $chatResponse->choices[0]->message->content;
                    $parsedAi = json_decode($content, true);

                    if ($parsedAi) {
                        $aiData['description'] = $parsedAi['description'] ?? '';
                        $aiData['health_risks'] = $parsedAi['health_risks'] ?? [];
                        $aiData['origin_history'] = $parsedAi['origin_data'] ?? [];
                    }
                }
            } catch (\Exception $e) {
                Log::error("OpenAI Generation Failed: " . $e->getMessage());
            }

            // ==========================================
            // FIXED: Store empty simulation data initially
            // Job will generate ONLY 2 images (1 year and 3 years)
            // ==========================================
            $simulationData = [
                '1_years' => null,
                '3_years' => null,
                'status' => 'pending',
                'dog_features' => $dogFeatures, // Store features for reference
            ];

            // Save to Database
            $uniqueId = strtoupper(Str::random(6));

            $dbResult = Results::create([
                'scan_id' => $uniqueId,
                'image' => $path,
                'breed' => $detectedBreed,
                'confidence' => round($confidence, 2),
                'top_predictions' => $topPredictions,
                'description' => $aiData['description'],
                'origin_history' => is_string($aiData['origin_history']) ? $aiData['origin_history'] : json_encode($aiData['origin_history']),
                'health_risks' => is_string($aiData['health_risks']) ? $aiData['health_risks'] : json_encode($aiData['health_risks']),
                'age_simulation' => null, // REMOVED - No longer storing aging text
                'simulation_data' => json_encode($simulationData),
            ]);

            session(['last_scan_id' => $dbResult->scan_id]);

            // Dispatch async job with dog features for ONLY 2 images
            \App\Jobs\GenerateAgeSimulations::dispatch($dbResult->id, $detectedBreed, $dogFeatures);

            $responseData = [
                'scan_id' => $dbResult->scan_id,
                'breed' => $dbResult->breed,
                'confidence' => $dbResult->confidence,
                'image' => $dbResult->image,
                // Use asset() or Storage::url() to create a full URL for the mobile app
                'image_url' => asset('storage/' . $dbResult->image),
                'top_predictions' => $dbResult->top_predictions,
                'description' => $dbResult->description,
                'created_at' => $dbResult->created_at,
            ];

            // Check if request wants JSON (API) or is from Web
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Analysis completed successfully'
                ], 200);
            }

            return redirect('/scan-results');
        } catch (\Exception $e) {
            Log::error('Analyze Error: ' . $e->getMessage());
            return back()->withErrors(['image' => 'Analysis failed: ' . $e->getMessage()]);
        }
    }

    // Add this method to your ScanResultController
    // Inside app/Http/Controllers/model/ScanResultController.php
    // Add this method to your ScanResultController.php

    public function getOriginHistory($scan_id)
    {
        $result = Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Scan result not found.'
            ], 404);
        }

        // Decode the JSON string from the database into an array
        $originData = is_string($result->origin_history)
            ? json_decode($result->origin_history, true)
            : $result->origin_history;

        // Add logging to see what's being returned
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
        // 1. Fetch the record from the database using scan_id
        // Assuming your model is named 'Results' or 'ScanResult'
        $result = \App\Models\Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Scan result not found.'
            ], 404);
        }

        // 2. Return the data formatted for your Mobile 'Result' type
        return response()->json([
            'success' => true,
            'data' => [
                'scan_id' => $result->scan_id,
                'breed' => $result->breed,
                'description' => $result->description,
                'confidence' => (float)$result->confidence,
                'image_url' => asset('storage/' . $result->image), // Ensure full URL
                'top_predictions' => is_string($result->top_predictions)
                    ? json_decode($result->top_predictions)
                    : $result->top_predictions,
                'created_at' => $result->created_at,
            ]
        ]);
    }

    // app/Http/Controllers/model/ScanResultController.php
    // app/Http/Controllers/model/ScanResultController.php

    public function getHealthRisk($scan_id)
    {
        $result = Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Scan result not found.'
            ], 404);
        }

        // Decode the JSON string from the database into an array
        $healthData = is_string($result->health_risks)
            ? json_decode($result->health_risks, true)
            : $result->health_risks;

        // Add logging to see what's being returned
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
        // 1. Find the correction
        $correction = BreedCorrection::findOrFail($id);

        // 2. Load JSON References
        $jsonPath = storage_path('app/references.json');
        if (file_exists($jsonPath)) {
            $references = json_decode(file_get_contents($jsonPath), true);

            // 3. Filter out the specific image associated with this correction
            // We look for the image filename in the source_image field
            $imageName = basename($correction->image_path);

            $newReferences = array_filter($references, function ($ref) use ($imageName) {
                return $ref['source_image'] !== $imageName;
            });

            // 4. Save back to JSON
            file_put_contents($jsonPath, json_encode(array_values($newReferences), JSON_PRETTY_PRINT));
        }

        // 5. Delete from DB
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

        // 1. SAVE TO SEPARATE TABLE (Do not update Results table)
        BreedCorrection::create([
            'scan_id' => $result->scan_id,
            'image_path' => $result->image,
            'original_breed' => $result->breed, // Keep record of what AI got wrong
            'corrected_breed' => $validated['correct_breed'],
            'status' => 'Added',
        ]);

        // Note: We removed $result->update(...) so the original scan remains "Wrong" in history.

        // 2. Trigger Learning (Memory Update)
        try {
            $pythonPath = env('PYTHON_PATH', 'python');
            $scriptPath = base_path('ml/learn.py');
            $imagePath = storage_path('app/public/' . $result->image);
            $jsonPath = storage_path('app/references.json');

            $command = sprintf(
                '"%s" "%s" "%s" "%s" "%s" 2>&1',
                $pythonPath,
                $scriptPath,
                $imagePath,
                $validated['correct_breed'],
                $jsonPath
            );

            $output = shell_exec($command);
            Log::info("Learning output: " . $output);
        } catch (\Exception $e) {
            Log::error("Learning failed: " . $e->getMessage());
        }

        return redirect('/model/scan-results')->with('success', 'Correction saved to dataset.');
    }

    public function deleteResult($id)
    {
        $result = Results::findOrFail($id);

        $result->delete();

        return redirect()->back()->with('success', 'Deleted');
    }
}
