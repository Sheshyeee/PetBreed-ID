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

      // DETAILED AGING DESCRIPTIONS - DRAMATIC AND VISIBLE
      $getAgingChanges = function ($ageYears) use ($currentAgeYears, $coatColor, $distinctiveMarkings) {
        $yearsOlder = $ageYears - $currentAgeYears;

        $changes = [];

        // Age-specific changes - MORE DRAMATIC AND VISIBLE
        if ($ageYears < 2) {
          $changes[] = "VERY youthful puppy-like appearance with soft features";
          $changes[] = "extremely bright, crystal clear eyes full of energy";
          $changes[] = "ultra glossy, silky shiny coat with maximum shine";
          $changes[] = "playful, energetic, curious expression";
          $changes[] = "smooth skin around eyes and muzzle with zero graying";
        } elseif ($ageYears < 5) {
          $changes[] = "prime adult appearance with strong features";
          $changes[] = "very well-developed muscular build";
          $changes[] = "healthy, lustrous coat with good shine";
          $changes[] = "alert, confident, focused expression";
          $changes[] = "minimal to no graying anywhere on face or body";
        } elseif ($ageYears < 8) {
          $changes[] = "VISIBLE mature aging signs throughout face";
          $changes[] = "CLEAR gray/white hairs spreading across the muzzle and chin";
          $changes[] = "noticeably duller, less glossy coat texture";
          $changes[] = "calm, mature, experienced expression";
          $changes[] = "OBVIOUS whitening/graying around eyebrows and forehead";
          $changes[] = "slight loosening of skin around eyes creating gentle wrinkles";
          $changes[] = "beginning of white/gray patches on chest";
        } else {
          $changes[] = "HEAVILY distinguished senior appearance with pronounced aging";
          $changes[] = "EXTENSIVE gray/white fur covering entire muzzle, face, eyebrows, and forehead";
          $changes[] = "coat significantly duller, coarser, with thin or patchy areas";
          $changes[] = "gentle, wise, tired expression with drooping features";
          $changes[] = "PROMINENT whitening spread across chest, paws, and legs";
          $changes[] = "eyes appear noticeably cloudier with visible cataract-like appearance";
          $changes[] = "sagging skin around jowls and under eyes";
          $changes[] = "graying extending to ears and back";
        }

        return implode('. ', $changes);
      };

      // GENERATE 1-YEAR IMAGE (IMAGE-TO-IMAGE)
      try {
        Log::info("=== Generating 1_years simulation (IMAGE-TO-IMAGE) ===");

        // ENHANCED: More explicit aging prompt for 1 year transformation
        $prompt1Year = "Age this exact dog to look {$age1YearLater} years old. "
          . "KEEP IDENTICAL: facial structure, {$coatColor} base color, {$coatPattern} pattern, {$distinctiveMarkings}, {$earType} ears, pose, background. "
          . "MUST SHOW AGING: {$getAgingChanges($age1YearLater)}. "
          . "Make the aging changes VERY CLEAR and VISIBLE - add actual gray/white fur where specified, change coat shine level, adjust eye clarity, modify expression. "
          . "The dog must look noticeably different in age while remaining the same individual. Realistic pet photography.";

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

        // ENHANCED: More dramatic aging prompt for 3 years transformation
        $prompt3Years = "Age this exact dog to look {$age3YearsLater} years old. "
          . "KEEP IDENTICAL: facial structure, {$coatColor} base color, {$coatPattern} pattern, {$distinctiveMarkings}, {$earType} ears, pose, background. "
          . "MUST SHOW DRAMATIC AGING: {$getAgingChanges($age3YearsLater)}. "
          . "Make the aging transformation HIGHLY VISIBLE and PRONOUNCED - extensively gray/white the specified areas, significantly reduce coat shine, make eyes look aged, change facial expression to match age. "
          . "The dog should look SIGNIFICANTLY older while remaining recognizably the same dog. Professional realistic pet photography.";

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
   * FIXED: Use finfo to detect MIME type from buffer instead of temp file
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

      // Validate it's actually an image by trying to create image resource
      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      Log::info("Image validated: {$imageInfo[0]}x{$imageInfo[1]}, type: {$imageInfo['mime']}");

      // Use the MIME type from getimagesizefromstring (most reliable)
      $mimeType = $imageInfo['mime'];

      // Convert to supported format if needed (Gemini supports JPEG, PNG, WebP, GIF)
      $supportedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (!in_array($mimeType, $supportedMimes)) {
        Log::warning("Unsupported image type {$mimeType}, converting to JPEG");

        // Create image from string and convert to JPEG
        $img = imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception("Failed to create image resource for conversion");
        }

        ob_start();
        imagejpeg($img, null, 90);
        $imageContents = ob_get_clean();
        imagedestroy($img);

        $mimeType = 'image/jpeg';
        Log::info("Converted to JPEG, new size: " . strlen($imageContents) . " bytes");
      }

      // Encode to base64 - ensuring no whitespace or newlines
      $imageData = base64_encode($imageContents);

      // Validate base64 encoding
      if (empty($imageData)) {
        throw new \Exception('Base64 encoding failed - empty result');
      }

      Log::info('✓ Original image prepared for image-to-image', [
        'mime_type' => $mimeType,
        'size_bytes' => strlen($imageContents),
        'base64_length' => strlen($imageData),
        'dimensions' => "{$imageInfo[0]}x{$imageInfo[1]}"
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
   * FIXED: Use finfo to detect MIME type from buffer + validate image
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

      // Download image from object storage
      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false || empty($imageContents)) {
        throw new \Exception('Failed to download image from object storage or image is empty');
      }

      Log::info('✓ Image downloaded from object storage', [
        'file_size' => strlen($imageContents)
      ]);

      // Validate it's actually an image
      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      // Use the MIME type from getimagesizefromstring (most reliable)
      $mimeType = $imageInfo['mime'];

      Log::info("Image validated for feature extraction: {$imageInfo[0]}x{$imageInfo[1]}, type: {$mimeType}");

      // Convert to supported format if needed
      $supportedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (!in_array($mimeType, $supportedMimes)) {
        Log::warning("Unsupported image type {$mimeType} for feature extraction, converting to JPEG");

        $img = imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception("Failed to create image resource for conversion");
        }

        ob_start();
        imagejpeg($img, null, 90);
        $imageContents = ob_get_clean();
        imagedestroy($img);

        $mimeType = 'image/jpeg';
      }

      $imageData = base64_encode($imageContents);

      if (empty($imageData)) {
        throw new \Exception('Base64 encoding failed for feature extraction');
      }

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

      Log::info('✓ Dog features extracted successfully with Gemini');
    } catch (\Exception $e) {
      Log::error("Feature extraction failed: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());
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
