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
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 240;
  public $tries = 3;
  public $backoff = [10, 30, 60];

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
      Log::info("🐕 WORLD-CLASS AGE TRANSFORMATION STARTED", [
        'result_id' => $this->resultId,
        'breed' => $this->breed,
        'model' => 'gemini-3-pro-image-preview (Nano Banana Pro)'
      ]);

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      $this->updateStatus($result, 'generating', []);

      // Prepare high-quality image for AI processing
      $imageData = $this->prepareHighQualityImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception("Failed to prepare image");
      }

      // Get advanced breed-specific aging characteristics
      $breedProfile = $this->getAdvancedBreedProfile($this->breed);
      Log::info("📊 Breed Profile Generated", $breedProfile);

      // Generate transformations using Nano Banana Pro
      $simulations = $this->generateWorldClassTransformations($imageData, $breedProfile);

      // Save high-quality results
      $savedPaths = [
        '1_years' => null,
        '3_years' => null
      ];

      if (isset($simulations['1_year']) && $simulations['1_year']) {
        $savedPaths['1_years'] = $this->saveHighQualityImage(
          $simulations['1_year'],
          '1_year',
          $this->resultId
        );
        Log::info("✅ 1-year transformation complete: {$savedPaths['1_years']}");
      }

      if (isset($simulations['3_years']) && $simulations['3_years']) {
        $savedPaths['3_years'] = $this->saveHighQualityImage(
          $simulations['3_years'],
          '3_years',
          $this->resultId
        );
        Log::info("✅ 3-years transformation complete: {$savedPaths['3_years']}");
      }

      $this->updateStatus($result, 'complete', $savedPaths, $breedProfile);

      $elapsed = round(microtime(true) - $startTime, 2);
      $successRate = ($savedPaths['1_years'] && $savedPaths['3_years']) ? '100%' : '50%';

      Log::info("🎉 TRANSFORMATION COMPLETE", [
        'time' => "{$elapsed}s",
        'success_rate' => $successRate,
        'quality' => 'world-class'
      ]);
    } catch (\Exception $e) {
      Log::error("❌ TRANSFORMATION FAILED", [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
      ]);

      if (isset($result)) {
        $this->updateStatus($result, 'failed', [], [], $e->getMessage());
      }
    }
  }

  /**
   * Generate world-class transformations using Nano Banana Pro
   */
  private function generateWorldClassTransformations($imageData, $breedProfile)
  {
    $client = new Client([
      'timeout' => 120,
      'connect_timeout' => 10,
    ]);

    $results = [
      '1_year' => null,
      '3_years' => null
    ];

    // Build expert-level prompts
    $prompt1Year = $this->buildExpertPrompt($breedProfile, 1);
    $prompt3Years = $this->buildExpertPrompt($breedProfile, 3);

    Log::info("🎨 Using world-class AI prompting techniques with IDENTITY LOCK");

    // Parallel generation with intelligent retry
    $maxAttempts = 3;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
      try {
        Log::info("🔄 Attempt " . ($attempt + 1) . "/{$maxAttempts}");

        $promises = [];

        if (!$results['1_year']) {
          $promises['1_year'] = $this->createGenerationPromise(
            $client,
            $prompt1Year,
            $imageData
          );
        }

        if (!$results['3_years']) {
          $promises['3_years'] = $this->createGenerationPromise(
            $client,
            $prompt3Years,
            $imageData
          );
        }

        if (empty($promises)) {
          break;
        }

        $settled = Promise\Utils::settle($promises)->wait();

        foreach ($settled as $key => $result) {
          if ($result['state'] === 'fulfilled') {
            $results[$key] = $result['value'];
            Log::info("✅ {$key} transformation successful");
          } else {
            Log::warning("⚠️ {$key} failed: " . $result['reason']->getMessage());
          }
        }

        if ($results['1_year'] && $results['3_years']) {
          Log::info("🎉 Both transformations complete");
          break;
        }

        if ($attempt < $maxAttempts - 1) {
          $delay = pow(2, $attempt + 1);
          Log::info("⏳ Exponential backoff: {$delay}s");
          sleep($delay);
        }
      } catch (\Exception $e) {
        Log::error("Attempt {$attempt} error: " . $e->getMessage());
        if ($attempt < $maxAttempts - 1) {
          sleep(3 * ($attempt + 1));
        }
      }
    }

    return $results;
  }

  /**
   * Create generation promise using Nano Banana Pro
   */
  private function createGenerationPromise($client, $prompt, $imageData)
  {
    $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');

    // Use Nano Banana Pro - the BEST image generation model
    $modelName = 'gemini-3-pro-image-preview';

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
        'temperature' => 0.4,        // LOWERED from 0.6 for more consistency
        'topK' => 40,                // LOWERED from 64 for better consistency
        'topP' => 0.9,              // LOWERED from 0.95 for better consistency
        'maxOutputTokens' => 32768   // Maximum for Nano Banana Pro
      ],
      'safetySettings' => [
        [
          'category' => 'HARM_CATEGORY_HARASSMENT',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_HATE_SPEECH',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
          'threshold' => 'BLOCK_NONE'
        ],
        [
          'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
          'threshold' => 'BLOCK_NONE'
        ]
      ]
    ];

    return $client->postAsync($endpoint, [
      'json' => $payload,
      'headers' => ['Content-Type' => 'application/json']
    ])->then(function ($response) {
      return $this->extractHighQualityImage($response);
    });
  }

  /**
   * Extract image from API response
   */
  private function extractHighQualityImage($response)
  {
    $responseData = json_decode($response->getBody()->getContents(), true);

    if (!isset($responseData['candidates'][0])) {
      Log::error("No candidates in response", ['response' => $responseData]);
      throw new \Exception("No image generated");
    }

    $candidate = $responseData['candidates'][0];

    // Method 1: Direct inlineData
    if (isset($candidate['content']['parts'][0]['inlineData']['data'])) {
      Log::info("✅ Image extracted from inlineData");
      return base64_decode($candidate['content']['parts'][0]['inlineData']['data']);
    }

    // Method 2: Text block with base64
    if (isset($candidate['content']['parts'][0]['text'])) {
      $text = $candidate['content']['parts'][0]['text'];
      $base64 = preg_replace('/```[\w]*\n?/', '', $text);
      $base64 = trim($base64);

      if (!empty($base64)) {
        Log::info("✅ Image extracted from text");
        return base64_decode($base64);
      }
    }

    throw new \Exception("No image data found");
  }

  /**
   * Build expert-level transformation prompt with IDENTITY LOCK
   */
  private function buildExpertPrompt($profile, $years)
  {
    $breed = $profile['breed'];
    $size = $profile['size_category'];
    $coat = $profile['coat_type'];
    $grayPattern = $profile['gray_pattern'];
    $isBrachy = $profile['brachycephalic'] ?? false;

    if ($years === 1) {
      $prompt = "🎯 CRITICAL INSTRUCTION: AGE THIS EXACT DOG

═══════════════════════════════════════════════════════
🔒 IDENTITY LOCK - ABSOLUTE REQUIREMENT 🔒
═══════════════════════════════════════════════════════

THIS IS NOT A REQUEST FOR 'A {$breed}' - THIS IS THIS SPECIFIC INDIVIDUAL DOG.

⚠️ MANDATORY PRESERVATION (100% REQUIRED):
✓ EXACT same coat color(s) - every single color must match
✓ EXACT same coat pattern - every marking, spot, patch
✓ EXACT same facial structure and features
✓ EXACT same ear shape, size, and position
✓ EXACT same eye color and expression
✓ EXACT same nose color and shape
✓ EXACT same body build and proportions
✓ EXACT same collar or accessories (if present)
✓ EXACT same background color and elements
✓ EXACT same pose, angle, and camera position
✓ EXACT same lighting direction and quality

✅ YOU MAY ONLY CHANGE:
• Add 3-7 scattered gray hairs on muzzle (subtle)
• Very slight coat texture change (5-10% less glossy)
• Minimal eye brightness reduction (10% less sparkle)
• Nothing else

🚨 FAILURE CONDITIONS:
❌ If coat color changes = COMPLETE FAILURE
❌ If markings disappear or move = COMPLETE FAILURE  
❌ If background changes = COMPLETE FAILURE
❌ If dog doesn't look identical = COMPLETE FAILURE
❌ If anyone can't recognize it's the same dog = COMPLETE FAILURE

═══════════════════════════════════════════════════════
🔬 SUBTLE AGING CHANGES (1 YEAR ONLY):
═══════════════════════════════════════════════════════

👁️ MINIMAL EYE CHANGES:
• Brightness: 10% less bright (very subtle)
• Lens: Barely noticeable start of cloudiness

🎨 MINIMAL COAT CHANGES:";

      switch ($coat) {
        case 'curly/fluffy':
          $prompt .= "\n• Texture: 5-10% less bouncy (very slight)
• Quality: Minimally less fluffy";
          break;

        case 'double_coat':
          $prompt .= "\n• Gloss: 10-15% reduction (subtle)
• Texture: Slightly less plush";
          break;

        case 'long_silky':
          $prompt .= "\n• Shine: 10-15% less luster (subtle)
• Texture: Slightly less flowing";
          break;

        default:
          $prompt .= "\n• Shine: 10-15% reduction (very subtle)
• Texture: Minimally coarser";
      }

      $prompt .= "\n\n😺 MINIMAL FACIAL CHANGES:";

      if ($isBrachy) {
        $prompt .= "\n• Wrinkles: 10% deeper (barely visible)";
      } else {
        $prompt .= "\n• Muzzle: Very subtle loosening (almost invisible)";
      }

      $prompt .= "\n\n⚪ MINIMAL GRAYING:";

      switch ($grayPattern) {
        case 'minimal':
          $prompt .= "\n• Gray hairs: NONE (breed doesn't gray)
• Color: Slightly duller (5%)";
          break;

        case 'prominent':
          $prompt .= "\n• Muzzle: 3-5 scattered silver hairs ONLY
• Very sparse and subtle";
          break;

        default:
          $prompt .= "\n• Muzzle tip: 2-4 scattered gray hairs ONLY
• Barely noticeable";
      }

      $prompt .= "\n\n💪 BODY CHANGES:
• Almost none - dog looks nearly identical
• Maybe 5% less muscle definition

═══════════════════════════════════════════════════════
📸 OUTPUT REQUIREMENTS:
═══════════════════════════════════════════════════════
• MUST look like the EXACT SAME DOG, just slightly older
• ALL unique features PERFECTLY preserved
• Background IDENTICAL
• Pose and angle IDENTICAL
• Only aging effects applied (subtle!)

REMEMBER: If viewers can't instantly recognize this as the same dog, YOU HAVE FAILED.

Generate the image now.";
    } else {
      // 3 YEARS
      $prompt = "🎯 CRITICAL INSTRUCTION: AGE THIS EXACT DOG

═══════════════════════════════════════════════════════
🔒 IDENTITY LOCK - ABSOLUTE REQUIREMENT 🔒
═══════════════════════════════════════════════════════

THIS IS NOT A REQUEST FOR 'A SENIOR {$breed}' - THIS IS THIS SPECIFIC DOG AGED 3 YEARS.

⚠️ MANDATORY PRESERVATION (100% REQUIRED):
✓ EXACT same coat base color(s) - every color preserved
✓ EXACT same coat pattern and markings - all preserved
✓ EXACT same facial structure
✓ EXACT same ear shape and position
✓ EXACT same eye color (can be cloudier, but same color)
✓ EXACT same nose color
✓ EXACT same body proportions
✓ EXACT same collar/accessories (if any)
✓ EXACT same background and setting
✓ EXACT same pose and angle
✓ EXACT same lighting

✅ YOU MAY CHANGE:
• Add gray/white hairs (30-50% on muzzle and face)
• Coat texture (coarser, less shiny)
• Eye cloudiness (cataract-like)
• Slight facial sagging
• Some coat thinning

🚨 NEVER CHANGE:
❌ Coat base colors or patterns
❌ Unique markings or spots
❌ Background or environment
❌ Dog's fundamental appearance
❌ Pose or camera angle

═══════════════════════════════════════════════════════
🔬 SENIOR AGING CHANGES (3 YEARS):
═══════════════════════════════════════════════════════

👁️ SENIOR EYE CHANGES:
• Cloudiness: 30-40% (cataract-like but NOT blind-looking)
• Brightness: 30-40% less sparkle
• Color: SAME color but with slight milkiness

🎨 SENIOR COAT CHANGES:";

      switch ($coat) {
        case 'curly/fluffy':
          $prompt .= "\n• Texture: 30-40% less fluffy, wiry
• Thinning: Slight, patchy areas
• BUT: Same curly structure, same colors";
          break;

        case 'double_coat':
          $prompt .= "\n• Undercoat: 30-40% thinner
• Guard hairs: Rougher, less glossy
• BUT: Same coat colors and patterns";
          break;

        case 'long_silky':
          $prompt .= "\n• Shine: Significantly reduced
• Texture: Coarser, dry appearance
• BUT: Same hair colors";
          break;

        default:
          $prompt .= "\n• Texture: Rougher, duller
• Quality: 30% loss
• BUT: SAME base colors";
      }

      $prompt .= "\n\n😺 SENIOR FACIAL AGING:";

      if ($isBrachy) {
        $prompt .= "\n• Wrinkles: 30-40% deeper
• Jowls: Looser, more prominent
• BUT: Same facial structure";
      } else {
        $prompt .= "\n• Sagging: Visible on jowls
• Skin: Looser around muzzle
• BUT: Same face shape";
      }

      $prompt .= "\n\n⚪ SENIOR GRAYING:";

      switch ($grayPattern) {
        case 'minimal':
          $prompt .= "\n• Gray: None (breed characteristic)
• Color: 25-30% darker/duller
• BUT: Same base color";
          break;

        case 'prominent':
          $prompt .= "\n• Muzzle: 40-60% gray coverage
• Face: Gray around eyes/ears
• BUT: Underlying coat color same";
          break;

        default:
          $prompt .= "\n• Muzzle: 30-50% gray
• Face: Gray scattered around
• BUT: Base colors preserved";
      }

      $prompt .= "\n\n💪 SENIOR BODY:";

      if ($size === 'giant') {
        $prompt .= "\n• Muscle: 20-30% softer
• Posture: Slightly hunched
• BUT: Same body structure";
      } elseif ($size === 'toy' || $size === 'small') {
        $prompt .= "\n• Muscle: 10-20% softer
• BUT: Compact form maintained";
      } else {
        $prompt .= "\n• Muscle: 20-30% reduced
• BUT: Same proportions";
      }

      $prompt .= "\n\n😌 EXPRESSION:
• Calmer, wiser look
• Less alert (not sad!)
• Healthy senior dignity

═══════════════════════════════════════════════════════
📸 OUTPUT REQUIREMENTS:
═══════════════════════════════════════════════════════
• MUST look like the EXACT SAME DOG, just senior
• ALL markings and colors PRESERVED
• Background IDENTICAL
• Pose IDENTICAL
• Just aged with gray hair and senior features

CRITICAL: Anyone looking at this should say 'That's the same dog, just older' NOT 'That's a different dog'.

Generate the image now.";
    }

    // Add breed-specific traits
    if (!empty($profile['specific_traits'])) {
      $prompt .= "\n\n🧬 BREED NOTES (for aging only):\n";
      foreach (array_slice($profile['specific_traits'], 0, 2) as $trait) {
        $prompt .= "• {$trait}\n";
      }
    }

    return $prompt;
  }

  /**
   * Get advanced breed-specific aging profile
   */
  private function getAdvancedBreedProfile($breed)
  {
    $breedLower = strtolower($breed);

    $profile = [
      'breed' => $breed,
      'size_category' => 'medium',
      'coat_type' => 'standard',
      'gray_pattern' => 'moderate',
      'aging_speed' => 'normal',
      'brachycephalic' => false,
      'specific_traits' => []
    ];

    // SIZE CATEGORIES
    if ($this->matchBreed($breedLower, ['chihuahua', 'pomeranian', 'yorkshire', 'toy', 'papillon', 'maltese', 'shih tzu'])) {
      $profile['size_category'] = 'toy';
      $profile['aging_speed'] = 'slow';
      $profile['specific_traits'][] = 'Toy breeds age slower';
    } elseif ($this->matchBreed($breedLower, ['corgi', 'beagle', 'french bulldog', 'boston terrier', 'cocker spaniel'])) {
      $profile['size_category'] = 'small';
      $profile['aging_speed'] = 'slow';
      $profile['specific_traits'][] = 'Small breeds age gracefully';
    } elseif ($this->matchBreed($breedLower, ['great dane', 'mastiff', 'saint bernard', 'newfoundland', 'wolfhound', 'leonberger', 'bernese'])) {
      $profile['size_category'] = 'giant';
      $profile['aging_speed'] = 'fast';
      $profile['specific_traits'][] = 'Giant breeds age faster';
    } elseif ($this->matchBreed($breedLower, ['german shepherd', 'golden retriever', 'labrador', 'rottweiler', 'doberman', 'boxer', 'husky'])) {
      $profile['size_category'] = 'large';
      $profile['specific_traits'][] = 'Large breeds show moderate aging';
    }

    // COAT TYPES
    if ($this->matchBreed($breedLower, ['poodle', 'bichon', 'maltese', 'shih tzu', 'lhasa apso', 'havanese'])) {
      $profile['coat_type'] = 'curly/fluffy';
      $profile['specific_traits'][] = 'Curly coat becomes wiry with age';
    } elseif ($this->matchBreed($breedLower, ['husky', 'malamute', 'samoyed', 'chow', 'akita'])) {
      $profile['coat_type'] = 'double_coat';
      $profile['specific_traits'][] = 'Double coat thins with age';
    } elseif ($this->matchBreed($breedLower, ['golden retriever', 'cocker spaniel', 'setter', 'cavalier'])) {
      $profile['coat_type'] = 'long_silky';
      $profile['specific_traits'][] = 'Silky coat loses shine';
    }

    // GRAYING PATTERNS
    if ($this->matchBreed($breedLower, ['rottweiler', 'doberman', 'black lab', 'pug', 'pit bull', 'scottish'])) {
      $profile['gray_pattern'] = 'minimal';
      $profile['specific_traits'][] = 'Dark coat dulls rather than grays';
    } elseif ($this->matchBreed($breedLower, ['german shepherd', 'schnauzer', 'yorkshire', 'weimaraner'])) {
      $profile['gray_pattern'] = 'prominent';
      $profile['specific_traits'][] = 'Grays prominently on muzzle';
    }

    // BRACHYCEPHALIC
    if ($this->matchBreed($breedLower, ['pug', 'french bulldog', 'boston terrier', 'shih tzu', 'bulldog', 'boxer', 'mastiff'])) {
      $profile['brachycephalic'] = true;
      $profile['specific_traits'][] = 'Facial wrinkles deepen with age';
    }

    return $profile;
  }

  /**
   * Match breed names flexibly
   */
  private function matchBreed($breedLower, $patterns)
  {
    foreach ($patterns as $pattern) {
      if (stripos($breedLower, $pattern) !== false) {
        return true;
      }
    }
    return false;
  }

  /**
   * Prepare high-quality image for AI processing
   */
  private function prepareHighQualityImage($fullPath)
  {
    try {
      $cacheKey = "hq_img_" . md5($fullPath);

      return Cache::remember($cacheKey, 600, function () use ($fullPath) {
        $imageContents = Storage::disk('object-storage')->get($fullPath);

        if (empty($imageContents)) {
          throw new \Exception('Empty image file');
        }

        $imageInfo = @getimagesizefromstring($imageContents);
        if ($imageInfo === false) {
          throw new \Exception('Invalid image');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        Log::info("📐 Original: {$width}x{$height}");

        // Optimal size for Nano Banana Pro (higher quality)
        $targetSize = 1536;

        if ($width > $targetSize || $height > $targetSize) {
          Log::info("🔄 Resizing to {$targetSize}px for optimal AI processing");
          $imageContents = $this->highQualityResize($imageContents, $targetSize);
        }

        $img = imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception('Failed to create image');
        }

        // Slight sharpening for better AI processing
        $sharpenMatrix = [
          [-1, -1, -1],
          [-1, 16, -1],
          [-1, -1, -1]
        ];
        $divisor = 8;
        $offset = 0;
        imageconvolution($img, $sharpenMatrix, $divisor, $offset);

        ob_start();
        imagejpeg($img, null, 92);
        $optimized = ob_get_clean();
        imagedestroy($img);

        Log::info("✅ Image prepared: " . round(strlen($optimized) / 1024, 2) . " KB");

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
   * High-quality bicubic resize
   */
  private function highQualityResize($imageContents, $maxSize)
  {
    $source = imagecreatefromstring($imageContents);
    if ($source === false) {
      throw new \Exception('Failed to create source');
    }

    $width = imagesx($source);
    $height = imagesy($source);

    $ratio = min($maxSize / $width, $maxSize / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    imagecopyresampled(
      $resized,
      $source,
      0,
      0,
      0,
      0,
      $newWidth,
      $newHeight,
      $width,
      $height
    );

    ob_start();
    imagejpeg($resized, null, 92);
    $output = ob_get_clean();

    imagedestroy($source);
    imagedestroy($resized);

    return $output;
  }

  /**
   * Save high-quality transformation
   */
  private function saveHighQualityImage($imageOutput, $type, $resultId)
  {
    try {
      $img = imagecreatefromstring($imageOutput);
      if ($img === false) {
        throw new \Exception('Failed to create output image');
      }

      ob_start();
      imagewebp($img, null, 90);
      $webpData = ob_get_clean();
      imagedestroy($img);

      $filename = "transform_{$resultId}_{$type}_" . time() . ".webp";
      $path = "simulations/{$filename}";

      Storage::disk('object-storage')->put($path, $webpData);

      Log::info("💾 Saved: {$path} (" . round(strlen($webpData) / 1024, 2) . " KB)");

      return $path;
    } catch (\Exception $e) {
      Log::error("Save failed: " . $e->getMessage());
      return null;
    }
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
      'updated_at' => now()->toIso8601String()
    ];

    if (!empty($profile)) {
      $data['breed_profile'] = $profile;
    }

    if ($error) {
      $data['error'] = $error;
    }

    $result->update(['simulation_data' => json_encode($data)]);

    Cache::forget("simulation_status_{$result->scan_id}");
    Cache::forget("sim_status_{$result->scan_id}");
  }
}
