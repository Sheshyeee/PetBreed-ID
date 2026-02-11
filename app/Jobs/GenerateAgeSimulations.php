<?php

namespace App\Jobs;

use App\Models\Results;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 600; // Increased to 10 minutes for retries
  public $tries = 1; // Don't retry on failure (we handle retries internally)

  protected $resultId;
  protected $breed;
  protected $imagePath;

  /**
   * Constructor: Now accepts image path
   */
  public function __construct($resultId, $breed, $imagePath)
  {
    $this->resultId = $resultId;
    $this->breed = $breed;
    $this->imagePath = $imagePath;
  }

  public function handle()
  {
    try {
      Log::info("=== GENERATING AGE SIMULATIONS (IMAGE-TO-IMAGE) ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);

      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      // EXTRACT DOG FEATURES AND PREPARE ORIGINAL IMAGE
      Log::info("→ Preparing original image for image-to-image generation...");
      $imageData = $this->prepareOriginalImage($this->imagePath);

      if (!$imageData) {
        throw new \Exception("Failed to load original image");
      }

      $dogFeatures = $this->extractDogFeatures($this->imagePath, $this->breed);
      Log::info("✓ Dog features extracted", $dogFeatures);

      // Initialize simulation data
      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $dogFeatures
      ];

      // Update status to generating IMMEDIATELY
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Status updated to 'generating'");

      // Extract features with defaults
      $coatColor = $dogFeatures['coat_color'] ?? 'brown';
      $coatPattern = $dogFeatures['coat_pattern'] ?? 'solid';
      $coatLength = $dogFeatures['coat_length'] ?? 'medium';
      $coatTexture = $dogFeatures['coat_texture'] ?? 'smooth';
      $build = $dogFeatures['build'] ?? 'medium';
      $estimatedAge = $dogFeatures['estimated_age'] ?? 'young adult';
      $distinctiveMarkings = $dogFeatures['distinctive_markings'] ?? 'none';
      $earType = $dogFeatures['ear_type'] ?? 'unknown';
      $eyeColor = $dogFeatures['eye_color'] ?? 'brown';

      // Convert estimated age to numeric years
      $estimatedAgeLower = strtolower($estimatedAge);
      switch ($estimatedAgeLower) {
        case 'puppy':
          $currentAgeYears = 0.5;
          break;
        case 'young adult':
          $currentAgeYears = 2;
          break;
        case 'adult':
          $currentAgeYears = 4;
          break;
        case 'mature':
          $currentAgeYears = 6;
          break;
        case 'senior':
          $currentAgeYears = 9;
          break;
        default:
          $currentAgeYears = 2;
          break;
      }

      // Calculate future ages
      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1year={$age1YearLater}y, +3years={$age3YearsLater}y");

      // DETAILED AGING DESCRIPTIONS
      $getAgingChanges = function ($ageYears) use ($currentAgeYears, $coatColor, $distinctiveMarkings) {
        $yearsOlder = $ageYears - $currentAgeYears;

        $changes = [];

        // Age-specific changes
        if ($ageYears < 2) {
          $changes[] = "youthful, vibrant appearance";
          $changes[] = "bright, clear eyes";
          $changes[] = "glossy, shiny coat";
          $changes[] = "energetic expression";
        } elseif ($ageYears < 5) {
          $changes[] = "mature adult appearance";
          $changes[] = "well-developed muscular build";
          $changes[] = "healthy, lustrous coat";
          $changes[] = "alert, confident expression";
        } elseif ($ageYears < 8) {
          $changes[] = "mature appearance with subtle aging signs";
          $changes[] = "slight gray/white hairs beginning around the muzzle";
          $changes[] = "slightly less glossy coat texture";
          $changes[] = "calm, wise expression";
          $changes[] = "possible minor whitening around eyebrows";
        } else {
          $changes[] = "distinguished senior appearance";
          $changes[] = "noticeable gray/white fur around muzzle, face, and eyebrows";
          $changes[] = "coat may be slightly duller or coarser";
          $changes[] = "gentle, calm expression";
          $changes[] = "possible whitening on chest and paws";
          $changes[] = "eyes may appear slightly cloudier or wiser";
        }

        return implode(', ', $changes);
      };

      // GENERATE 1-YEAR IMAGE (IMAGE-TO-IMAGE) with retry
      try {
        Log::info("=== Generating 1_years simulation (IMAGE-TO-IMAGE) ===");

        $prompt1Year = "Transform this exact dog to show how it will look at {$age1YearLater} years old. "
          . "Keep the same {$coatColor} {$coatPattern} coat, {$distinctiveMarkings} markings, {$earType} ears, {$eyeColor} eyes. "
          . "Apply aging: {$getAgingChanges($age1YearLater)}. "
          . "Same pose and lighting. Professional pet photo quality.";

        Log::info("Prompt 1-year: " . substr($prompt1Year, 0, 200) . "...");

        $imageOutput = $this->generateImageWithGemini(
          $prompt1Year,
          $imageData['base64'],
          $imageData['mimeType']
        );

        if ($imageOutput) {
          $simulationFilename = "simulation_1_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $imageOutput);
          $simulationData['1_years'] = $simulationPath;

          // UPDATE DATABASE IMMEDIATELY
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ Generated 1_years: {$simulationPath}");
        } else {
          throw new \Exception("Failed to generate image data");
        }
      } catch (\Exception $e) {
        Log::error("Failed 1_years simulation: " . $e->getMessage());
        $simulationData['1_years'] = null;
        $simulationData['error_1year'] = $e->getMessage();
        // Update DB even on failure so frontend knows
        $result->update(['simulation_data' => json_encode($simulationData)]);
      }

      // Wait between API calls to avoid rate limiting
      sleep(5);

      // GENERATE 3-YEAR IMAGE (IMAGE-TO-IMAGE) with retry
      try {
        Log::info("=== Generating 3_years simulation (IMAGE-TO-IMAGE) ===");

        $prompt3Years = "Transform this exact dog to show how it will look at {$age3YearsLater} years old. "
          . "Keep the same {$coatColor} {$coatPattern} coat, {$distinctiveMarkings} markings, {$earType} ears, {$eyeColor} eyes. "
          . "Apply aging: {$getAgingChanges($age3YearsLater)}. "
          . "Same pose and lighting. Professional pet photo quality.";

        Log::info("Prompt 3-year: " . substr($prompt3Years, 0, 200) . "...");

        $imageOutput = $this->generateImageWithGemini(
          $prompt3Years,
          $imageData['base64'],
          $imageData['mimeType']
        );

        if ($imageOutput) {
          $simulationFilename = "simulation_3_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $imageOutput);
          $simulationData['3_years'] = $simulationPath;

          Log::info("✓ Generated 3_years: {$simulationPath}");
        } else {
          throw new \Exception("Failed to generate image data");
        }
      } catch (\Exception $e) {
        Log::error("Failed 3_years simulation: " . $e->getMessage());
        $simulationData['3_years'] = null;
        $simulationData['error_3year'] = $e->getMessage();
      }

      // Mark as complete (even if some failed)
      $simulationData['status'] = 'complete';

      // Final database update
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Final status: complete");
      Log::info("✓ Simulation job finished - Success: 1yr=" . ($simulationData['1_years'] ? 'YES' : 'NO') . ", 3yr=" . ($simulationData['3_years'] ? 'YES' : 'NO'));
    } catch (\Exception $e) {
      Log::error("Age simulation job FAILED: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());

      // Update status to failed
      $failedData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'failed',
        'error' => $e->getMessage()
      ];

      Results::where('id', $this->resultId)->update([
        'simulation_data' => json_encode($failedData)
      ]);
    }
  }

  /**
   * ==========================================
   * NEW: Prepare original image for image-to-image generation
   * Optimizes image size for better API reliability
   * ==========================================
   */
  private function prepareOriginalImage($fullPath)
  {
    try {
      Log::info("Preparing original image from path: {$fullPath}");

      // Download image from object storage
      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false || empty($imageContents)) {
        throw new \Exception('Failed to download image from object storage or image is empty');
      }

      Log::info("Image downloaded, size: " . strlen($imageContents) . " bytes");

      // Validate it's actually an image
      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      Log::info("Image validated: {$imageInfo[0]}x{$imageInfo[1]}, type: {$imageInfo['mime']}");

      $mimeType = $imageInfo['mime'];

      // OPTIMIZATION: Resize large images to reduce API load and improve success rate
      $maxDimension = 1024; // Max width or height
      if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension) {
        Log::info("Resizing image for optimal API performance...");

        $img = imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception("Failed to create image resource");
        }

        // Calculate new dimensions
        $ratio = min($maxDimension / $imageInfo[0], $maxDimension / $imageInfo[1]);
        $newWidth = (int)($imageInfo[0] * $ratio);
        $newHeight = (int)($imageInfo[1] * $ratio);

        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($mimeType === 'image/png') {
          imagealphablending($resized, false);
          imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $imageInfo[0], $imageInfo[1]);

        // Convert to JPEG for smaller size and better API compatibility
        ob_start();
        imagejpeg($resized, null, 85); // 85% quality for good balance
        $imageContents = ob_get_clean();

        imagedestroy($img);
        imagedestroy($resized);

        $mimeType = 'image/jpeg';
        Log::info("✓ Image resized to {$newWidth}x{$newHeight}, new size: " . strlen($imageContents) . " bytes");
      } else {
        // Convert to JPEG if not already
        $supportedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $supportedMimes)) {
          Log::info("Converting image to JPEG...");

          $img = imagecreatefromstring($imageContents);
          if ($img === false) {
            throw new \Exception("Failed to create image resource for conversion");
          }

          ob_start();
          imagejpeg($img, null, 90);
          $imageContents = ob_get_clean();
          imagedestroy($img);

          $mimeType = 'image/jpeg';
          Log::info("✓ Converted to JPEG, size: " . strlen($imageContents) . " bytes");
        }
      }

      // Encode to base64
      $imageData = base64_encode($imageContents);

      if (empty($imageData)) {
        throw new \Exception('Base64 encoding failed - empty result');
      }

      // Warn if image is still too large
      $sizeInMB = strlen($imageData) / (1024 * 1024);
      if ($sizeInMB > 4) {
        Log::warning("Image size is large ({$sizeInMB}MB), may cause API issues");
      }

      Log::info('✓ Original image prepared for image-to-image', [
        'mime_type' => $mimeType,
        'size_bytes' => strlen($imageContents),
        'base64_size_mb' => round($sizeInMB, 2)
      ]);

      return [
        'base64' => $imageData,
        'mimeType' => $mimeType
      ];
    } catch (\Exception $e) {
      Log::error("Failed to prepare original image: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());
      return null;
    }
  }

  /**
   * ==========================================
   * Extract Dog Features using Gemini API
   * ==========================================
   */
  private function extractDogFeatures($fullPath, $detectedBreed)
  {
    $dogFeatures = [
      'coat_color' => 'brown',
      'coat_pattern' => 'solid',
      'coat_length' => 'medium',
      'coat_texture' => 'smooth',
      'estimated_age' => 'young adult',
      'build' => 'medium',
      'distinctive_markings' => 'none',
      'ear_type' => 'unknown',
      'eye_color' => 'brown',
    ];

    try {
      Log::info("Extracting features from path: {$fullPath}");

      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false || empty($imageContents)) {
        throw new \Exception('Failed to download image from object storage');
      }

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      $mimeType = $imageInfo['mime'];

      // Convert if needed
      $supportedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (!in_array($mimeType, $supportedMimes)) {
        $img = imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception("Failed to create image resource");
        }

        ob_start();
        imagejpeg($img, null, 90);
        $imageContents = ob_get_clean();
        imagedestroy($img);

        $mimeType = 'image/jpeg';
      }

      $imageData = base64_encode($imageContents);

      if (empty($imageData)) {
        throw new \Exception('Base64 encoding failed');
      }

      $visionPrompt = "Analyze this {$detectedBreed} dog and provide ONLY a JSON response:

