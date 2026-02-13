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

  public $timeout = 180;
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
      Log::info("=== DRAMATIC AGE SIMULATIONS (ULTRA-FAST + BREED-AWARE) ===");
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

      // ⚡ OPTIMIZATION 2: Get breed-specific growth data
      $breedData = $this->getBreedCharacteristics($this->breed);
      Log::info("✓ Breed characteristics loaded", $breedData);

      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $breedData
      ];

      $result->update(['simulation_data' => json_encode($simulationData)]);

      // Calculate ages based on current appearance
      $currentAgeYears = $breedData['estimated_age_years'];
      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1={$age1YearLater}y, +3={$age3YearsLater}y");

      $breed = $this->breed;

      // ⚡ OPTIMIZED PROMPTS - Shorter but DRAMATICALLY descriptive
      $prompt1Year = $this->buildDramaticPrompt($breed, $currentAgeYears, $age1YearLater, $breedData);
      $prompt3Years = $this->buildDramaticPrompt($breed, $currentAgeYears, $age3YearsLater, $breedData);

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
   * ⚡ BREED-AWARE: Get characteristics for dramatic transformations
   */
  private function getBreedCharacteristics($breed)
  {
    $breedLower = strtolower($breed);

    // Determine size category and growth potential
    $giantBreeds = ['great dane', 'mastiff', 'saint bernard', 'newfoundland', 'leonberger', 'irish wolfhound'];
    $largeBreeds = ['german shepherd', 'golden retriever', 'labrador', 'rottweiler', 'doberman', 'boxer'];
    $mediumBreeds = ['beagle', 'bulldog', 'cocker spaniel', 'border collie', 'australian shepherd', 'husky', 'aspin'];
    $smallBreeds = ['chihuahua', 'pomeranian', 'yorkshire terrier', 'shih tzu', 'pug', 'dachshund', 'maltese'];

    $isGiant = false;
    $isLarge = false;
    $isMedium = false;
    $isSmall = false;

    foreach ($giantBreeds as $giant) {
      if (stripos($breedLower, $giant) !== false) {
        $isGiant = true;
        break;
      }
    }

    if (!$isGiant) {
      foreach ($largeBreeds as $large) {
        if (stripos($breedLower, $large) !== false) {
          $isLarge = true;
          break;
        }
      }
    }

    if (!$isGiant && !$isLarge) {
      foreach ($mediumBreeds as $medium) {
        if (stripos($breedLower, $medium) !== false) {
          $isMedium = true;
          break;
        }
      }
    }

    if (!$isGiant && !$isLarge && !$isMedium) {
      foreach ($smallBreeds as $small) {
        if (stripos($breedLower, $small) !== false) {
          $isSmall = true;
          break;
        }
      }
    }

    // Default to medium if not categorized
    if (!$isGiant && !$isLarge && !$isMedium && !$isSmall) {
      $isMedium = true;
    }

    return [
      'size_category' => $isGiant ? 'giant' : ($isLarge ? 'large' : ($isMedium ? 'medium' : 'small')),
      'can_grow_tall' => $isGiant || $isLarge,
      'estimated_age_years' => 2, // Default young adult
      'growth_stage' => 'growing',
    ];
  }

  /**
   * ⚡ DRAMATIC PROMPT BUILDER - Breed-aware transformations
   */
  private function buildDramaticPrompt($breed, $currentAge, $targetAge, $breedData)
  {
    $ageDiff = $targetAge - $currentAge;
    $sizeCategory = $breedData['size_category'];
    $canGrowTall = $breedData['can_grow_tall'];

    // CRITICAL: Determine if this is a puppy-to-adult transformation
    $isPuppyToAdult = ($currentAge < 1.5 && $targetAge >= 1.5);

    $transformations = [];

    // ============================================
    // PUPPY TO ADULT TRANSFORMATION (MOST DRAMATIC)
    // ============================================
    if ($isPuppyToAdult) {
      $transformations[] = "TRANSFORM this puppy into a FULLY GROWN ADULT {$breed}";

      if ($canGrowTall) {
        $transformations[] = "MASSIVE size increase - dog must be SIGNIFICANTLY TALLER and LONGER, appearing 2-3x larger in frame";
        $transformations[] = "body stretches vertically and horizontally - legs become MUCH LONGER and thicker";
        $transformations[] = "head enlarges dramatically, muzzle extends and widens significantly";
      } else {
        $transformations[] = "body fills out substantially - appears MUCH stockier and muscular";
        $transformations[] = "compact frame becomes solid and dense, filling out proportionally";
      }

      $transformations[] = "puppy fat completely replaced by defined adult muscle structure";
      $transformations[] = "facial features mature - eyes smaller relative to head, ears fully developed";
      $transformations[] = "coat texture changes from soft puppy fur to adult coat (thicker, coarser)";
      $transformations[] = "paws grow proportionally larger and more robust";
      $transformations[] = "expression shifts from innocent puppy to confident adult dog";
      $transformations[] = "overall stance changes from wobbly puppy to stable, powerful adult posture";
    }
    // ============================================
    // ADULT TO MATURE/SENIOR (AGING VISIBLE)
    // ============================================
    else if ($targetAge >= 6) {
      $transformations[] = "AGE this {$breed} to {$targetAge} years - VISIBLE AGING REQUIRED";

      if ($targetAge >= 9) {
        // SENIOR DOG - EXTREME AGING
        $transformations[] = "coat SEVERELY dulled and grayed - rough texture, thinning patches visible";
        $transformations[] = "eyes VERY cloudy with milky cataract appearance - tired, aged look";
        $transformations[] = "PRONOUNCED facial sagging - jowls droop heavily, loose skin under eyes and chin";
        $transformations[] = "muzzle shows SIGNIFICANT graying (50-70% white/gray hairs)";
        $transformations[] = "body appears less muscular, slight weight gain or loss, sagging posture";
        $transformations[] = "overall weathered, senior dog appearance - unmistakably OLD";
      } else {
        // MATURE DOG - MODERATE AGING
        $transformations[] = "coat noticeably duller, losing youthful shine and gloss";
        $transformations[] = "eyes developing slight cloudiness, less bright and sparkly";
        $transformations[] = "visible skin loosening around face, especially jowls and under eyes";
        $transformations[] = "some gray hairs appearing on muzzle (20-40% gray)";
        $transformations[] = "facial expression calmer, more tired, less energetic than young adult";
        $transformations[] = "coat slightly thinner in places, body less toned";
      }
    }
    // ============================================
    // YOUNG ADULT TO PRIME (SUBTLE MATURATION)
    // ============================================
    else {
      $transformations[] = "MATURE this {$breed} to {$targetAge} years - subtle adult development";

      if ($canGrowTall && $currentAge < 2) {
        $transformations[] = "slight final growth - body becomes MORE MUSCULAR and FULLER";
        $transformations[] = "chest deepens and broadens, legs more defined and powerful";
      }

      $transformations[] = "face becomes more mature and defined, jaw more pronounced";
      $transformations[] = "coat fully developed to adult texture and density";
      $transformations[] = "expression confident and alert, less playful than young dog";
      $transformations[] = "overall athletic prime physique - peak physical condition";
    }

    // Build final prompt (COMPACT for speed)
    $prompt = implode('. ', $transformations);
    $prompt .= ". PRESERVE: exact breed identity, coat color/pattern, distinctive markings, background, lighting. ";
    $prompt .= "OUTPUT: Photorealistic pet photo, same angle and pose.";

    return $prompt;
  }

  /**
   * ⚡ OPTIMIZED: Async generation with FASTER settings
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
      'timeout' => 85,  // ⚡ Reduced from 90
      'connect_timeout' => 4,  // ⚡ Reduced from 5
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
        'temperature' => 0.4,  // ⚡ Lower for consistency and speed
        'topK' => 25,          // ⚡ Reduced for speed
        'topP' => 0.8,         // ⚡ Reduced for speed
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
   * ⚡ OPTIMIZED: Prepare image with aggressive resizing for speed
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

      // ⚡ AGGRESSIVE RESIZE for maximum speed (1536px max instead of 1920px)
      if ($width > 1536 || $height > 1536) {
        Log::info("Resizing {$width}x{$height} to max 1536px");
        $imageContents = $this->resizeImage($imageContents, 1536, 1536);
      }

      // ⚡ Convert to JPEG with slightly lower quality for speed
      $img = imagecreatefromstring($imageContents);
      ob_start();
      imagejpeg($img, null, 82);  // ⚡ Reduced from 85 to 82
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
    imagejpeg($resized, null, 82);  // ⚡ Reduced quality for speed
    $output = ob_get_clean();

    imagedestroy($image);
    imagedestroy($resized);

    return $output;
  }

  /**
   * ⚡ OPTIMIZED: Save as WebP with optimized compression
   */
  private function saveSimulationWebP($imageOutput, $type)
  {
    $img = imagecreatefromstring($imageOutput);

    ob_start();
    imagewebp($img, null, 82);  // ⚡ Reduced from 85 to 82 for speed
    $webpData = ob_get_clean();
    imagedestroy($img);

    $filename = "simulation_{$type}_" . time() . "_" . Str::random(6) . ".webp";
    $path = "simulations/{$filename}";

    Storage::disk('object-storage')->put($path, $webpData);

    return $path;
  }
}
