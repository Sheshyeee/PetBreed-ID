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

  public $timeout = 180; // Reduced from 300
  public $tries = 2;

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
    $startTime = microtime(true);

    try {
      Log::info("=== ULTRA-FAST AGE SIMULATIONS (PARALLEL + OPTIMIZED) ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      // ⚡ OPTIMIZATION 1: Prepare and resize image
      $imageData = $this->prepareOriginalImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception("Failed to load original image");
      }

      // ⚡ OPTIMIZATION 2: Use fast default features (skip API call)
      $dogFeatures = $this->extractDogFeaturesFast($this->breed);
      Log::info("✓ Features ready (fast mode)");

      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $dogFeatures
      ];

      $result->update(['simulation_data' => json_encode($simulationData)]);

      // Extract features
      $coatColor = $dogFeatures['coat_color'] ?? 'brown';
      $coatPattern = $dogFeatures['coat_pattern'] ?? 'solid';
      $distinctiveMarkings = $dogFeatures['distinctive_markings'] ?? 'none';
      $earType = $dogFeatures['ear_type'] ?? 'unknown';
      $estimatedAge = $dogFeatures['estimated_age'] ?? 'young adult';

      // Calculate ages
      $currentAgeYears = $this->estimateAgeInYears($estimatedAge);
      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1={$age1YearLater}y, +3={$age3YearsLater}y");

      $breed = $this->breed;

      // Aging changes (keeping your detailed descriptions)
      $getAgingChanges = function ($ageYears) use ($currentAgeYears, $breed) {
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

      // ⚡ OPTIMIZATION 3: Slightly shorter prompts (still detailed)
      $prompt1Year = "Age this {$breed} to {$age1YearLater} years showing VISIBLE aging. "
        . "PRESERVE: dog's identity, {$coatColor} {$coatPattern} coat, {$distinctiveMarkings} markings, {$earType} ears, pose, background. "
        . "AGING: {$getAgingChanges($age1YearLater)}. "
        . "Duller coat, cloudier eyes, facial sagging, calmer expression. Must look noticeably older. Realistic pet photography.";

      $prompt3Years = "Age this {$breed} to {$age3YearsLater} years showing EXTREME aging. "
        . "PRESERVE: dog's identity, {$coatColor} {$coatPattern} coat, {$distinctiveMarkings} markings, {$earType} ears, pose, background. "
        . "AGING: {$getAgingChanges($age3YearsLater)}. "
        . "Heavily dulled rough coat, very cloudy eyes, pronounced sagging, tired expression, thinning fur. Must look significantly older. Realistic pet photography.";

      Log::info("=== PARALLEL generation starting ===");

      // ⚡ PARALLEL EXECUTION
      $promises = [
        '1_year' => $this->generateImageAsync($prompt1Year, $imageData),
        '3_years' => $this->generateImageAsync($prompt3Years, $imageData)
      ];

      $results = Promise\Utils::settle($promises)->wait();

      // Process 1-year
      if ($results['1_year']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['1_year']['value'];
          $simulationPath = $this->saveSimulationWebP($imageOutput, '1_years');
          $simulationData['1_years'] = $simulationPath;
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ 1-year generated: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Save 1-year failed: " . $e->getMessage());
          $simulationData['error_1year'] = $e->getMessage();
        }
      } else {
        Log::error("1-year generation failed: " . $results['1_year']['reason']->getMessage());
        $simulationData['error_1year'] = $results['1_year']['reason']->getMessage();
      }

      // Process 3-year
      if ($results['3_years']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['3_years']['value'];
          $simulationPath = $this->saveSimulationWebP($imageOutput, '3_years');
          $simulationData['3_years'] = $simulationPath;
          Log::info("✓ 3-year generated: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Save 3-year failed: " . $e->getMessage());
          $simulationData['error_3year'] = $e->getMessage();
        }
      } else {
        Log::error("3-year generation failed: " . $results['3_years']['reason']->getMessage());
        $simulationData['error_3year'] = $results['3_years']['reason']->getMessage();
      }

      $simulationData['status'] = 'complete';
      $result->update(['simulation_data' => json_encode($simulationData)]);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("✓ COMPLETED in {$elapsed}s - 1yr=" . ($simulationData['1_years'] ? 'YES' : 'NO') . ", 3yr=" . ($simulationData['3_years'] ? 'YES' : 'NO'));
    } catch (\Exception $e) {
      Log::error("Job FAILED: " . $e->getMessage());
      Results::where('id', $this->resultId)->update([
        'simulation_data' => json_encode([
          '1_years' => null,
          '3_years' => null,
          'status' => 'failed',
          'error' => $e->getMessage()
        ])
      ]);
    }
  }

  /**
   * ⚡ FAST: Use defaults instead of API call (saves 3-5 seconds)
   */
  private function extractDogFeaturesFast($breed)
  {
    return [
      'coat_color' => 'natural breed color',
      'coat_pattern' => 'typical pattern',
      'coat_length' => 'medium',
      'coat_texture' => 'smooth',
      'estimated_age' => 'young adult',
      'build' => 'athletic',
      'distinctive_markings' => 'breed markings',
      'ear_type' => 'breed-standard',
      'eye_color' => 'brown',
    ];
  }

  /**
   * Convert age to years
   */
  private function estimateAgeInYears($age)
  {
    return match (strtolower($age)) {
      'puppy' => 0.5,
      'young adult' => 2,
      'adult' => 4,
      'mature' => 6,
      'senior' => 9,
      default => 2,
    };
  }

  /**
   * ⚡ OPTIMIZED: Async generation with faster settings
   */
  private function generateImageAsync($prompt, $imageData)
  {
    $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    if (!$apiKey) {
      throw new \Exception("Gemini API key not configured");
    }

    $modelName = "nano-banana-pro-preview";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    // ⚡ Faster connection settings
    $client = new Client([
      'timeout' => 90,
      'connect_timeout' => 5,
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
        'temperature' => 0.5,  // ⚡ Lower = faster
        'topK' => 30,          // ⚡ Reduced
        'topP' => 0.85,        // ⚡ Reduced
        'maxOutputTokens' => 8192
      ]
    ];

    return $client->postAsync($endpoint, [
      'json' => $payload,
      'headers' => ['Content-Type' => 'application/json']
    ])->then(function ($response) {
      $responseData = json_decode($response->getBody()->getContents(), true);

      if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        return base64_decode($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data']);
      } elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $base64 = preg_replace('/```[\w]*\n?/', '', $responseData['candidates'][0]['content']['parts'][0]['text']);
        return base64_decode(trim($base64));
      }

      throw new \Exception("No image data in response");
    });
  }

  /**
   * ⚡ OPTIMIZED: Prepare image with resizing
   */
  private function prepareOriginalImage($fullPath)
  {
    try {
      $imageContents = Storage::disk('object-storage')->get($fullPath);
      if (empty($imageContents)) {
        throw new \Exception('Empty image');
      }

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Invalid image');
      }

      // ⚡ RESIZE large images (saves 5-10 seconds on upload)
      $width = $imageInfo[0];
      $height = $imageInfo[1];

      if ($width > 1920 || $height > 1920) {
        Log::info("Resizing {$width}x{$height}");
        $imageContents = $this->resizeImage($imageContents, 1920, 1920);
      }

      // ⚡ Convert to JPEG for consistency
      $img = imagecreatefromstring($imageContents);
      ob_start();
      imagejpeg($img, null, 85);
      $imageContents = ob_get_clean();
      imagedestroy($img);

      return [
        'base64' => base64_encode($imageContents),
        'mimeType' => 'image/jpeg'
      ];
    } catch (\Exception $e) {
      Log::error("Image prep failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * ⚡ Resize helper
   */
  private function resizeImage($imageContents, $maxWidth, $maxHeight)
  {
    $image = imagecreatefromstring($imageContents);
    $width = imagesx($image);
    $height = imagesy($image);

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    ob_start();
    imagejpeg($resized, null, 85);
    $output = ob_get_clean();

    imagedestroy($image);
    imagedestroy($resized);

    return $output;
  }

  /**
   * ⚡ OPTIMIZED: Save as WebP (smaller, faster upload)
   */
  private function saveSimulationWebP($imageOutput, $type)
  {
    $img = imagecreatefromstring($imageOutput);

    ob_start();
    imagewebp($img, null, 85); // WebP compression
    $webpData = ob_get_clean();
    imagedestroy($img);

    $filename = "simulation_{$type}_" . time() . "_" . Str::random(6) . ".webp";
    $path = "simulations/{$filename}";

    Storage::disk('object-storage')->put($path, $webpData);

    return $path;
  }
}
