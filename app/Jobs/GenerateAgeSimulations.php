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

      // Store breed in local variable for use in closures
      $breed = $this->breed;

      // NATURAL HEALTHY AGING - Well-cared for dog showing breed-appropriate age
      $getAgingChanges = function ($ageYears) use ($currentAgeYears, $breed) {
        $yearsOlder = $ageYears - $currentAgeYears;

        $changes = [];

        // Age-specific changes - NATURAL HEALTHY AGING for well-cared dog
        if ($ageYears < 2) {
          $changes[] = "youthful {$breed} with bright, sparkling eyes full of puppy energy";
          $changes[] = "coat at peak youthful condition - naturally glossy and vibrant for this breed";
          $changes[] = "tight facial features with smooth skin, no signs of aging";
          $changes[] = "energetic, playful, alert expression typical of young {$breed}";
          $changes[] = "face shows youthful vitality and liveliness";
        } elseif ($ageYears < 5) {
          $changes[] = "prime adult {$breed} in excellent health and condition";
          $changes[] = "coat well-groomed and healthy with natural adult luster";
          $changes[] = "eyes bright and alert with mature confidence";
          $changes[] = "strong, confident expression showing adult maturity";
          $changes[] = "well-maintained appearance of healthy adult dog";
        } elseif ($ageYears < 8) {
          $changes[] = "mature {$breed} showing natural middle-age - still well-groomed and healthy but visibly older";
          $changes[] = "coat clean and brushed but naturally less vibrant than youth - slight reduction in natural shine as typical for aging {$breed}";
          $changes[] = "eyes still bright but developing the softer, gentler look of maturity - slight natural cloudiness beginning";
          $changes[] = "facial features gently relaxed showing maturity - natural slight softening around eyes and muzzle";
          $changes[] = "calm, wise, settled expression of a mature well-loved dog";
          $changes[] = "overall appearance of well-cared-for middle-aged {$breed} - healthy but clearly not young anymore";
        } else {
          $changes[] = "senior {$breed} showing distinguished age while clearly well-loved and properly cared for";
          $changes[] = "coat clean and groomed but showing natural senior changes - reduced luster, slightly coarser texture typical of elderly {$breed}";
          $changes[] = "eyes developing natural senior cloudiness but still clear enough to show awareness - gentle aged expression";
          $changes[] = "face showing natural senior features - gentle sagging around jowls and eyes that comes with age in {$breed}";
          $changes[] = "dignified, gentle, calm expression of a well-loved senior dog";
          $changes[] = "body still healthy-looking but naturally less toned than prime years";
          $changes[] = "overall appearance of cherished elderly {$breed} - showing age gracefully while obviously well-maintained";
        }

        return implode('. ', $changes);
      };

      // GENERATE 1-YEAR IMAGE (IMAGE-TO-IMAGE)
      try {
        Log::info("=== Generating 1_years simulation (IMAGE-TO-IMAGE) ===");

        // NATURAL HEALTHY AGING: Show how well-cared dog naturally ages 1 year
        $prompt1Year = "Transform this {$breed} dog to show natural healthy aging to {$age1YearLater} years old - this is a WELL-CARED FOR, LOVED dog showing normal age progression. "
          . "PRESERVE COMPLETELY: The dog's unique identity, facial structure, exact coat colors and patterns ({$coatColor} {$coatPattern}), "
          . "distinctive markings ({$distinctiveMarkings}), ear shape ({$earType}), same pose and background. "
          . "NATURAL AGING for well-maintained {$breed}: {$getAgingChanges($age1YearLater)}. "
          . "IMPORTANT: The dog must look HEALTHY and WELL-GROOMED - this is a loved pet with good care. Show natural aging signs: "
          . "slightly less glossy coat (but still clean and brushed), eyes beginning to soften in brightness (but still clear), "
          . "facial expression more mature and calm (but content), very slight natural softening of facial features. "
          . "The dog should look noticeably older BUT still healthy, happy, and well-cared for - aging gracefully, not declining. "
          . "Do NOT make the dog look neglected, haggard, sick, or poorly maintained. Show natural breed-appropriate aging for a loved family pet. "
          . "Professional pet photography of healthy, well-cared-for {$breed} at {$age1YearLater} years old.";

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

        // NATURAL HEALTHY SENIOR AGING: Show how well-cared senior dog looks at 3 years older
        $prompt3Years = "Transform this {$breed} dog to show natural healthy senior aging to {$age3YearsLater} years old - this is a WELL-CARED FOR, CHERISHED senior dog showing normal age progression. "
          . "PRESERVE COMPLETELY: The dog's unique identity, facial structure, exact coat colors and patterns ({$coatColor} {$coatPattern}), "
          . "distinctive markings ({$distinctiveMarkings}), ear shape ({$earType}), same pose and background. "
          . "NATURAL SENIOR AGING for well-maintained {$breed}: {$getAgingChanges($age3YearsLater)}. "
          . "IMPORTANT: The dog must look HEALTHY and WELL-CARED FOR despite age - this is a beloved senior pet receiving excellent care. Show natural senior aging: "
          . "coat clean and groomed but naturally less lustrous (still brushed and maintained), eyes developing gentle cloudiness typical of age (but still aware and alert), "
          . "natural gentle sagging around face that comes with senior years (but face clean and groomed), calm dignified expression of senior dog (content and peaceful). "
          . "The dog should look CLEARLY OLDER and SENIOR BUT still healthy, loved, well-groomed, and content - aging gracefully with dignity. "
          . "Do NOT make the dog look neglected, haggard, sick, dying, or poorly maintained. Show natural breed-appropriate senior aging for a cherished family pet. "
          . "This is how a well-loved {$breed} looks at {$age3YearsLater} years with proper care - distinguished and senior but healthy and happy. "
          . "Professional pet photography of healthy, well-cared-for senior {$breed} at {$age3YearsLater} years old.";

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
