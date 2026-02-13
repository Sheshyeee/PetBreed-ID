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
use GuzzleHttp\Exception\RequestException;

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 180;
  public $tries = 3;
  public $backoff = [10, 30, 60]; // Exponential backoff

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
      Log::info("🐕 STARTING AGE SIMULATION", [
        'result_id' => $this->resultId,
        'breed' => $this->breed
      ]);

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      // Mark as generating immediately
      $this->updateStatus($result, 'generating', []);

      // Prepare image once (optimized for speed)
      $imageData = $this->prepareOptimizedImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception("Failed to prepare image");
      }

      // Get comprehensive breed profile
      $breedProfile = $this->getBreedAgingProfile($this->breed);
      Log::info("📊 Breed Profile", $breedProfile);

      // Generate both transformations in parallel with retry logic
      $simulations = $this->generateSimulationsParallel($imageData, $breedProfile);

      // Save results
      $savedPaths = [
        '1_years' => null,
        '3_years' => null
      ];

      if (isset($simulations['1_year']) && $simulations['1_year']) {
        $savedPaths['1_years'] = $this->saveSimulationImage(
          $simulations['1_year'],
          '1_year',
          $this->resultId
        );
        Log::info("✅ 1-year saved: {$savedPaths['1_years']}");
      }

      if (isset($simulations['3_years']) && $simulations['3_years']) {
        $savedPaths['3_years'] = $this->saveSimulationImage(
          $simulations['3_years'],
          '3_years',
          $this->resultId
        );
        Log::info("✅ 3-years saved: {$savedPaths['3_years']}");
      }

      // Update with final results
      $this->updateStatus($result, 'complete', $savedPaths, $breedProfile);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("🎉 COMPLETED in {$elapsed}s");
    } catch (\Exception $e) {
      Log::error("❌ Job FAILED", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      if (isset($result)) {
        $this->updateStatus($result, 'failed', [], [], $e->getMessage());
      }
    }
  }

  /**
   * Generate both simulations in parallel with intelligent retry
   */
  private function generateSimulationsParallel($imageData, $breedProfile)
  {
    $client = new Client([
      'timeout' => 90,
      'connect_timeout' => 5,
    ]);

    $maxRetries = 2;
    $results = [
      '1_year' => null,
      '3_years' => null
    ];

    // Build prompts
    $prompt1Year = $this->buildAdvancedPrompt($breedProfile, 1);
    $prompt3Years = $this->buildAdvancedPrompt($breedProfile, 3);

    // Try parallel first
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      try {
        Log::info("🔄 Parallel attempt " . ($attempt + 1));

        $promises = [
          '1_year' => $this->createGenerationPromise($client, $prompt1Year, $imageData),
          '3_years' => $this->createGenerationPromise($client, $prompt3Years, $imageData)
        ];

        $settled = Promise\Utils::settle($promises)->wait();

        // Process 1 year
        if ($settled['1_year']['state'] === 'fulfilled') {
          $results['1_year'] = $settled['1_year']['value'];
          Log::info("✅ 1-year generated");
        } else {
          Log::warning("⚠️ 1-year failed: " . $settled['1_year']['reason']->getMessage());
        }

        // Process 3 years
        if ($settled['3_years']['state'] === 'fulfilled') {
          $results['3_years'] = $settled['3_years']['value'];
          Log::info("✅ 3-years generated");
        } else {
          Log::warning("⚠️ 3-years failed: " . $settled['3_years']['reason']->getMessage());
        }

        // If both succeeded, we're done
        if ($results['1_year'] && $results['3_years']) {
          break;
        }

        // If one failed, retry just that one
        if (!$results['1_year'] && $attempt < $maxRetries - 1) {
          sleep(2);
          $results['1_year'] = $this->generateSingle($client, $prompt1Year, $imageData);
        }

        if (!$results['3_years'] && $attempt < $maxRetries - 1) {
          sleep(2);
          $results['3_years'] = $this->generateSingle($client, $prompt3Years, $imageData);
        }
      } catch (\Exception $e) {
        Log::warning("Parallel attempt {$attempt} error: " . $e->getMessage());
        if ($attempt < $maxRetries - 1) {
          sleep(3 * ($attempt + 1));
        }
      }
    }

    return $results;
  }

  /**
   * Create a promise for image generation
   */
  private function createGenerationPromise($client, $prompt, $imageData)
  {
    $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');

    // Try models in order of preference
    // Configure your working model in .env as GEMINI_MODEL
    $modelName = config('services.gemini.model') ?? env('GEMINI_MODEL', 'gemini-2.0-flash-thinking-exp-01-21');

    // Available models (uncomment the one that works for you):
    // - gemini-2.0-flash-thinking-exp-01-21 (current stable)
    // - gemini-1.5-pro-latest (reliable, slower)
    // - gemini-1.5-flash-latest (faster, good quality)

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

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
        'temperature' => 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 8192
      ]
    ];

    return $client->postAsync($endpoint, [
      'json' => $payload,
      'headers' => ['Content-Type' => 'application/json']
    ])->then(function ($response) {
      return $this->extractImageFromResponse($response);
    });
  }

  /**
   * Generate single image synchronously
   */
  private function generateSingle($client, $prompt, $imageData)
  {
    try {
      $promise = $this->createGenerationPromise($client, $prompt, $imageData);
      return $promise->wait();
    } catch (\Exception $e) {
      Log::error("Single generation failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Extract image data from API response
   */
  private function extractImageFromResponse($response)
  {
    $responseData = json_decode($response->getBody()->getContents(), true);

    if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
      return base64_decode($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data']);
    }

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
      $base64 = preg_replace('/```[\w]*\n?/', '', $responseData['candidates'][0]['content']['parts'][0]['text']);
      return base64_decode(trim($base64));
    }

    throw new \Exception("No image data in response");
  }

  /**
   * Comprehensive breed aging profile with detailed characteristics
   */
  private function getBreedAgingProfile($breed)
  {
    $breedLower = strtolower($breed);

    // Initialize profile
    $profile = [
      'breed' => $breed,
      'size_category' => 'medium',
      'coat_type' => 'standard',
      'aging_speed' => 'normal',
      'gray_pattern' => 'standard',
      'face_changes' => 'moderate',
      'body_changes' => 'moderate',
      'specific_traits' => []
    ];

    // Size categories (affects aging speed and body changes)
    if ($this->matchesBreed($breedLower, ['chihuahua', 'pomeranian', 'yorkshire terrier', 'toy', 'papillon', 'maltese'])) {
      $profile['size_category'] = 'toy';
      $profile['aging_speed'] = 'slow';
      $profile['body_changes'] = 'minimal';
      $profile['specific_traits'][] = 'maintains puppy-like features longer';
    } elseif ($this->matchesBreed($breedLower, ['corgi', 'beagle', 'french bulldog', 'boston terrier', 'miniature'])) {
      $profile['size_category'] = 'small';
      $profile['aging_speed'] = 'slow';
    } elseif ($this->matchesBreed($breedLower, ['great dane', 'mastiff', 'saint bernard', 'newfoundland', 'irish wolfhound', 'leonberger'])) {
      $profile['size_category'] = 'giant';
      $profile['aging_speed'] = 'fast';
      $profile['body_changes'] = 'significant';
      $profile['specific_traits'][] = 'ages faster than smaller breeds';
      $profile['specific_traits'][] = 'may show joint stiffness and posture changes';
    } elseif ($this->matchesBreed($breedLower, ['german shepherd', 'golden retriever', 'labrador', 'rottweiler', 'doberman', 'boxer'])) {
      $profile['size_category'] = 'large';
      $profile['body_changes'] = 'moderate-high';
    }

    // Coat types
    if ($this->matchesBreed($breedLower, ['poodle', 'bichon', 'maltese', 'shih tzu', 'lhasa apso'])) {
      $profile['coat_type'] = 'curly/fluffy';
      $profile['specific_traits'][] = 'coat becomes wiry and less fluffy with age';
    } elseif ($this->matchesBreed($breedLower, ['husky', 'malamute', 'samoyed', 'chow'])) {
      $profile['coat_type'] = 'double_coat';
      $profile['specific_traits'][] = 'undercoat thins with age';
    } elseif ($this->matchesBreed($breedLower, ['golden retriever', 'cocker spaniel', 'setter'])) {
      $profile['coat_type'] = 'long_silky';
      $profile['specific_traits'][] = 'coat loses shine and becomes coarser';
    }

    // Graying patterns
    if ($this->matchesBreed($breedLower, ['rottweiler', 'doberman', 'black lab', 'pug', 'pitbull'])) {
      $profile['gray_pattern'] = 'minimal';
      $profile['specific_traits'][] = 'dark coat darkens or dulls rather than grays';
    } elseif ($this->matchesBreed($breedLower, ['german shepherd', 'schnauzer', 'yorkshire'])) {
      $profile['gray_pattern'] = 'prominent';
      $profile['specific_traits'][] = 'grays significantly around muzzle and eyebrows';
    } elseif ($this->matchesBreed($breedLower, ['golden retriever', 'labrador', 'beagle'])) {
      $profile['gray_pattern'] = 'moderate_face';
      $profile['specific_traits'][] = 'grays primarily on face and muzzle';
    }

    // Wrinkle-prone breeds
    if ($this->matchesBreed($breedLower, ['shar pei', 'bulldog', 'mastiff', 'basset hound', 'bloodhound', 'pug'])) {
      $profile['face_changes'] = 'high';
      $profile['specific_traits'][] = 'existing wrinkles deepen significantly';
      $profile['specific_traits'][] = 'facial skin loosens and sags more prominently';
    }

    // Athletic breeds
    if ($this->matchesBreed($breedLower, ['border collie', 'australian shepherd', 'vizsla', 'weimaraner'])) {
      $profile['body_changes'] = 'moderate';
      $profile['specific_traits'][] = 'maintains athletic build but loses some muscle definition';
    }

    // Brachycephalic (flat-faced) breeds
    if ($this->matchesBreed($breedLower, ['pug', 'french bulldog', 'boston terrier', 'shih tzu', 'bulldog'])) {
      $profile['specific_traits'][] = 'face wrinkles become more pronounced';
      $profile['specific_traits'][] = 'eyes may appear more prominent with age';
    }

    return $profile;
  }

  /**
   * Helper to match breed names flexibly
   */
  private function matchesBreed($breedLower, $patterns)
  {
    foreach ($patterns as $pattern) {
      if (stripos($breedLower, $pattern) !== false) {
        return true;
      }
    }
    return false;
  }

  /**
   * Build advanced, breed-specific transformation prompt
   */
  private function buildAdvancedPrompt($profile, $years)
  {
    $breed = $profile['breed'];
    $changes = [];

    if ($years === 1) {
      // ===== 1 YEAR TRANSFORMATION =====

      // Eyes (universal)
      $changes[] = "Eyes: subtle cloudiness beginning, slight loss of sparkle and clarity";

      // Coat changes based on type
      switch ($profile['coat_type']) {
        case 'curly/fluffy':
          $changes[] = "Coat: texture becoming slightly less soft and fluffy, minor loss of volume";
          break;
        case 'double_coat':
          $changes[] = "Coat: undercoat thinning slightly, outer coat less glossy";
          break;
        case 'long_silky':
          $changes[] = "Coat: losing 15-20% of natural shine, appearing more matte";
          break;
        default:
          $changes[] = "Coat: subtle loss of glossy sheen, texture slightly coarser";
      }

      // Face aging
      if ($profile['face_changes'] === 'high') {
        $changes[] = "Face: existing wrinkles deepen by 20%, slight skin loosening around jowls";
      } else {
        $changes[] = "Face: subtle loosening around muzzle and under eyes, early jowl development";
      }

      // Graying based on pattern
      switch ($profile['gray_pattern']) {
        case 'minimal':
          $changes[] = "Color: coat appears slightly duller/darker, minimal to no gray";
          break;
        case 'prominent':
          $changes[] = "Graying: light silver hairs appearing on muzzle and around eyebrows";
          break;
        case 'moderate_face':
          $changes[] = "Graying: few scattered gray hairs on muzzle, very subtle";
          break;
        default:
          $changes[] = "Graying: natural age-appropriate gray hairs on muzzle (if applicable to breed)";
      }

      // Body changes based on size
      if ($profile['size_category'] === 'giant') {
        $changes[] = "Body: if young, significant growth; if adult, early muscle softening";
      } elseif ($profile['size_category'] === 'toy') {
        $changes[] = "Body: maintains compact build, minimal visible changes";
      } else {
        $changes[] = "Body: slight softening of muscle definition, early maturity";
      }

      // Expression
      $changes[] = "Expression: calmer, more mature demeanor, slightly less bright-eyed";
    } else {
      // ===== 3 YEARS TRANSFORMATION (SENIOR) =====

      // Eyes (pronounced aging)
      $changes[] = "Eyes: noticeable cloudiness/haziness, clear signs of aging, reduced brightness";

      // Coat (significant changes)
      switch ($profile['coat_type']) {
        case 'curly/fluffy':
          $changes[] = "Coat: significantly wiry and coarse, lost most fluffiness, thinning visible";
          break;
        case 'double_coat':
          $changes[] = "Coat: undercoat notably thinner, patchy areas, rough outer coat";
          break;
        case 'long_silky':
          $changes[] = "Coat: very dull and rough, lost all shine, possible thinning";
          break;
        default:
          $changes[] = "Coat: very rough and dull, significant loss of youthful texture";
      }

      // Face aging (dramatic)
      if ($profile['face_changes'] === 'high') {
        $changes[] = "Face: wrinkles MUCH deeper and more extensive, pronounced sagging, aged appearance";
      } else {
        $changes[] = "Face: clear sagging visible on jowls and under eyes, loose skin, aged look";
      }

      // Graying (pronounced)
      switch ($profile['gray_pattern']) {
        case 'minimal':
          $changes[] = "Color: coat significantly darker/faded, natural dulling with age";
          break;
        case 'prominent':
          $changes[] = "Graying: extensive silver/gray on muzzle, eyebrows, and spreading to face";
          break;
        case 'moderate_face':
          $changes[] = "Graying: prominent gray muzzle, gray spreading around face and eyes";
          break;
        default:
          $changes[] = "Graying: age-appropriate gray pattern typical for senior {$breed}";
      }

      // Body (senior changes)
      if ($profile['aging_speed'] === 'fast') {
        $changes[] = "Body: senior posture, possible stiffness, weight redistribution, less toned";
      } elseif ($profile['size_category'] === 'toy') {
        $changes[] = "Body: compact but clearly senior, subtle aging signs throughout";
      } else {
        $changes[] = "Body: reduced muscle tone, slight weight changes, senior physique";
      }

      // Expression (senior dog)
      $changes[] = "Expression: clearly senior dog, wise and calm demeanor, visibly aged face";
    }

    // Add breed-specific traits
    if (!empty($profile['specific_traits'])) {
      $changes[] = "Breed traits: " . implode(', ', array_slice($profile['specific_traits'], 0, 2));
    }

    $changesList = implode(". ", $changes);

    return "Transform this {$breed} to show realistic aging after {$years} year(s). "
      . "REQUIRED AGING CHANGES: {$changesList}. "
      . "CRITICAL PRESERVATION: exact breed identity, same coat base color and pattern, "
      . "all distinctive markings, same pose and position, identical background. "
      . "STYLE: photorealistic natural aging, professional pet photography quality, "
      . "accurate {$breed} aging characteristics, believable and realistic transformation.";
  }

  /**
   * Prepare image with aggressive optimization for speed
   */
  private function prepareOptimizedImage($fullPath)
  {
    try {
      // Check cache first
      $cacheKey = "prepared_img_" . md5($fullPath);

      return Cache::remember($cacheKey, 300, function () use ($fullPath) {
        $imageContents = Storage::disk('object-storage')->get($fullPath);

        if (empty($imageContents)) {
          throw new \Exception('Empty image file');
        }

        // Get dimensions
        $imageInfo = @getimagesizefromstring($imageContents);
        if ($imageInfo === false) {
          throw new \Exception('Invalid image format');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // Aggressive resize for speed (smaller = faster API)
        $targetSize = 1024; // Reduced from 1280

        if ($width > $targetSize || $height > $targetSize) {
          Log::info("Resizing {$width}x{$height} → {$targetSize}px");
          $imageContents = $this->fastResize($imageContents, $targetSize);
        }

        // Convert to optimized JPEG
        $img = imagecreatefromstring($imageContents);

        ob_start();
        imagejpeg($img, null, 85); // Balanced quality
        $optimized = ob_get_clean();
        imagedestroy($img);

        return [
          'base64' => base64_encode($optimized),
          'mimeType' => 'image/jpeg'
        ];
      });
    } catch (\Exception $e) {
      Log::error("Image prep failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Fast image resize
   */
  private function fastResize($imageContents, $maxSize)
  {
    $source = imagecreatefromstring($imageContents);
    $width = imagesx($source);
    $height = imagesy($source);

    $ratio = min($maxSize / $width, $maxSize / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    ob_start();
    imagejpeg($resized, null, 85);
    $output = ob_get_clean();

    imagedestroy($source);
    imagedestroy($resized);

    return $output;
  }

  /**
   * Save simulation image as optimized WebP
   */
  private function saveSimulationImage($imageOutput, $type, $resultId)
  {
    $img = imagecreatefromstring($imageOutput);

    ob_start();
    imagewebp($img, null, 85); // High quality WebP
    $webpData = ob_get_clean();
    imagedestroy($img);

    $filename = "sim_{$resultId}_{$type}_" . time() . ".webp";
    $path = "simulations/{$filename}";

    Storage::disk('object-storage')->put($path, $webpData);

    return $path;
  }

  /**
   * Update result status
   */
  private function updateStatus($result, $status, $paths = [], $profile = [], $error = null)
  {
    $data = [
      'status' => $status,
      '1_years' => $paths['1_years'] ?? null,
      '3_years' => $paths['3_years'] ?? null,
    ];

    if (!empty($profile)) {
      $data['breed_profile'] = $profile;
    }

    if ($error) {
      $data['error'] = $error;
    }

    $result->update(['simulation_data' => json_encode($data)]);

    // Clear status cache
    Cache::forget("simulation_status_{$result->scan_id}");
  }
}
