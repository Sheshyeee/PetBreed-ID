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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 300;
  public $tries = 1;

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
      Log::info("=== GENERATING AGE SIMULATIONS (PARALLEL) ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);

      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      // Prepare image ONCE
      Log::info("→ Preparing original image...");
      $imageData = $this->prepareOriginalImage($this->imagePath);

      if (!$imageData) {
        throw new \Exception("Failed to load original image");
      }

      // Extract features ONCE
      $dogFeatures = $this->extractDogFeatures($this->imagePath, $this->breed);
      Log::info("✓ Dog features extracted", $dogFeatures);

      // Initialize simulation data
      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $dogFeatures
      ];

      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Status updated to 'generating'");

      // Extract features with defaults (SAME AS ORIGINAL)
      $coatColor = $dogFeatures['coat_color'] ?? 'brown';
      $coatPattern = $dogFeatures['coat_pattern'] ?? 'solid';
      $coatLength = $dogFeatures['coat_length'] ?? 'medium';
      $coatTexture = $dogFeatures['coat_texture'] ?? 'smooth';
      $build = $dogFeatures['build'] ?? 'medium';
      $estimatedAge = $dogFeatures['estimated_age'] ?? 'young adult';
      $distinctiveMarkings = $dogFeatures['distinctive_markings'] ?? 'none';
      $earType = $dogFeatures['ear_type'] ?? 'unknown';
      $eyeColor = $dogFeatures['eye_color'] ?? 'brown';

      // Convert estimated age to numeric years (SAME AS ORIGINAL)
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

      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1year={$age1YearLater}y, +3years={$age3YearsLater}y");

      $breed = $this->breed;

      // AGING CHANGES FUNCTION (SAME AS ORIGINAL - KEEPING YOUR EXACT PROMPTS)
      $getAgingChanges = function ($ageYears) use ($currentAgeYears, $breed) {
        $yearsOlder = $ageYears - $currentAgeYears;

        $changes = [];

        if ($ageYears < 2) {
          $changes[] = "extremely youthful puppy appearance with very bright, wide, sparkling eyes radiating pure energy";
          $changes[] = "coat at absolute peak shine - glossy, vibrant, full, and perfectly healthy";
          $changes[] = "perfectly tight skin with zero wrinkles, sagging, or loose areas anywhere";
          $changes[] = "highly energetic, playful, alert expression with perked features";
          $changes[] = "face looks fresh, smooth, and youthful";
        } elseif ($ageYears < 5) {
          $changes[] = "strong prime adult {$breed} with fully developed features and athletic build";
          $changes[] = "coat still very healthy with good shine but not puppy-level glossy";
          $changes[] = "eyes bright and alert but more mature, less sparkly than puppy";
          $changes[] = "confident, focused expression with well-defined facial muscles";
          $changes[] = "slight maturity visible in face compared to puppy stage";
        } elseif ($ageYears < 8) {
          $changes[] = "CLEARLY AGED mature {$breed} - face shows OBVIOUS aging compared to young adult";
          $changes[] = "coat NOTICEABLY duller, rougher texture, losing shine significantly, appears somewhat dry or coarse";
          $changes[] = "eyes developing VISIBLE cloudiness or haziness, losing the bright clarity, appearing tired or softer";
          $changes[] = "VISIBLE skin loosening especially around jowls, under eyes, and mouth area creating gentle sagging";
          $changes[] = "facial expression much calmer, tired, less energetic - shows clear maturity and weariness";
          $changes[] = "coat appears thinner in places, less full and voluminous than prime years";
          $changes[] = "overall face has weathered, lived-in appearance - clearly not a young dog anymore";
          $changes[] = "body looks less toned, slightly heavier or saggier posture";
        } else {
          $changes[] = "HEAVILY AGED senior {$breed} with DRAMATICALLY visible aging throughout entire appearance";
          $changes[] = "coat SEVERELY dulled - rough, coarse, thin, patchy, completely lost youthful shine and health";
          $changes[] = "eyes VERY cloudy and hazy with cataract-like milky appearance, looking tired and aged";
          $changes[] = "PRONOUNCED facial sagging - jowls drooping significantly, loose skin under eyes and around mouth";
          $changes[] = "facial features deeply relaxed and droopy, showing extreme tiredness and age";
          $changes[] = "coat thinning extensively revealing more skin, possible bald patches or sparse areas";
          $changes[] = "entire face has heavily weathered, worn appearance of a very old dog";
          $changes[] = "body appears significantly less muscular, sagging posture, low energy stance";
          $changes[] = "overall appearance screams 'senior dog' - unmistakably old and aged";
        }

        return implode('. ', $changes);
      };

      // ============================================================
      // MAIN CHANGE: GENERATE BOTH IMAGES IN PARALLEL (NOT SEQUENTIAL)
      // ============================================================

      Log::info("=== Starting PARALLEL generation of both images ===");

      // Build prompts (KEEPING YOUR EXACT ORIGINAL PROMPTS)
      $prompt1Year = "Transform this {$breed} dog to show DRAMATIC VISIBLE AGING to {$age1YearLater} years old. This must look NOTICEABLY OLDER. "
        . "PRESERVE COMPLETELY: The dog's unique identity, facial structure, exact coat colors and patterns ({$coatColor} {$coatPattern}), "
        . "distinctive markings ({$distinctiveMarkings}), ear shape ({$earType}), same pose and background. "
        . "APPLY THESE INTENSE AGING CHANGES for a {$breed}: {$getAgingChanges($age1YearLater)}. "
        . "CRITICAL AGING EFFECTS TO APPLY: Significantly reduce coat shine and gloss - make it look duller, rougher, less healthy. "
        . "Make eyes appear cloudier, less bright, more tired. Add visible skin loosening around face - slight sagging jowls and under eyes. "
        . "Change facial expression to look older, calmer, more tired, less energetic. Reduce muscle definition. "
        . "The transformation MUST be OBVIOUS when compared side-by-side with original - viewers should immediately see this dog is older. "
        . "Do NOT add gray/white fur unless natural for {$breed}. Focus on: duller coat, cloudier eyes, sagging skin, tired expression. "
        . "Professional realistic pet photography showing clear age progression.";

      $prompt3Years = "Transform this {$breed} dog to show EXTREME DRAMATIC AGING to {$age3YearsLater} years old. This must look SIGNIFICANTLY OLDER - a MAJOR transformation. "
        . "PRESERVE COMPLETELY: The dog's unique identity, facial structure, exact coat colors and patterns ({$coatColor} {$coatPattern}), "
        . "distinctive markings ({$distinctiveMarkings}), ear shape ({$earType}), same pose and background. "
        . "APPLY THESE EXTREME AGING CHANGES for a {$breed}: {$getAgingChanges($age3YearsLater)}. "
        . "CRITICAL INTENSE AGING EFFECTS: Make coat HEAVILY dulled - rough, coarse, thin, patchy, completely lost shine. "
        . "Make eyes VERY cloudy and hazy with visible cataract-like milkiness. Add PRONOUNCED facial sagging - drooping jowls, loose skin under eyes and mouth. "
        . "Expression must look tired, weary, aged - NOT energetic. Face should appear weathered and worn. Reduce muscle tone significantly. "
        . "Thin out the coat visibly, show patches or sparse areas. Make the overall appearance scream 'old senior dog'. "
        . "The transformation MUST be EXTREME and UNMISTAKABLE - side-by-side with original should show shocking age difference. "
        . "Do NOT add gray/white fur unless natural for {$breed}. Focus on: severely dulled rough coat, very cloudy eyes, heavy facial sagging, exhausted expression, thin patchy fur. "
        . "Professional realistic pet photography showing severe age progression - this dog is clearly MUCH older.";

      // Create promises for PARALLEL execution
      $promises = [
        '1_year' => $this->generateImageAsync($prompt1Year, $imageData),
        '3_years' => $this->generateImageAsync($prompt3Years, $imageData)
      ];

      // Wait for BOTH to complete
      $results = Promise\Utils::settle($promises)->wait();

      // Process 1-year result
      if ($results['1_year']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['1_year']['value'];
          $simulationFilename = "simulation_1_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";
          Storage::disk('object-storage')->put($simulationPath, $imageOutput);
          $simulationData['1_years'] = $simulationPath;
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ Generated 1_years: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Failed to save 1_years: " . $e->getMessage());
          $simulationData['error_1year'] = 'Failed to save: ' . $e->getMessage();
        }
      } else {
        Log::error("Failed 1_years generation: " . $results['1_year']['reason']->getMessage());
        $simulationData['error_1year'] = 'Generation failed: ' . $results['1_year']['reason']->getMessage();
      }

      // Process 3-year result
      if ($results['3_years']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['3_years']['value'];
          $simulationFilename = "simulation_3_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";
          Storage::disk('object-storage')->put($simulationPath, $imageOutput);
          $simulationData['3_years'] = $simulationPath;
          Log::info("✓ Generated 3_years: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Failed to save 3_years: " . $e->getMessage());
          $simulationData['error_3year'] = 'Failed to save: ' . $e->getMessage();
        }
      } else {
        Log::error("Failed 3_years generation: " . $results['3_years']['reason']->getMessage());
        $simulationData['error_3year'] = 'Generation failed: ' . $results['3_years']['reason']->getMessage();
      }

      // Mark as complete
      $simulationData['status'] = 'complete';
      $result->update(['simulation_data' => json_encode($simulationData)]);

      Log::info("✓ Final status: complete");
      Log::info("✓ PARALLEL generation finished - 1yr=" . ($simulationData['1_years'] ? 'YES' : 'NO') . ", 3yr=" . ($simulationData['3_years'] ? 'YES' : 'NO'));
    } catch (\Exception $e) {
      Log::error("Age simulation job FAILED: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());

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
   * NEW: Generate image asynchronously using Guzzle Promise
   * This allows parallel execution
   */
  private function generateImageAsync($prompt, $imageData)
  {
    $apiKey = config('services.gemini.api_key');
    if (!$apiKey) {
      $apiKey = env('GEMINI_API_KEY');
    }

    if (!$apiKey) {
      throw new \Exception("Gemini API key not configured");
    }

    $modelName = "nano-banana-pro-preview";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $client = new Client([
      'timeout' => 120,
      'connect_timeout' => 10,
    ]);

    $payload = [
      'contents' => [
        [
          'parts' => [
            ['text' => $prompt],
            [
              'inlineData' => [
                'mimeType' => $imageData['mimeType'],
                'data' => $imageData['base64']
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
      ]
    ];

    // Return a PROMISE instead of waiting for result
    return $client->postAsync($endpoint, [
      'json' => $payload,
      'headers' => ['Content-Type' => 'application/json']
    ])->then(function ($response) {
      $responseBody = $response->getBody()->getContents();
      $responseData = json_decode($responseBody, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Failed to parse API response");
      }

      // Extract image data
      if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        return base64_decode($base64Image);
      } elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['text'];
        $base64Image = preg_replace('/```[\w]*\n?/', '', $base64Image);
        $base64Image = trim($base64Image);
        return base64_decode($base64Image);
      } else {
        throw new \Exception("No image data in response");
      }
    });
  }

  // ============================================================
  // EVERYTHING BELOW IS EXACTLY THE SAME AS YOUR ORIGINAL CODE
  // ============================================================

  private function prepareOriginalImage($fullPath)
  {
    try {
      Log::info("Preparing original image from path: {$fullPath}");

      $imageContents = Storage::disk('object-storage')->get($fullPath);

      if ($imageContents === false || empty($imageContents)) {
        throw new \Exception('Failed to download image from object storage or image is empty');
      }

      Log::info("Image downloaded, size: " . strlen($imageContents) . " bytes");

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      Log::info("Image validated: {$imageInfo[0]}x{$imageInfo[1]}, type: {$imageInfo['mime']}");

      $mimeType = $imageInfo['mime'];

      $supportedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (!in_array($mimeType, $supportedMimes)) {
        Log::warning("Unsupported image type {$mimeType}, converting to JPEG");

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

      $imageData = base64_encode($imageContents);

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
        throw new \Exception('Failed to download image from object storage or image is empty');
      }

      Log::info('✓ Image downloaded from object storage', [
        'file_size' => strlen($imageContents)
      ]);

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Downloaded content is not a valid image');
      }

      $mimeType = $imageInfo['mime'];

      Log::info("Image validated for feature extraction: {$imageInfo[0]}x{$imageInfo[1]}, type: {$mimeType}");

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

      $apiKey = config('services.gemini.api_key');
      if (empty($apiKey)) {
        Log::error('✗ Gemini API key not configured');
        return $dogFeatures;
      }

      $client = new Client();
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

      Log::info('✓ Dog features extracted successfully with Gemini');
    } catch (\Exception $e) {
      Log::error("Feature extraction failed: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());
    }

    return $dogFeatures;
  }
}
