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

    Log::info("🎨 Using world-class AI prompting techniques");

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
        'temperature' => 0.6,        // Optimal for realistic results
        'topK' => 64,                // Maximum quality
        'topP' => 0.95,
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
   * Build expert-level transformation prompt
   */
  private function buildExpertPrompt($profile, $years)
  {
    $breed = $profile['breed'];
    $size = $profile['size_category'];
    $coat = $profile['coat_type'];
    $grayPattern = $profile['gray_pattern'];
    $isBrachy = $profile['brachycephalic'] ?? false;

    if ($years === 1) {
      $prompt = "🎯 EXPERT PET PHOTOGRAPHY TRANSFORMATION TASK

Transform this {$breed} dog to show REALISTIC, NATURAL aging after EXACTLY 1 YEAR.

═══════════════════════════════════════════════════════
🔬 SCIENTIFIC AGING CHANGES (1 YEAR):
═══════════════════════════════════════════════════════

👁️ EYE AGING:
• Lens: Subtle cloudiness beginning (10-15% opacity increase)
• Brightness: Reduced sparkle (15-20% less bright)
• Color: Slight dulling of iris color
• Detail: Very fine tear staining may appear

🎨 COAT TRANSFORMATION";

      switch ($coat) {
        case 'curly/fluffy':
          $prompt .= "\n• Texture: 10-15% less bouncy and fluffy
• Structure: Individual hairs slightly wiry
• Volume: Minor loss, less 'puppy-like'
• Touch: Would feel slightly coarser";
          break;

        case 'double_coat':
          $prompt .= "\n• Undercoat: Thin by 10-15%
• Guard hairs: Lose 20% of gloss
• Texture: Slight roughening
• Density: Less plush overall";
          break;

        case 'long_silky':
          $prompt .= "\n• Shine: Lose 20-25% of luster
• Appearance: More matte finish
• Texture: Individual strands coarser
• Flow: Less flowing, more textured";
          break;

        default:
          $prompt .= "\n• Shine: 15-20% reduction in gloss
• Texture: Noticeably coarser
• Vibrancy: Subtle color dulling
• Quality: Less youthful appearance";
      }

      $prompt .= "\n\n😺 FACIAL CHANGES:";

      if ($isBrachy) {
        $prompt .= "\n• Wrinkles: Deepen by 15-20%
• Jowls: Slight loosening begins
• Folds: More pronounced
• Eyes: May appear more prominent";
      } else {
        $prompt .= "\n• Muzzle: Very subtle skin loosening
• Expression lines: Slight deepening
• Jowls: Early development starting
• Under-eye: Subtle loosening";
      }

      $prompt .= "\n\n⚪ GRAYING PATTERN:";

      switch ($grayPattern) {
        case 'minimal':
          $prompt .= "\n• Color shift: 10% duller/darker
• Gray hairs: NONE (breed doesn't gray)
• Aging: Natural color fade only";
          break;

        case 'prominent':
          $prompt .= "\n• Muzzle: 5-10 scattered silver hairs
• Eyebrows: Very subtle graying
• Pattern: First early signs only";
          break;

        default:
          $prompt .= "\n• Muzzle tip: 3-7 scattered gray hairs
• Coverage: Very minimal
• Natural: Age-appropriate for {$breed}";
      }

      $prompt .= "\n\n💪 BODY & POSTURE:";

      if ($size === 'giant') {
        $prompt .= "\n• Growth: Significant if young
• Muscle: 5-10% softening if adult
• Definition: Slightly less toned";
      } elseif ($size === 'toy' || $size === 'small') {
        $prompt .= "\n• Build: Maintains compact form
• Changes: Minimal visible
• Maturity: Slight proportional changes";
      } else {
        $prompt .= "\n• Muscle: 5-10% definition loss
• Maturity: Slight physical development
• Composition: Minimal changes";
      }

      $prompt .= "\n\n😌 EXPRESSION & DEMEANOR:
• Facial expression: Calmer, more mature
• Eye brightness: 10-15% less 'bright-eyed'
• Wisdom: Subtle in the gaze
• Energy: Natural maturation visible

═══════════════════════════════════════════════════════
🚫 CRITICAL PRESERVATION REQUIREMENTS:
═══════════════════════════════════════════════════════
✓ EXACT breed characteristics
✓ IDENTICAL coat base color
✓ SAME coat pattern/markings
✓ ALL distinctive features
✓ EXACT pose and position
✓ SAME background/environment
✓ MATCHING lighting conditions

❌ DO NOT:
✗ Make dramatic changes (only 1 year!)
✗ Change breed appearance
✗ Alter markings or unique features
✗ Change pose or background
✗ Over-age the dog

═══════════════════════════════════════════════════════
📸 OUTPUT SPECIFICATIONS:
═══════════════════════════════════════════════════════
• Quality: Ultra-high resolution, professional photography
• Style: Photorealistic, natural aging
• Accuracy: Scientifically correct for {$breed}
• Lighting: Match original perfectly
• Detail: Sharp focus, crystal clear
• Believability: 100% realistic transformation

Generate the transformed image showing this {$breed} aged exactly 1 year with all changes applied naturally and professionally.";
    } else {
      // 3 YEARS - SENIOR DOG
      $prompt = "🎯 EXPERT PET PHOTOGRAPHY TRANSFORMATION TASK

Transform this {$breed} dog to show REALISTIC SENIOR DOG AGING after EXACTLY 3 YEARS.

═══════════════════════════════════════════════════════
🔬 SENIOR DOG AGING CHANGES (3 YEARS):
═══════════════════════════════════════════════════════

👁️ SENIOR EYE CHANGES:
• Lens: Noticeable cloudiness/haziness (cataract-like)
• Opacity: 40-50% cloudier than young
• Brightness: Significantly reduced (40-50% less sparkle)
• Color: Dull with slight milkiness
• Tint: Possible light blue/gray lens tint
• Tear stains: More prominent

🎨 SENIOR COAT TRANSFORMATION:";

      switch ($coat) {
        case 'curly/fluffy':
          $prompt .= "\n• Texture: 40-50% less fluffy, very wiry
• Structure: Coarse replacing soft curls
• Thinning: Noticeable, patchy areas visible
• Softness: Lost most puppy quality
• Appearance: Dull, dry, aged";
          break;

        case 'double_coat':
          $prompt .= "\n• Undercoat: 40-50% thinner, patchy
• Guard hairs: Very rough and coarse
• Plushness: Significant loss
• Thinning: Visible on flanks/tail
• Shine: Completely gone";
          break;

        case 'long_silky':
          $prompt .= "\n• Shine: Completely lost, very dull
• Texture: Significantly coarse
• Thinning: Possible visible areas
• Silkiness: All gone
• Quality: Dry, brittle appearance";
          break;

        default:
          $prompt .= "\n• Texture: Very rough and dull
• Quality: 30-40% loss of youth
• Appearance: Dry, brittle
• Color: Noticeable fading
• Overall: Clearly aged coat";
      }

      $prompt .= "\n\n😺 SENIOR FACIAL AGING:";

      if ($isBrachy) {
        $prompt .= "\n• Wrinkles: 40-50% DEEPER, extensive
• Sagging: Pronounced facial skin
• Jowls: VERY loose, prominent
• Around eyes/nose: Deeply set wrinkles
• Overall: Unmistakably senior face";
      } else {
        $prompt .= "\n• Sagging: Clear visible on jowls/under eyes
• Skin: Loose around muzzle and face
• Lines: Deeper expression lines
• Eyes: Prominent aging around them
• Overall: Senior face unmistakable";
      }

      $prompt .= "\n\n⚪ SENIOR GRAYING PATTERN:";

      switch ($grayPattern) {
        case 'minimal':
          $prompt .= "\n• Color change: 30-40% darker/faded
• Aging: Natural dulling and darkening
• Overall: Washed out appearance
• Gray: None (breed characteristic)";
          break;

        case 'prominent':
          $prompt .= "\n• Muzzle: Extensive silver/gray coverage
• Spread: Gray on eyebrows, forehead
• Eyes/ears: Graying around them
• Coverage: 40-60% of face gray
• Pattern: Clear senior graying";
          break;

        default:
          $prompt .= "\n• Muzzle: 30-50% gray coverage
• Spread: Gray around face and eyes
• Distribution: Scattered on head/ears
• Pattern: Age-appropriate for {$breed}";
      }

      $prompt .= "\n\n💪 SENIOR BODY CHANGES:";

      if ($size === 'giant') {
        $prompt .= "\n• Posture: Possible slight stiffness
• Muscle: 20-30% tone reduction
• Weight: Redistribution, less athletic
• Stance: Slightly hunched/relaxed
• Overall: Clear senior physique";
      } elseif ($size === 'toy' || $size === 'small') {
        $prompt .= "\n• Build: Compact but clearly senior
• Muscle: 10-20% softening
• Aging: Subtle throughout body
• Appearance: Less sprightly";
      } else {
        $prompt .= "\n• Muscle: 20-30% tone reduction
• Weight: Slight changes visible
• Body: Less toned, softer
• Physique: Senior unmistakable";
      }

      $prompt .= "\n\n😌 SENIOR EXPRESSION:
• Demeanor: Clearly wise, senior dog
• Eyes: Calm, aged, experienced
• Alertness: Less alert (not sad, just aged)
• Wisdom: Natural senior dignity
• Overall: Healthy senior appearance

═══════════════════════════════════════════════════════
🚫 CRITICAL PRESERVATION REQUIREMENTS:
═══════════════════════════════════════════════════════
✓ EXACT breed characteristics  
✓ IDENTICAL coat base color
✓ SAME pattern (only add gray)
✓ ALL distinctive markings
✓ EXACT pose and position
✓ SAME background
✓ MATCHING lighting

❌ DO NOT:
✗ Change breed appearance
✗ Alter markings/features
✗ Change pose or background
✗ Make dog look sick (healthy senior only!)

═══════════════════════════════════════════════════════
📸 OUTPUT SPECIFICATIONS:
═══════════════════════════════════════════════════════
• Quality: Ultra-high resolution professional
• Realism: 100% photorealistic senior transformation
• Health: Healthy senior (not ill)
• Accuracy: Scientifically correct aging
• Detail: Crystal clear, sharp focus
• Lighting: Perfect match to original

Generate the transformed image showing this {$breed} as a healthy, dignified senior dog aged exactly 3 years with all changes applied naturally.";
    }

    // Add breed-specific traits
    if (!empty($profile['specific_traits'])) {
      $prompt .= "\n\n🧬 BREED-SPECIFIC NOTES:\n";
      foreach (array_slice($profile['specific_traits'], 0, 3) as $trait) {
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
      $profile['specific_traits'][] = 'Toy breeds age slower, maintain youthful features longer';
    } elseif ($this->matchBreed($breedLower, ['corgi', 'beagle', 'french bulldog', 'boston terrier', 'cocker spaniel'])) {
      $profile['size_category'] = 'small';
      $profile['aging_speed'] = 'slow';
      $profile['specific_traits'][] = 'Small breeds age gracefully';
    } elseif ($this->matchBreed($breedLower, ['great dane', 'mastiff', 'saint bernard', 'newfoundland', 'wolfhound', 'leonberger', 'bernese'])) {
      $profile['size_category'] = 'giant';
      $profile['aging_speed'] = 'fast';
      $profile['specific_traits'][] = 'Giant breeds age faster with earlier senior signs';
    } elseif ($this->matchBreed($breedLower, ['german shepherd', 'golden retriever', 'labrador', 'rottweiler', 'doberman', 'boxer', 'husky'])) {
      $profile['size_category'] = 'large';
      $profile['specific_traits'][] = 'Large breeds show moderate aging';
    }

    // COAT TYPES
    if ($this->matchBreed($breedLower, ['poodle', 'bichon', 'maltese', 'shih tzu', 'lhasa apso', 'havanese'])) {
      $profile['coat_type'] = 'curly/fluffy';
      $profile['specific_traits'][] = 'Curly coat becomes wiry, loses fluffiness';
    } elseif ($this->matchBreed($breedLower, ['husky', 'malamute', 'samoyed', 'chow', 'akita'])) {
      $profile['coat_type'] = 'double_coat';
      $profile['specific_traits'][] = 'Double coat thins significantly with age';
    } elseif ($this->matchBreed($breedLower, ['golden retriever', 'cocker spaniel', 'setter', 'cavalier'])) {
      $profile['coat_type'] = 'long_silky';
      $profile['specific_traits'][] = 'Silky coat loses shine, becomes coarser';
    }

    // GRAYING PATTERNS
    if ($this->matchBreed($breedLower, ['rottweiler', 'doberman', 'black lab', 'pug', 'pit bull', 'scottish'])) {
      $profile['gray_pattern'] = 'minimal';
      $profile['specific_traits'][] = 'Dark coat darkens/dulls rather than grays';
    } elseif ($this->matchBreed($breedLower, ['german shepherd', 'schnauzer', 'yorkshire', 'weimaraner'])) {
      $profile['gray_pattern'] = 'prominent';
      $profile['specific_traits'][] = 'Grays prominently on muzzle and face';
    }

    // BRACHYCEPHALIC (FLAT-FACED)
    if ($this->matchBreed($breedLower, ['pug', 'french bulldog', 'boston terrier', 'shih tzu', 'bulldog', 'boxer', 'mastiff'])) {
      $profile['brachycephalic'] = true;
      $profile['specific_traits'][] = 'Facial wrinkles deepen significantly with age';
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
        // FIX: Use the correct sharpen filter with matrix
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
