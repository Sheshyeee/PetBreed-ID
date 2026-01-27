<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
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
        $results = Results::latest()->get();

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

            // Validation - UPDATED to support AVIF and more formats
            Log::info('=== VALIDATION ===');

            $validated = $request->validate([
                'image' => [
                    'required',
                    'file',
                    // FIXED: Added avif and more flexible MIME types
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
                            'image/avif',  // ADDED: AVIF support
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

            // Get file
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
            Log::info('Detected MIME: ' . $mimeType);
            Log::info('Using extension: ' . $extension);

            $path = $image->storeAs('scans', $filename, 'public');
            Log::info('Stored at: ' . $path);

            $fullPath = storage_path('app/public/' . $path);
            Log::info('Full path: ' . $fullPath);

            if (!file_exists($fullPath)) {
                throw new \Exception('File was not saved properly');
            }

            // Python execution
            Log::info('=== PYTHON EXECUTION ===');
            $pythonPath = env('PYTHON_PATH', 'python');
            $scriptPath = base_path('ml\\predict.py');

            if (!file_exists($scriptPath)) {
                throw new \Exception('Prediction script not found at: ' . $scriptPath);
            }

            $command = sprintf('"%s" "%s" "%s" 2>&1', $pythonPath, $scriptPath, $fullPath);
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

            // Extract JSON from output
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
                throw new \Exception('Invalid JSON from prediction script: ' . json_last_error_msg());
            }

            Log::info('Parsed result: ' . json_encode($result));

            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            if (!isset($result['breed']) || !isset($result['confidence'])) {
                throw new \Exception('Invalid prediction result structure');
            }
            $uniqueId = Str::random(6);

            // FIXED: Convert confidence from decimal to percentage
            $confidence = $result['confidence'];
            if ($confidence <= 1.0) {
                $confidence = $confidence * 100; // Convert 0.85 to 85
            }

            // FIXED: Convert top_predictions confidences to percentages
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

            $result = Results::create([
                'scan_id' => $uniqueId,
                'image' => $path,
                'breed' => $result['breed'],
                'confidence' => round($confidence, 2), // Save as percentage
                'top_predictions' => $topPredictions,
            ]);



            #Log::info('Confidence: ' . ($successData['confidence'] * 100) . '%');

            session(['last_scan_id' => $result->scan_id]);



            return redirect('/scan-results');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('========================================');
            Log::error('=== VALIDATION ERROR ===');
            Log::error('========================================');
            Log::error('Validation errors: ' . json_encode($e->errors()));
            Log::error('Error messages: ' . json_encode($e->validator->errors()->all()));



            throw $e;
        } catch (\Exception $e) {
            Log::error('========================================');
            Log::error('=== EXCEPTION ===');
            Log::error('========================================');
            Log::error('Message: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ':' . $e->getLine());



            $errorData = ['message' => 'Failed to analyze image: ' . $e->getMessage()];

            return back()->with('error', $errorData);
        }
    }
}
