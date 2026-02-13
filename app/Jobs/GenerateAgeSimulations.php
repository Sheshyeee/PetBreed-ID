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

  public $timeout = 150;
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
      Log::info("=== BREED-SPECIFIC AGE TRANSFORMATIONS ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      $imageData = $this->prepareOriginalImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception("Failed to load original image");
      }

      $breedInfo = $this->analyzeBreedAging($this->breed);
      Log::info("✓ Breed aging profile loaded", $breedInfo);

      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $breedInfo
      ];

      $result->update(['simulation_data' => json_encode($simulationData)]);

      // Build dynamic transformation prompts
      $prompt1Year = $this->buildDynamicAgePrompt($this->breed, 1, $breedInfo);
      $prompt3Years = $this->buildDynamicAgePrompt($this->breed, 3, $breedInfo);

      Log::info("=== PARALLEL generation starting ===");

      $promises = [
        '1_year' => $this->generateImageAsync($prompt1Year, $imageData),
        '3_years' => $this->generateImageAsync($prompt3Years, $imageData)
      ];

      $results = Promise\Utils::settle($promises)->wait();

      // Process results
      if ($results['1_year']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['1_year']['value'];
          $simulationPath = $this->saveSimulationWebP($imageOutput, '1_years');
          $simulationData['1_years'] = $simulationPath;
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ 1-year: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Save 1-year failed: " . $e->getMessage());
          $simulationData['error_1year'] = $e->getMessage();
        }
      } else {
        Log::error("1-year failed: " . $results['1_year']['reason']->getMessage());
        $simulationData['error_1year'] = $results['1_year']['reason']->getMessage();
      }

      if ($results['3_years']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['3_years']['value'];
          $simulationPath = $this->saveSimulationWebP($imageOutput, '3_years');
          $simulationData['3_years'] = $simulationPath;
          Log::info("✓ 3-year: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Save 3-year failed: " . $e->getMessage());
          $simulationData['error_3year'] = $e->getMessage();
        }
      } else {
        Log::error("3-year failed: " . $results['3_years']['reason']->getMessage());
        $simulationData['error_3year'] = $results['3_years']['reason']->getMessage();
      }

      $simulationData['status'] = 'complete';
      $result->update(['simulation_data' => json_encode($simulationData)]);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("✓ COMPLETED in {$elapsed}s");
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
   * Analyze how this specific breed ages naturally
   */
  private function analyzeBreedAging($breed)
  {
    $breedLower = strtolower($breed);

    // Breeds with minimal graying (dark coats stay dark)
    $lowGrayingBreeds = ['rottweiler', 'doberman', 'black lab', 'pug', 'bulldog', 'pitbull', 'staffordshire'];

    // Breeds with heavy coat changes
    $heavyCoatBreeds = ['poodle', 'bichon', 'maltese', 'yorkie', 'shih tzu', 'schnauzer'];

    // Giant breeds (age faster, more body changes)
    $giantBreeds = ['great dane', 'mastiff', 'saint bernard', 'newfoundland', 'leonberger', 'irish wolfhound'];

    // Small breeds (age differently - less size change, more face aging)
    $smallBreeds = ['chihuahua', 'pomeranian', 'yorkshire', 'toy', 'maltese', 'papillon'];

    // Wrinkly breeds (more pronounced sagging)
    $wrinklyBreeds = ['shar pei', 'bulldog', 'mastiff', 'basset hound', 'bloodhound', 'pug'];

    // Determine characteristics
    $hasMinimalGraying = false;
    $hasHeavyCoat = false;
    $isGiant = false;
    $isSmall = false;
    $isWrinkly = false;

    foreach ($lowGrayingBreeds as $b) {
      if (stripos($breedLower, $b) !== false) {
        $hasMinimalGraying = true;
        break;
      }
    }

    foreach ($heavyCoatBreeds as $b) {
      if (stripos($breedLower, $b) !== false) {
        $hasHeavyCoat = true;
        break;
      }
    }

    foreach ($giantBreeds as $b) {
      if (stripos($breedLower, $b) !== false) {
        $isGiant = true;
        break;
      }
    }

    foreach ($smallBreeds as $b) {
      if (stripos($breedLower, $b) !== false) {
        $isSmall = true;
        break;
      }
    }

    foreach ($wrinklyBreeds as $b) {
      if (stripos($breedLower, $b) !== false) {
        $isWrinkly = true;
        break;
      }
    }

    return [
      'minimal_graying' => $hasMinimalGraying,
      'heavy_coat' => $hasHeavyCoat,
      'is_giant' => $isGiant,
      'is_small' => $isSmall,
      'is_wrinkly' => $isWrinkly,
    ];
  }

  /**
   * Build dynamic transformation prompt based on actual breed characteristics
   */
  private function buildDynamicAgePrompt($breed, $yearsAhead, $breedInfo)
  {
    $changes = [];

    if ($yearsAhead === 1) {
      // ========== 1 YEAR TRANSFORMATION (MODERATE AGING) ==========

      // EYES - Universal aging sign for ALL breeds
      $changes[] = "eyes slightly less bright and clear, developing subtle haziness";

      // COAT - Different approaches for different coat types
      if ($breedInfo['heavy_coat']) {
        $changes[] = "coat texture becoming less fluffy/soft, slightly more coarse";
      } else {
        $changes[] = "coat losing 20% of glossy shine, appearing more matte";
      }

      // FACE AGING - Adjusted for breed type
      if ($breedInfo['is_wrinkly']) {
        $changes[] = "existing wrinkles becoming slightly deeper and more pronounced";
      } else {
        $changes[] = "subtle skin loosening around jowls and under eyes";
      }

      // GRAYING - Only for breeds that naturally gray
      if (!$breedInfo['minimal_graying']) {
        $changes[] = "natural age-appropriate color changes on muzzle (as typical for this breed)";
      }

      // EXPRESSION - Universal
      $changes[] = "facial expression calmer, less energetic, more mature";

      // BODY - Size dependent
      if ($breedInfo['is_giant']) {
        $changes[] = "if puppy: significant body mass increase, more muscular build";
      } elseif (!$breedInfo['is_small']) {
        $changes[] = "if puppy: noticeable body development and muscle definition";
      }
    } else {
      // ========== 3 YEARS TRANSFORMATION (DRAMATIC AGING) ==========

      // EYES - Dramatic aging for all breeds
      $changes[] = "eyes noticeably cloudier with visible haziness, appearing older and tired";

      // COAT - Major deterioration
      if ($breedInfo['heavy_coat']) {
        $changes[] = "coat significantly coarser and less voluminous, thinning in areas";
      } else {
        $changes[] = "coat very dull with rough texture, lost youthful luster completely";
      }

      // FACE AGING - Dramatic for all breeds
      if ($breedInfo['is_wrinkly']) {
        $changes[] = "wrinkles MUCH deeper and more extensive, prominent aging throughout face";
      } else {
        $changes[] = "clear facial sagging visible on jowls, under eyes, and around mouth";
      }

      // GRAYING - Breed-specific approach
      if ($breedInfo['minimal_graying']) {
        $changes[] = "coat color naturally darkens/fades as appropriate for aging {$breed}";
      } else {
        $changes[] = "age-appropriate graying pattern typical for older {$breed} (natural placement)";
      }

      // BODY CONDITION - Universal senior signs
      if ($breedInfo['is_giant']) {
        $changes[] = "body less toned, possible weight changes, senior dog posture";
      } elseif ($breedInfo['is_small']) {
        $changes[] = "accelerated aging signs typical for small breeds, senior appearance";
      } else {
        $changes[] = "less muscle definition, slight weight redistribution, aged physique";
      }

      // EXPRESSION - Senior dog demeanor
      $changes[] = "clearly senior dog expression, calm and wise demeanor, noticeably aged";
    }

    $changesList = implode(". ", $changes);

    return "Transform this {$breed} to appear {$yearsAhead} year(s) older. AGING CHANGES REQUIRED: {$changesList}. PRESERVE EXACTLY: same breed identity, coat base color/pattern, distinctive markings, body pose, background environment. Style: natural photorealistic aging, professional pet photography quality.";
  }

  /**
   * Generate image with optimized settings
   */
  private function generateImageAsync($prompt, $imageData)
  {
    $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    if (!$apiKey) {
      throw new \Exception("Gemini API key not configured");
    }

    $modelName = "nano-banana-pro-preview";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $client = new Client([
      'timeout' => 75,
      'connect_timeout' => 3,
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
        'temperature' => 0.65,
        'topK' => 35,
        'topP' => 0.9,
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
   * Prepare image - optimized for speed
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

      $width = $imageInfo[0];
      $height = $imageInfo[1];

      // Optimize size for speed
      $maxSize = 1280;
      if ($width > $maxSize || $height > $maxSize) {
        Log::info("Resizing {$width}x{$height} to {$maxSize}px");
        $imageContents = $this->resizeImage($imageContents, $maxSize, $maxSize);
      }

      // Convert to JPEG
      $img = imagecreatefromstring($imageContents);
      ob_start();
      imagejpeg($img, null, 78);
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
   * Resize helper
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
    imagejpeg($resized, null, 78);
    $output = ob_get_clean();

    imagedestroy($image);
    imagedestroy($resized);

    return $output;
  }

  /**
   * Save as WebP
   */
  private function saveSimulationWebP($imageOutput, $type)
  {
    $img = imagecreatefromstring($imageOutput);

    ob_start();
    imagewebp($img, null, 78);
    $webpData = ob_get_clean();
    imagedestroy($img);

    $filename = "simulation_{$type}_" . time() . "_" . Str::random(6) . ".webp";
    $path = "simulations/{$filename}";

    Storage::disk('object-storage')->put($path, $webpData);

    return $path;
  }
}
