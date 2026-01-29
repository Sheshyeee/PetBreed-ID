<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
use App\Models\BreedCorrection;
use App\Models\Results;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use LDAP\Result;
use Pest\Support\Str;

class ScanResultController extends Controller
{
    public function dashboard()
    {
        $results = Results::latest()->take(6)->get();

        return inertia('dashboard', ['results' => $results]);
    }
    public function preview($id)
    {
        $result = Results::findOrFail($id);

        return inertia('model/review-dog', ['result' => $result]);
    }



    public function index()
    {
        $correctedScanIds = BreedCorrection::pluck('scan_id');

        // 2. Fetch Results that are NOT in that list
        $results = Results::whereNotIn('scan_id', $correctedScanIds)
            ->latest()
            ->get();

        return inertia('model/scan-results', ['results' => $results]);
    }


    public function analyze(Request $request)
    {
        Log::info('=================================');
        Log::info('=== ANALYZE REQUEST STARTED ===');
        Log::info('=================================');
        Log::info('Timestamp: ' . now());
        Log::info('Request method: ' . $request->method());
        Log::info('Has file: ' . ($request->hasFile('image') ? 'YES' : 'NO'));

        $path = null;

        try {
            // Log file details BEFORE validation
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                Log::info('=== FILE DETAILS (BEFORE VALIDATION) ===');
                Log::info('Original name: ' . $file->getClientOriginalName());
                Log::info('Size: ' . $file->getSize() . ' bytes');
                Log::info('MIME type: ' . $file->getMimeType());
                Log::info('Client extension: ' . $file->getClientOriginalExtension());
                Log::info('Extension: ' . $file->extension());
                Log::info('Is valid: ' . ($file->isValid() ? 'YES' : 'NO'));
                Log::info('Error code: ' . $file->getError());

                $tempPath = $file->getRealPath();
                if ($tempPath && file_exists($tempPath)) {
                    $imageInfo = @getimagesize($tempPath);
                    if ($imageInfo) {
                        Log::info('Image info from getimagesize:');
                        Log::info('  - Width: ' . $imageInfo[0]);
                        Log::info('  - Height: ' . $imageInfo[1]);
                        Log::info('  - Type: ' . $imageInfo[2]);
                        Log::info('  - MIME: ' . $imageInfo['mime']);
                    }
                }
            }

            // Validation
            Log::info('=== VALIDATION ===');

            $validated = $request->validate([
                'image' => [
                    'required',
                    'file',
                    'mimes:jpeg,jpg,png,webp,gif,avif,bmp,svg',
                    'max:10240', // 10MB
                    function ($attribute, $value, $fail) {
                        if (!$value->isValid()) {
                            $fail('The uploaded file is invalid.');
                            return;
                        }

                        $tempPath = $value->getRealPath();
                        if (!$tempPath || !file_exists($tempPath)) {
                            $fail('Unable to access the uploaded file.');
                            return;
                        }

                        // Verify it's an actual image using getimagesize
                        $imageInfo = @getimagesize($tempPath);
                        if ($imageInfo === false) {
                            $fail('The file must be a valid image.');
                            return;
                        }

                        // Check dimensions
                        if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                            $fail('Image dimensions are too large. Maximum 10000x10000 pixels.');
                            return;
                        }

                        // Verify supported image types by checking MIME type
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
            ], [
                'image.required' => 'Please select an image to upload.',
                'image.file' => 'The uploaded file is invalid.',
                'image.mimes' => 'The image must be a valid image file (JPEG, PNG, WebP, GIF, AVIF, BMP, SVG).',
                'image.max' => 'The image must not be larger than 10MB.',
            ]);

            Log::info('✓ Validation passed');

            // Process File
            Log::info('=== FILE PROCESSING ===');
            $image = $request->file('image');

            // Determine proper extension based on MIME type
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

            // Store file with correct extension
            $filename = time() . '_' . pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME) . '.' . $extension;
            Log::info('Storing as: ' . $filename);

            $path = $image->storeAs('scans', $filename, 'public');
            Log::info('Stored at: ' . $path);

            $fullPath = storage_path('app/public/' . $path);

            if (!file_exists($fullPath)) {
                throw new \Exception('File was not saved properly');
            }

            // Python Execution
            Log::info('=== PYTHON EXECUTION ===');
            $pythonPath = env('PYTHON_PATH', 'python');

            // Fix: Use correct separator for OS if needed, generally base_path works
            $scriptPath = base_path('ml/predict.py');
            $jsonPath = storage_path('app/references.json');

            if (!file_exists($scriptPath)) {
                throw new \Exception('Prediction script not found at: ' . $scriptPath);
            }

            // Command: python predict.py [image_path] [memory_file_path]
            $command = sprintf('"%s" "%s" "%s" "%s" 2>&1', $pythonPath, $scriptPath, $fullPath, $jsonPath);
            Log::info('Command: ' . $command);

            $startTime = microtime(true);
            $output = shell_exec($command);
            $executionTime = round(microtime(true) - $startTime, 2);

            Log::info('Execution time: ' . $executionTime . 's');
            Log::info('Output length: ' . strlen($output ?? ''));

            if (empty($output)) {
                throw new \Exception('No output from prediction script');
            }

            // Parse JSON
            Log::info('=== JSON PARSING ===');
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
                Log::error('Output: ' . substr($output, 0, 500));
                throw new \Exception('Invalid JSON from prediction script');
            }

            Log::info('Parsed result: ' . json_encode($result));

            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            // Calculate Confidence
            $confidence = $result['confidence'];
            if ($confidence <= 1.0) {
                $confidence = $confidence * 100; // Convert 0.85 to 85
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

            $uniqueId = Str::random(6);

            $dbResult = Results::create([
                'scan_id' => $uniqueId,
                'image' => $path,
                'breed' => $result['breed'],
                'confidence' => round($confidence, 2),
                'top_predictions' => $topPredictions,
            ]);

            session(['last_scan_id' => $dbResult->scan_id]);

            return redirect('/scan-results');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== VALIDATION ERROR ===');
            Log::error('Validation errors: ' . json_encode($e->errors()));
            throw $e;
        } catch (\Exception $e) {
            Log::error('=== EXCEPTION ===');
            Log::error('Message: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ':' . $e->getLine());

            $errorData = ['message' => 'Failed to analyze image: ' . $e->getMessage()];
            return back()->with('error', $errorData);
        }
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
}
