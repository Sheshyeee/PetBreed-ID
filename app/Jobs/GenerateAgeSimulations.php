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

      // Extract dog features
      Log::info("→ Extracting dog features from image...");
      $dogFeatures = $this->extractDogFeatures($this->imagePath, $this->breed);
      Log::info("✓ Dog features extracted", $dogFeatures);

      // Initialize simulation data
      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $dogFeatures
      ];

      // Update status to generating
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Status updated to 'generating'");

      // Get the original image as base64
      $originalImageBase64 = $this->getOriginalImageBase64($this->imagePath);

      if (!$originalImageBase64) {
        throw new \Exception("Failed to load original image");
      }

      // Extract features with defaults
      $coatColor = $dogFeatures['coat_color'] ?? 'brown';
      $coatPattern = $dogFeatures['coat_pattern'] ?? 'solid';
      $coatLength = $dogFeatures['coat_length'] ?? 'medium';
      $build = $dogFeatures['build'] ?? 'medium';
      $estimatedAge = $dogFeatures['estimated_age'] ?? 'young adult';
      $distinctiveMarkings = $dogFeatures['distinctive_markings'] ?? 'none';
      $earType = $dogFeatures['ear_type'] ?? 'unknown';

      // Convert estimated age to numeric years
      $currentAgeYears = $this->convertAgeToYears($estimatedAge);
      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1year={$age1YearLater}y, +3years={$age3YearsLater}y");

      // GENERATE 1-YEAR IMAGE
      try {
        Log::info("=== Generating 1_years simulation ===");

        $prompt1Year = $this->buildAgingPrompt(
          $this->breed,
          $coatColor,
          $coatPattern,
          $coatLength,
          $build,
          $earType,
          $distinctiveMarkings,
          $age1YearLater
        );

        Log::info("Prompt 1-year: " . $prompt1Year);

        // Call Gemini with original image for reference
        $imageData = $this->generateImageWithGemini($prompt1Year, $originalImageBase64);

        if ($imageData) {
          $simulationFilename = "simulation_1_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $imageData);
          $simulationData['1_years'] = $simulationPath;

          // Update database immediately
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ Generated 1_years: {$simulationPath}");
        } else {
          throw new \Exception("Failed to generate image data");
        }
      } catch (\Exception $e) {
        Log::error("Failed 1_years simulation: " . $e->getMessage());
        $simulationData['1_years'] = null;
        $simulationData['error_1year'] = 'Generation failed: ' . $e->getMessage();
      }

      // Wait between API calls
      sleep(3);

      // GENERATE 3-YEAR IMAGE
      try {
        Log::info("=== Generating 3_years simulation ===");

        $prompt3Years = $this->buildAgingPrompt(
          $this->breed,
          $coatColor,
          $coatPattern,
          $coatLength,
          $build,
          $earType,
          $distinctiveMarkings,
          $age3YearsLater
        );

        Log::info("Prompt 3-year: " . $prompt3Years);

        // Call Gemini with original image for reference
        $imageData = $this->generateImageWithGemini($prompt3Years, $originalImageBase64);

        if ($imageData) {
          $simulationFilename = "simulation_3_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $imageData);
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
   * Build aging prompt based on target age
   */
  private function buildAgingPrompt($breed, $coatColor, $coatPattern, $coatLength, $build, $earType, $distinctiveMarkings, $targetAge)
  {
    // Determine aging characteristics based on target age
    $agingDescription = $this->getAgingDescription($targetAge);

    // Build comprehensive prompt for image-to-image generation
    $prompt = "Generate an image of this exact same {$breed} dog, keeping ALL identifying features identical: ";
    $prompt .= "same {$coatColor} {$coatPattern} {$coatLength} coat, ";
    $prompt .= "same {$build} build, ";
    $prompt .= "same {$earType} ears, ";

    if ($distinctiveMarkings !== 'none') {
      $prompt .= "same distinctive markings ({$distinctiveMarkings}), ";
    }

    $prompt .= "same pose and angle as the reference image. ";
    $prompt .= "CRITICAL: The dog must be EXACTLY the same individual, just aged. ";
    $prompt .= "Apply these aging changes ONLY: {$agingDescription}. ";
    $prompt .= "Keep the background, lighting, and composition similar. Professional pet photography quality.";

    return $prompt;
  }

  /**
   * Get aging description based on target age in years
   */
  private function getAgingDescription($ageYears)
  {
    if ($ageYears <= 1.5) {
      // Puppy to young adult
      return "slightly more developed face, fuller coat, more alert eyes, youthful energy maintained";
    } elseif ($ageYears <= 3) {
      // Young adult
      return "fully mature adult features, coat at peak condition, confident expression, no gray yet";
    } elseif ($ageYears <= 5) {
      // Prime adult
      return "strong adult features, healthy coat, wise expression, possible very slight gray starting around muzzle";
    } elseif ($ageYears <= 7) {
      // Mature
      return "light gray/white fur developing around muzzle and eyebrows, slightly calmer expression, coat still healthy";
    } elseif ($ageYears <= 10) {
      // Senior
      return "noticeable gray/white fur on muzzle, eyebrows, and around eyes, softer expression, gentle graying on face, mature wisdom in eyes";
    } else {
      // Very senior
      return "significant gray/white fur on face and muzzle, cloudy or wise eyes, calmer demeanor, visible aging on face, dignified senior appearance";
    }
  }

  /**
   * Convert age description to numeric years
   */
  private function convertAgeToYears($estimatedAge)
  {
    $estimatedAgeLower = strtolower($estimatedAge);

    return match ($estimatedAgeLower) {
      'puppy' => 0.5,
      'young adult' => 2,
      'adult' => 4,
      'mature' => 6,
      'senior' => 9,
      default => 2,
    };
  }

  /**
   * Get original image as base64
   */
  private function getOriginalImageBase64($imagePath)
  {
    try {
      $imageContents = Storage::disk('object-storage')->get($imagePath);

      if ($imageContents === false) {
        Log::error("Failed to get image from object storage: {$imagePath}");
        return null;
      }

      return base64_encode($imageContents);
    } catch (\Exception $e) {
      Log::error("Error getting original image: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Extract Dog Features using Gemini API
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
      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false) {
        throw new \Exception('Failed to download image from object storage');
      }

      $tempPath = tempnam(sys_get_temp_dir(), 'feature_extract_');
      $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
      $tempPathWithExt = $tempPath . '.' . $extension;
      rename($tempPath, $tempPathWithExt);

      file_put_contents($tempPathWithExt, $imageContents);

      Log::info('✓ Image downloaded from object storage to temp file');

      $imageData = base64_encode($imageContents);
      $mimeType = mime_content_type($tempPathWithExt);

      $visionPrompt = "Analyze this {$detectedBreed} dog image and provide ONLY a JSON response with these exact keys:

{
  \"coat_color\": \"primary color(s) - be specific (e.g., 'golden', 'black and tan', 'white with brown patches', 'brindle')\",
  \"coat_pattern\": \"pattern type (solid, spotted, brindle, merle, parti-color, tuxedo, sable)\",
  \"coat_length\": \"length (short, medium, long, curly)\",
  \"coat_texture\": \"texture description (silky, wiry, fluffy, smooth)\",
  \"estimated_age\": \"age range (puppy, young adult, adult, mature, senior) based on face, eyes, and coat\",
  \"build\": \"body type (lean, athletic, stocky, muscular, compact, large)\",
  \"distinctive_markings\": \"any unique features visible (facial markings, chest patches, eyebrow marks, ear color, tail characteristics, spots, stripes, etc.)\",
  \"ear_type\": \"ear shape (floppy, erect, semi-erect)\",
  \"eye_color\": \"eye color if visible (brown, blue, amber, heterochromic)\",
  \"size_estimate\": \"size category (toy, small, medium, large, giant)\"
}

Be very detailed and specific about ALL visible features, colors, and patterns.";

      $apiKey = config('services.gemini.api_key');
      if (empty($apiKey)) {
        Log::error('✗ Gemini API key not configured');
        return $dogFeatures;
      }

      $client = new \GuzzleHttp\Client();
      $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey, [
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

      Log::info('✓ Dog features extracted successfully');
    } catch (\Exception $e) {
      Log::error("Feature extraction failed: " . $e->getMessage());
    }

    return $dogFeatures;
  }

  /**
   * Generate image using Gemini Nano Banana Pro with reference image
   */
  private function generateImageWithGemini($prompt, $referenceImageBase64)
  {
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

      Log::info("Calling Gemini Nano Banana Pro API with reference image...");

      // Include reference image in the request for image-to-image generation
      $payload = array(
        'contents' => array(
          array(
            'parts' => array(
              array(
                'text' => "REFERENCE IMAGE: This is the original dog photo. " . $prompt
              ),
              array(
                'inlineData' => array(
                  'mimeType' => 'image/png',
                  'data' => $referenceImageBase64
                )
              )
            )
          )
        ),
        'generationConfig' => array(
          'temperature' => 0.4,  // Lower temperature for more faithful reproduction
          'topK' => 40,
          'topP' => 0.95,
          'maxOutputTokens' => 8192
        )
      );

      $jsonPayload = json_encode($payload);

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
        $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
        throw new \Exception("Gemini API returned error (HTTP {$httpCode}): {$errorMessage}");
      }

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
