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

  public $timeout = 300; // 5 minutes max
  public $tries = 1; // Don't retry on failure

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

      // GENERATE 1-YEAR IMAGE (IMAGE-TO-IMAGE)
      try {
        Log::info("=== Generating 1_years simulation (IMAGE-TO-IMAGE) ===");

        // CRITICAL: Image-to-image prompt that references the original dog
        $prompt1Year = "Transform this exact dog to show how it will look in {$age1YearLater} years old. "
          . "PRESERVE: The dog's unique facial features, exact {$coatColor} coloring, {$coatPattern} pattern, "
          . "{$distinctiveMarkings} markings, {$earType} ears, {$eyeColor} eyes, and overall appearance. "
          . "CHANGE ONLY: Age the dog by exactly 1 year. Apply these aging changes: {$getAgingChanges($age1YearLater)}. "
          . "The dog MUST remain recognizably the same individual dog, just {$age1YearLater} years old. "
          . "Keep the same pose, lighting, and background style. Professional pet photography quality.";

        Log::info("Prompt 1-year: " . substr($prompt1Year, 0, 200) . "...");

        // Call Gemini with IMAGE-TO-IMAGE (passing original image)
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
        $simulationData['error_1year'] = 'Content policy rejection or API error: ' . $e->getMessage();
      }

      // Wait between API calls
      sleep(3);

      // GENERATE 3-YEAR IMAGE (IMAGE-TO-IMAGE)
      try {
        Log::info("=== Generating 3_years simulation (IMAGE-TO-IMAGE) ===");

        // CRITICAL: Image-to-image prompt for 3 years aging
        $prompt3Years = "Transform this exact dog to show how it will look in {$age3YearsLater} years old. "
          . "PRESERVE: The dog's unique facial features, exact {$coatColor} coloring, {$coatPattern} pattern, "
          . "{$distinctiveMarkings} markings, {$earType} ears, {$eyeColor} eyes, and overall appearance. "
          . "CHANGE ONLY: Age the dog by exactly 3 years. Apply these aging changes: {$getAgingChanges($age3YearsLater)}. "
          . "The dog MUST remain recognizably the same individual dog, just {$age3YearsLater} years old. "
          . "Keep the same pose, lighting, and background style. Professional pet photography quality.";

        Log::info("Prompt 3-year: " . substr($prompt3Years, 0, 200) . "...");

        // Call Gemini with IMAGE-TO-IMAGE (passing original image)
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
        $simulationData['error_3year'] = 'Content policy rejection or API error: ' . $e->getMessage();
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
   * ==========================================
   */
  private function prepareOriginalImage($fullPath)
  {
    try {
      // Download image from object storage
      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false) {
        throw new \Exception('Failed to download image from object storage');
      }

      // Create temporary file to get mime type
      $tempPath = tempnam(sys_get_temp_dir(), 'orig_image_');
      $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
      $tempPathWithExt = $tempPath . '.' . $extension;
      rename($tempPath, $tempPathWithExt);

      file_put_contents($tempPathWithExt, $imageContents);

      $imageData = base64_encode($imageContents);
      $mimeType = mime_content_type($tempPathWithExt);

      // Clean up temp file
      if (file_exists($tempPathWithExt)) {
        unlink($tempPathWithExt);
      }

      Log::info('✓ Original image prepared for image-to-image', [
        'mime_type' => $mimeType,
        'size_bytes' => strlen($imageContents)
      ]);

      return [
        'base64' => $imageData,
        'mimeType' => $mimeType
      ];
    } catch (\Exception $e) {
      Log::error("Failed to prepare original image: " . $e->getMessage());
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
      // Download image from object storage to temp file
      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false) {
        throw new \Exception('Failed to download image from object storage');
      }

      // Create temporary file
      $tempPath = tempnam(sys_get_temp_dir(), 'feature_extract_');
      $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
      $tempPathWithExt = $tempPath . '.' . $extension;
      rename($tempPath, $tempPathWithExt);

      file_put_contents($tempPathWithExt, $imageContents);

      Log::info('✓ Image downloaded from object storage to temp file', [
        'temp_path' => $tempPathWithExt,
        'file_size' => strlen($imageContents)
      ]);

      $imageData = base64_encode($imageContents);
      $mimeType = mime_content_type($tempPathWithExt);

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

      // Use Gemini API
      $apiKey = config('services.gemini.api_key');
      if (empty($apiKey)) {
        Log::error('✗ Gemini API key not configured');
        return $dogFeatures;
      }

      $client = new \GuzzleHttp\Client();
      $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent?key=' . $apiKey, [
        'json' => [
          'contents' => [
            [
              'parts' => [
                [
                  'text' => $visionPrompt
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
            'maxOutputTokens' => 500,
            'responseMimeType' => 'application/json'
          ]
        ]
      ]);

      $result = json_decode($response->getBody()->getContents(), true);
      $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

      if ($content) {
        $features = json_decode($content, true);
        if ($features) {
          $dogFeatures = array_merge($dogFeatures, $features);
        }
      }

      // Clean up temp file
      if (file_exists($tempPathWithExt)) {
        unlink($tempPathWithExt);
      }

      Log::info('✓ Dog features extracted successfully with Gemini');
    } catch (\Exception $e) {
      Log::error("Feature extraction failed: " . $e->getMessage());
    }

    return $dogFeatures;
  }

  /**
   * ==========================================
   * FIXED: Generate image using IMAGE-TO-IMAGE with Gemini
   * This preserves the dog's appearance while aging it
   * ==========================================
   */
  private function generateImageWithGemini($prompt, $originalImageBase64, $mimeType)
  {
    try {
      $apiKey = config('services.gemini.api_key');
      if (!$apiKey) {
        $apiKey = env('GEMINI_API_KEY');
      }

      if (!$apiKey) {
        throw new \Exception("Gemini API key not configured");
      }

      // Gemini Nano Banana Pro 3 endpoint
      $modelName = "nano-banana-pro-preview";
      $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

      Log::info("Calling Gemini Nano Banana Pro API with IMAGE-TO-IMAGE...");

      // IMAGE-TO-IMAGE: Include the original image so the model can preserve the dog's appearance
      $payload = array(
        'contents' => array(
          array(
            'parts' => array(
              array(
                'text' => $prompt
              ),
              array(
                'inlineData' => array(
                  'mimeType' => $mimeType,
                  'data' => $originalImageBase64
                )
              )
            )
          )
        ),
        'generationConfig' => array(
          'temperature' => 0.6, // Lower temperature for more consistent aging
          'topK' => 40,
          'topP' => 0.90,
          'maxOutputTokens' => 8192
        )
      );

      $jsonPayload = json_encode($payload);

      // Use cURL for reliable HTTP request
      $ch = curl_init($endpoint);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
      ));
      curl_setopt($ch, CURLOPT_TIMEOUT, 120);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

      $responseBody = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);

      if ($curlError) {
        throw new \Exception("cURL error: {$curlError}");
      }

      if ($httpCode !== 200) {
        Log::error("Gemini API Error (HTTP {$httpCode}): " . $responseBody);
        $errorData = json_decode($responseBody, true);
        $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Unknown error';
        throw new \Exception("Gemini API returned error (HTTP {$httpCode}): {$errorMessage}");
      }

      // Parse JSON response
      $responseData = json_decode($responseBody, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        Log::error("JSON decode error: " . json_last_error_msg());
        throw new \Exception("Failed to parse Gemini API response");
      }

      Log::info("Gemini API Response received successfully");

      // Extract image data from response
      if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        Log::info("✓ Image data extracted successfully (base64 length: " . strlen($base64Image) . ")");
        return base64_decode($base64Image);
      } elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['text'];
        $base64Image = preg_replace('/```[\w]*\n?/', '', $base64Image);
        $base64Image = trim($base64Image);
        Log::info("✓ Image data extracted from text field (length: " . strlen($base64Image) . ")");
        return base64_decode($base64Image);
      } else {
        Log::error("Unexpected Gemini response format");
        Log::error("Full response: " . json_encode($responseData));
        throw new \Exception("Unexpected response format from Gemini API - no image data found");
      }
    } catch (\Exception $e) {
      Log::error("Gemini API call failed: " . $e->getMessage());
      throw $e;
    }
  }
}
