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
      Log::info("=== GENERATING AGE SIMULATIONS ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);

      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      // EXTRACT DOG FEATURES AND PREPARE ORIGINAL IMAGE
      Log::info("→ Preparing original image...");
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
      $distinctiveMarkings = $dogFeatures['distinctive_markings'] ?? 'none';
      $earType = $dogFeatures['ear_type'] ?? 'unknown';
      $estimatedAge = $dogFeatures['estimated_age'] ?? 'young adult';

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

      // GENERATE 1-YEAR IMAGE
      try {
        Log::info("=== Generating 1_years simulation ===");

        $prompt1Year = "A professional photo of a {$this->breed} dog that is {$age1YearLater} years old. "
          . "The dog has {$coatColor} coat with {$coatPattern} pattern";

        if ($distinctiveMarkings !== 'none') {
          $prompt1Year .= ", {$distinctiveMarkings}";
        }

        $prompt1Year .= ", {$earType} ears. ";

        // Add age-appropriate characteristics
        if ($age1YearLater < 3) {
          $prompt1Year .= "The dog shows youthful energy with bright eyes, glossy coat, playful expression, "
            . "and well-developed young adult muscles. ";
        } elseif ($age1YearLater < 6) {
          $prompt1Year .= "The dog shows mature adult characteristics with peak physical condition, "
            . "fully developed muscles, alert expression, and a few subtle gray hairs starting on the muzzle. ";
        } else {
          $prompt1Year .= "The dog shows aging characteristics with noticeable gray hairs on muzzle and around eyes, "
            . "slightly duller coat, wise calm expression, and minor graying on eyebrows. ";
        }

        $prompt1Year .= "High quality pet photography, well-lit, professional studio quality.";

        Log::info("Prompt 1-year: " . substr($prompt1Year, 0, 200) . "...");

        // Generate image
        $imageOutput = $this->generateImageWithGemini($prompt1Year);

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
        $simulationData['error_1year'] = 'Generation failed: ' . $e->getMessage();

        // Update DB with error
        $result->update(['simulation_data' => json_encode($simulationData)]);
      }

      // GENERATE 3-YEAR IMAGE (NO SLEEP)
      try {
        Log::info("=== Generating 3_years simulation ===");

        $prompt3Years = "A professional photo of a {$this->breed} dog that is {$age3YearsLater} years old. "
          . "The dog has {$coatColor} coat with {$coatPattern} pattern";

        if ($distinctiveMarkings !== 'none') {
          $prompt3Years .= ", {$distinctiveMarkings}";
        }

        $prompt3Years .= ", {$earType} ears. ";

        // Add MORE dramatic age-appropriate characteristics
        if ($age3YearsLater < 4) {
          $prompt3Years .= "The dog shows full adult maturity with powerful muscles, "
            . "confident posture, peak physical condition, and vibrant healthy appearance. ";
        } elseif ($age3YearsLater < 8) {
          $prompt3Years .= "The dog shows clear aging with prominent gray/white hairs covering the entire muzzle, "
            . "gray fur forming a mask around both eyes, white eyebrows, some graying on chin and chest, "
            . "coat that is duller and less shiny, and a wise calm expression. ";
        } else {
          $prompt3Years .= "The dog shows advanced senior aging with extensive gray/white fur on entire face, "
            . "completely white/gray muzzle, gray mask around eyes, white eyebrows, "
            . "significant graying on chest and front legs, noticeably duller and coarser coat, "
            . "gentle senior expression, slight cloudiness in eyes, and relaxed elderly demeanor. ";
        }

        $prompt3Years .= "High quality pet photography, well-lit, professional studio quality.";

        Log::info("Prompt 3-year: " . substr($prompt3Years, 0, 200) . "...");

        // Generate image
        $imageOutput = $this->generateImageWithGemini($prompt3Years);

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
        $simulationData['error_3year'] = 'Generation failed: ' . $e->getMessage();
      }

      // Mark as complete
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
   * Prepare original image (optimized for reliability)
   * ==========================================
   */
  private function prepareOriginalImage($fullPath)
  {
    try {
      Log::info("Preparing original image from path: {$fullPath}");

      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false || empty($imageContents)) {
        throw new \Exception('Failed to download image from object storage');
      }

      Log::info("Image downloaded, size: " . strlen($imageContents) . " bytes");

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      Log::info("Image validated: {$imageInfo[0]}x{$imageInfo[1]}, type: {$imageInfo['mime']}");

      $mimeType = $imageInfo['mime'];

      // Resize if too large
      $maxDimension = 1024;
      if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension) {
        Log::info("Resizing image for API compatibility...");

        $img = imagecreatefromstring($imageContents);
        if ($img !== false) {
          $ratio = min($maxDimension / $imageInfo[0], $maxDimension / $imageInfo[1]);
          $newWidth = (int)($imageInfo[0] * $ratio);
          $newHeight = (int)($imageInfo[1] * $ratio);

          $resized = imagecreatetruecolor($newWidth, $newHeight);
          imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $imageInfo[0], $imageInfo[1]);

          ob_start();
          imagejpeg($resized, null, 75);
          $imageContents = ob_get_clean();

          imagedestroy($img);
          imagedestroy($resized);

          $mimeType = 'image/jpeg';
          Log::info("Resized to {$newWidth}x{$newHeight}");
        }
      }

      // Convert to JPEG for consistency
      if ($mimeType !== 'image/jpeg') {
        Log::info("Converting to JPEG...");

        $img = imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception("Failed to create image resource");
        }

        ob_start();
        imagejpeg($img, null, 75);
        $imageContents = ob_get_clean();
        imagedestroy($img);

        $mimeType = 'image/jpeg';
      }

      $imageData = base64_encode($imageContents);

      if (empty($imageData)) {
        throw new \Exception('Base64 encoding failed');
      }

      Log::info('✓ Image prepared successfully');

      return [
        'base64' => $imageData,
        'mimeType' => $mimeType
      ];
    } catch (\Exception $e) {
      Log::error("Failed to prepare image: " . $e->getMessage());
      return null;
    }
  }

  /**
   * ==========================================
   * Extract Dog Features using Gemini Vision API
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
        throw new \Exception('Failed to download image');
      }

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Invalid image');
      }

      $mimeType = $imageInfo['mime'];
      $imageData = base64_encode($imageContents);

      $visionPrompt = "Analyze this {$detectedBreed} dog image and provide ONLY a JSON response with these exact keys:

{
  \"coat_color\": \"primary color(s) - be specific\",
  \"coat_pattern\": \"pattern type (solid, spotted, brindle, merle, parti-color, tuxedo, sable)\",
  \"coat_length\": \"length (short, medium, long, curly)\",
  \"coat_texture\": \"texture description\",
  \"estimated_age\": \"age range (puppy, young adult, adult, mature, senior)\",
  \"build\": \"body type\",
  \"distinctive_markings\": \"any unique features\",
  \"ear_type\": \"ear shape (floppy, erect, semi-erect)\",
  \"eye_color\": \"eye color if visible\"
}";

      $apiKey = config('services.gemini.api_key');
      if (empty($apiKey)) {
        Log::error('Gemini API key not configured');
        return $dogFeatures;
      }

      $client = new \GuzzleHttp\Client(['timeout' => 30]);
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

      Log::info('✓ Dog features extracted successfully');
    } catch (\Exception $e) {
      Log::error("Feature extraction failed: " . $e->getMessage());
    }

    return $dogFeatures;
  }

  /**
   * ==========================================
   * FIXED: Generate image using TEXT-TO-IMAGE with Gemini Imagen
   * Uses gemini-2.0-flash-exp-imagen which supports image generation
   * ==========================================
   */
  private function generateImageWithGemini($prompt)
  {
    try {
      $apiKey = config('services.gemini.api_key');
      if (!$apiKey) {
        $apiKey = env('GEMINI_API_KEY');
      }

      if (!$apiKey) {
        throw new \Exception("Gemini API key not configured");
      }

      // CRITICAL FIX: Use the CORRECT model that supports image generation
      // Using gemini-2.5-flash-image (Nano Banana) - supports image generation
      $modelName = "gemini-2.5-flash-image";
      $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

      Log::info("🎨 Calling Gemini Image API (model: {$modelName})...");

      // TEXT-TO-IMAGE payload
      $payload = array(
        'contents' => array(
          array(
            'parts' => array(
              array(
                'text' => $prompt
              )
            )
          )
        ),
        'generationConfig' => array(
          'temperature' => 0.4,
          'topK' => 40,
          'topP' => 0.95,
          'maxOutputTokens' => 8192
        )
      );

      $jsonPayload = json_encode($payload);

      Log::info("📤 Sending request (payload size: " . strlen($jsonPayload) . " bytes)");

      $ch = curl_init($endpoint);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
      ));
      curl_setopt($ch, CURLOPT_TIMEOUT, 90);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

      Log::info("⏳ Waiting for API response...");
      $startTime = microtime(true);

      $responseBody = curl_exec($ch);

      $endTime = microtime(true);
      $duration = round($endTime - $startTime, 2);

      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      $curlErrno = curl_errno($ch);
      curl_close($ch);

      Log::info("📥 Response received in {$duration}s (HTTP {$httpCode})");

      if ($curlError) {
        Log::error("❌ cURL Error #{$curlErrno}: {$curlError}");
        throw new \Exception("cURL error: {$curlError}");
      }

      if ($httpCode !== 200) {
        Log::error("❌ API Error (HTTP {$httpCode})");
        Log::error("Response: " . substr($responseBody, 0, 1000));

        $errorData = json_decode($responseBody, true);
        $errorMessage = $errorData['error']['message'] ?? 'Unknown API error';

        throw new \Exception("API error (HTTP {$httpCode}): {$errorMessage}");
      }

      $responseData = json_decode($responseBody, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        Log::error("❌ JSON decode error: " . json_last_error_msg());
        throw new \Exception("Failed to parse API response");
      }

      Log::info("✅ Response parsed successfully");

      // Extract image from response
      // Try different possible response formats
      if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        Log::info("✓ Image extracted from inlineData");
        return base64_decode($base64Image);
      }

      if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
        // Sometimes the image is embedded in text as base64
        if (preg_match('/data:image\/[^;]+;base64,([A-Za-z0-9+\/=]+)/', $text, $matches)) {
          Log::info("✓ Image extracted from data URI");
          return base64_decode($matches[1]);
        }
        // Or just raw base64
        $base64Image = preg_replace('/```[\w]*\n?/', '', $text);
        $base64Image = trim($base64Image);
        if (strlen($base64Image) > 1000) {
          Log::info("✓ Image extracted from text field");
          return base64_decode($base64Image);
        }
      }

      // Log response structure for debugging
      Log::error("❌ Unexpected response structure");
      Log::error("Response keys: " . json_encode(array_keys($responseData)));
      if (isset($responseData['candidates'][0])) {
        Log::error("Candidate[0] keys: " . json_encode(array_keys($responseData['candidates'][0])));
        if (isset($responseData['candidates'][0]['content'])) {
          Log::error("Content keys: " . json_encode(array_keys($responseData['candidates'][0]['content'])));
        }

        // Check for safety blocks
        if (isset($responseData['candidates'][0]['finishReason'])) {
          $finishReason = $responseData['candidates'][0]['finishReason'];
          Log::error("Finish reason: {$finishReason}");

          if ($finishReason === 'SAFETY') {
            throw new \Exception("Content blocked by safety filters");
          }
        }
      }

      throw new \Exception("No image data found in API response");
    } catch (\Exception $e) {
      Log::error("💥 Image generation FAILED: " . $e->getMessage());
      throw $e;
    }
  }
}