{
  \"coat_color\": \"specific color (e.g., golden, black and tan, white with brown)\",
  \"coat_pattern\": \"pattern (solid, spotted, brindle, merle, parti-color)\",
  \"coat_length\": \"length (short, medium, long)\",
  \"coat_texture\": \"texture (silky, wiry, fluffy, smooth)\",
  \"estimated_age\": \"age (puppy, young adult, adult, mature, senior)\",
  \"build\": \"body type (lean, stocky, medium)\",
  \"distinctive_markings\": \"unique features (facial marks, chest patches, etc.)\",
  \"ear_type\": \"ear shape (floppy, erect, semi-erect)\",
  \"eye_color\": \"eye color (brown, blue, amber)\"
}";

      $apiKey = config('services.gemini.api_key');
      if (empty($apiKey)) {
        Log::warning('Gemini API key not configured, using defaults');
        return $dogFeatures;
      }

      $client = new \GuzzleHttp\Client();
      $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent?key=' . $apiKey, [
        'json' => [
          'contents' => [
            [
              'parts' => [
                ['text' => $visionPrompt],
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
            'maxOutputTokens' => 500,
            'responseMimeType' => 'application/json'
          ]
        ],
        'timeout' => 30,
        'connect_timeout' => 10
      ]);

      $result = json_decode($response->getBody()->getContents(), true);
      $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

      if ($content) {
        $features = json_decode($content, true);
        if ($features) {
          $dogFeatures = array_merge($dogFeatures, $features);
          Log::info('✓ Dog features extracted successfully');
        }
      }
    } catch (\Exception $e) {
      Log::warning("Feature extraction failed (using defaults): " . $e->getMessage());
    }

    return $dogFeatures;
  }

  /**
   * ==========================================
   * IMPROVED: Generate image with retry logic and exponential backoff
   * ==========================================
   */
  private function generateImageWithGemini($prompt, $originalImageBase64, $mimeType, $maxRetries = 3)
  {
    $lastException = null;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
          $apiKey = env('GEMINI_API_KEY');
        }

        if (!$apiKey) {
          throw new \Exception("Gemini API key not configured");
        }

        $modelName = "nano-banana-pro-preview";
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        Log::info("Gemini API call (Attempt {$attempt}/{$maxRetries})");

        // Build payload
        $payload = [
          'contents' => [
            [
              'parts' => [
                ['text' => $prompt],
                [
                  'inlineData' => [
                    'mimeType' => $mimeType,
                    'data' => $originalImageBase64
                  ]
                ]
              ]
            ]
          ],
          'generationConfig' => [
            'temperature' => 0.6,
            'topK' => 40,
            'topP' => 0.90,
            'maxOutputTokens' => 8192
          ],
          // Add safety settings to reduce rejections
          'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
          ]
        ];

        $jsonPayload = json_encode($payload);

        // Use cURL with proper timeouts
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => $jsonPayload,
          CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonPayload)
          ],
          CURLOPT_TIMEOUT => 180, // 3 minutes
          CURLOPT_CONNECTTIMEOUT => 30,
          CURLOPT_SSL_VERIFYPEER => true
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
          throw new \Exception("cURL error: {$curlError}");
        }

        // Handle HTTP errors
        if ($httpCode !== 200) {
          $errorData = json_decode($responseBody, true);
          $errorMessage = $errorData['error']['message'] ?? 'Unknown error';

          Log::error("Gemini API Error (HTTP {$httpCode}): {$errorMessage}");

          // Check if error is retryable
          $retryableCodes = [429, 500, 502, 503, 504]; // Rate limit and server errors

          if (!in_array($httpCode, $retryableCodes) || $attempt >= $maxRetries) {
            throw new \Exception("API error (HTTP {$httpCode}): {$errorMessage}");
          }

          // Exponential backoff: 3s, 6s, 12s
          $waitTime = min(3 * pow(2, $attempt - 1), 30);
          Log::warning("Retryable error, waiting {$waitTime}s...");
          sleep($waitTime);
          continue;
        }

        // Parse response
        $responseData = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new \Exception("JSON decode error: " . json_last_error_msg());
        }

        // Check for content filtering or other issues
        if (isset($responseData['candidates'][0]['finishReason'])) {
          $finishReason = $responseData['candidates'][0]['finishReason'];

          if ($finishReason !== 'STOP') {
            Log::warning("Content finish reason: {$finishReason}");

            // Try with simpler prompt on next attempt
            if ($attempt < $maxRetries) {
              Log::info("Simplifying prompt for retry...");
              // Remove detailed instructions that might trigger filters
              $prompt = preg_replace('/PRESERVE:.*?CHANGE ONLY:/s', '', $prompt);
              sleep(3);
              continue;
            }

            throw new \Exception("Content policy issue: {$finishReason}");
          }
        }

        // Extract image data
        if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
          $base64Image = $responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'];
          Log::info("✓ Image generated (size: " . strlen($base64Image) . " chars)");
          return base64_decode($base64Image);
        }

        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
          $base64Image = $responseData['candidates'][0]['content']['parts'][0]['text'];
          $base64Image = preg_replace('/```[\w]*\n?/', '', $base64Image);
          $base64Image = trim($base64Image);
          Log::info("✓ Image from text field (size: " . strlen($base64Image) . " chars)");
          return base64_decode($base64Image);
        }

        Log::error("No image data in response: " . json_encode($responseData));
        throw new \Exception("No image data found in API response");
      } catch (\Exception $e) {
        $lastException = $e;
        Log::error("Attempt {$attempt}/{$maxRetries} failed: " . $e->getMessage());

        // Don't retry configuration errors
        if (strpos($e->getMessage(), 'API key') !== false) {
          throw $e;
        }

        // Wait before next retry (if not last attempt)
        if ($attempt < $maxRetries) {
          $waitTime = min(3 * pow(2, $attempt - 1), 30);
          Log::info("Waiting {$waitTime}s before retry...");
          sleep($waitTime);
        }
      }
    }

    // All retries failed
    Log::error("All {$maxRetries} attempts exhausted");
    throw $lastException ?? new \Exception("Failed after {$maxRetries} attempts");
  }
}
