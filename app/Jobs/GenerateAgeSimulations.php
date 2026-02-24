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
      Log::info("🐕 AGE SIMULATION STARTED", [
        'result_id' => $this->resultId,
        'breed' => $this->breed,
      ]);

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      $this->updateStatus($result, 'generating', []);

      $imageData = $this->prepareHighQualityImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception("Failed to prepare image");
      }

      $breedProfile = $this->getBreedProfile($this->breed);
      Log::info("📊 Breed Profile", $breedProfile);

      $simulations = $this->generateTransformations($imageData, $breedProfile);

      $savedPaths = [
        '1_years' => null,
        '3_years' => null
      ];

      if (isset($simulations['1_year']) && $simulations['1_year']) {
        $savedPaths['1_years'] = $this->saveImage($simulations['1_year'], '1_year', $this->resultId);
        Log::info("✅ 1-year saved: {$savedPaths['1_years']}");
      }

      if (isset($simulations['3_years']) && $simulations['3_years']) {
        $savedPaths['3_years'] = $this->saveImage($simulations['3_years'], '3_years', $this->resultId);
        Log::info("✅ 3-years saved: {$savedPaths['3_years']}");
      }

      $this->updateStatus($result, 'complete', $savedPaths, $breedProfile);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("🎉 SIMULATION COMPLETE in {$elapsed}s");
    } catch (\Exception $e) {
      Log::error("❌ SIMULATION FAILED", [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
      ]);
      if (isset($result)) {
        $this->updateStatus($result, 'failed', [], [], $e->getMessage());
      }
    }
  }

  /**
   * Generate transformations via Gemini
   */
  private function generateTransformations($imageData, $breedProfile)
  {
    $client = new Client(['timeout' => 120, 'connect_timeout' => 10]);

    $results = ['1_year' => null, '3_years' => null];

    $prompt1Year  = $this->buildAgingPrompt($breedProfile, 1);
    $prompt3Years = $this->buildAgingPrompt($breedProfile, 3);

    $maxAttempts = 3;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
      try {
        Log::info("🔄 Attempt " . ($attempt + 1) . "/{$maxAttempts}");

        $promises = [];
        if (!$results['1_year'])  $promises['1_year']  = $this->createGenerationPromise($client, $prompt1Year,  $imageData);
        if (!$results['3_years']) $promises['3_years'] = $this->createGenerationPromise($client, $prompt3Years, $imageData);

        if (empty($promises)) break;

        $settled = Promise\Utils::settle($promises)->wait();

        foreach ($settled as $key => $result) {
          if ($result['state'] === 'fulfilled') {
            $results[$key] = $result['value'];
            Log::info("✅ {$key} succeeded");
          } else {
            Log::warning("⚠️ {$key} failed: " . $result['reason']->getMessage());
          }
        }

        if ($results['1_year'] && $results['3_years']) break;

        if ($attempt < $maxAttempts - 1) {
          $delay = pow(2, $attempt + 1);
          Log::info("⏳ Backing off {$delay}s");
          sleep($delay);
        }
      } catch (\Exception $e) {
        Log::error("Attempt {$attempt} error: " . $e->getMessage());
        if ($attempt < $maxAttempts - 1) sleep(3 * ($attempt + 1));
      }
    }

    return $results;
  }

  /**
   * Create async promise for Gemini call
   */
  private function createGenerationPromise($client, $prompt, $imageData)
  {
    $apiKey    = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    $modelName = 'gemini-2.0-flash-exp-image-generation';
    $endpoint  = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $payload = [
      'contents' => [[
        'parts' => [
          ['text' => $prompt],
          ['inlineData' => ['mimeType' => $imageData['mimeType'], 'data' => $imageData['base64']]]
        ]
      ]],
      'generationConfig' => [
        'temperature'        => 0.3,
        'topK'               => 32,
        'topP'               => 0.85,
        'maxOutputTokens'    => 8192,
        'responseModalities' => ['IMAGE', 'TEXT'],
      ],
      'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
      ]
    ];

    return $client->postAsync($endpoint, [
      'json'    => $payload,
      'headers' => ['Content-Type' => 'application/json']
    ])->then(function ($response) {
      return $this->extractImage($response);
    });
  }

  /**
   * Extract image bytes from API response
   */
  private function extractImage($response)
  {
    $responseData = json_decode($response->getBody()->getContents(), true);

    if (!isset($responseData['candidates'][0])) {
      Log::error("No candidates", ['response' => $responseData]);
      throw new \Exception("No image generated");
    }

    $parts = $responseData['candidates'][0]['content']['parts'] ?? [];

    foreach ($parts as $part) {
      if (isset($part['inlineData']['data'])) {
        Log::info("✅ Image from inlineData");
        return base64_decode($part['inlineData']['data']);
      }
    }

    foreach ($parts as $part) {
      if (isset($part['text'])) {
        $text   = preg_replace('/```[\w]*\n?/', '', $part['text']);
        $text   = trim($text);
        $decoded = base64_decode($text, true);
        if ($decoded && strlen($decoded) > 1000) {
          Log::info("✅ Image from text block");
          return $decoded;
        }
      }
    }

    throw new \Exception("No image data found in response");
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  CORE: Breed-aware realistic aging prompt
  // ─────────────────────────────────────────────────────────────────────────

  /**
   * Build a realistic, healthy, breed-accurate aging prompt.
   *
   * Key philosophy:
   *  • Aging = natural biological maturation, NOT neglect / illness
   *  • Show physical changes specific to this breed (size, coat, face)
   *  • Dog must look healthy, well-groomed, and HAPPY at every age
   *  • Identity (color, markings, pose, background) must be preserved
   */
  private function buildAgingPrompt($profile, $years)
  {
    $breed    = $profile['breed'];
    $size     = $profile['size_category'];
    $coat     = $profile['coat_type'];
    $isBrachy = $profile['brachycephalic'];
    $maturity = $profile['maturity_changes'];

    if ($years === 1) {
      $ageStage   = "1 year older — healthy young adult";
      $coatChange = $this->coatChange1Year($coat, $size);
      $bodyChange = $this->bodyChange1Year($size, $isBrachy, $profile);
      $faceChange = $this->faceChange1Year($isBrachy, $profile);
      $grayChange = "No gray hairs — this dog is in its prime.";
      $healthNote = "Vibrant, healthy, well-fed, well-groomed. Full of energy.";
    } else {
      $ageStage   = "3 years older — fully mature adult in excellent health";
      $coatChange = $this->coatChange3Years($coat, $size);
      $bodyChange = $this->bodyChange3Years($size, $isBrachy, $profile);
      $faceChange = $this->faceChange3Years($isBrachy, $profile);
      $grayChange = $this->grayChange3Years($profile);
      $healthNote = "Strong, healthy, calm, well-groomed. A confident well-cared-for adult.";
    }

    // Build prompt as plain string to avoid heredoc PHP interpolation issues
    $lines = [];
    $lines[] = "You are a photo editor. Your ONLY job is to age the dog in this photo by {$years} year(s).";
    $lines[] = "";
    $lines[] = "=== STEP 1: COPY THE ENTIRE SCENE EXACTLY ===";
    $lines[] = "Reproduce EVERY detail of the input photo with 100% fidelity:";
    $lines[] = "- EXACT same background: every color, object, texture, and lighting";
    $lines[] = "- EXACT same camera angle and zoom level";
    $lines[] = "- EXACT same pose of the dog";
    $lines[] = "- If the dog is being held by a person, those hands/arms are still there in the same position";
    $lines[] = "- If there are people in the photo, they remain in exactly the same position";
    $lines[] = "- EXACT same floor, walls, furniture, and environment";
    $lines[] = "- EXACT same coat color and markings on the dog";
    $lines[] = "";
    $lines[] = "FORBIDDEN CHANGES (any of these = failure):";
    $lines[] = "- Replacing background with white, gray, or studio backdrop";
    $lines[] = "- Changing camera angle to portrait/headshot";
    $lines[] = "- Removing people, hands, or objects from the scene";
    $lines[] = "- Changing the dog pose or position";
    $lines[] = "- Changing coat base colors or markings";
    $lines[] = "";
    $lines[] = "=== STEP 2: APPLY ONLY THESE AGING CHANGES TO THE DOG ===";
    $lines[] = "Breed: {$breed}";
    $lines[] = "Age stage: {$ageStage}";
    $lines[] = "";
    $lines[] = "BODY SIZE: {$bodyChange}";
    $lines[] = "FACE: {$faceChange}";
    $lines[] = "COAT TEXTURE: {$coatChange}";
    $lines[] = "GRAYING: {$grayChange}";
    $lines[] = "";
    $lines[] = "Breed notes: {$maturity}";
    $lines[] = "";
    $lines[] = "=== HEALTH RULE (CRITICAL) ===";
    $lines[] = "{$healthNote}";
    $lines[] = "The dog must look clean, healthy, and well cared for.";
    $lines[] = "Do NOT make the dog look sick, mangy, matted, or neglected.";
    $lines[] = "This is natural biological aging — the dog is thriving.";
    $lines[] = "";
    $lines[] = "=== FINAL CHECKLIST (verify before output) ===";
    $lines[] = "- Background matches the input photo exactly";
    $lines[] = "- Camera angle and framing match exactly";
    $lines[] = "- All people/hands/objects from original are still present";
    $lines[] = "- Dog coat colors and markings are preserved";
    $lines[] = "- Dog looks healthy, happy, and well-groomed";
    $lines[] = "- Only the dog age has changed — nothing else";
    $lines[] = "";
    $lines[] = "Output the photo-edited image now.";

    return implode("
", $lines);
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  PER-COAT / PER-BODY / PER-FACE / GRAY helpers  (1-year vs 3-year)
  // ─────────────────────────────────────────────────────────────────────────

  private function coatChange1Year($coat, $size)
  {
    return match ($coat) {
      'curly/fluffy' => "Coat is slightly fuller and more settled — the puppy fluffiness is transitioning into the breed's adult plush coat. Still very fluffy, very clean.",
      'double_coat'  => "Adult double coat is developing — thicker and more defined undercoat starting to show. Coat looks lush and healthy.",
      'long_silky'   => "Coat is reaching adult length — silky, flowing, and well-groomed. Perhaps slightly longer than before.",
      'wire'         => "Wiry texture becoming more defined — characteristic breed texture more prominent but tidy.",
      'short'        => "Adult short coat fully developed — smooth, glossy, healthy sheen.",
      default        => "Coat has fully matured into the breed's adult coat — healthy, clean, well-groomed.",
    };
  }

  private function coatChange3Years($coat, $size)
  {
    return match ($coat) {
      'curly/fluffy' => "Coat is at its full adult glory — dense, well-formed curls or plush fur at peak condition. Clean and well-groomed.",
      'double_coat'  => "Dense, full double coat — thick and healthy. Coat is rich in color and texture.",
      'long_silky'   => "Coat is at its longest adult length — flowing, glossy, well-maintained. Some natural texture compared to puppy coat but still beautiful.",
      'wire'         => "Wiry coat fully expressed — characteristic scruffy-but-tidy look of the mature breed.",
      'short'        => "Short coat is glossy and healthy — fits the mature body well.",
      default        => "Mature adult coat — full, healthy, well-maintained.",
    };
  }

  private function bodyChange1Year($size, $isBrachy, $profile)
  {
    $grows = $profile['grows_significantly'] ?? false;

    if ($grows) {
      return match ($size) {
        'giant' => "Body noticeably larger and taller — the breed is a giant breed and has grown significantly. Legs are longer, chest is deeper, overall frame is much bigger but still lean / filling out.",
        'large' => "Body larger and more muscular — this large breed has grown substantially. Taller, broader chest, longer legs. Looks like a young adult dog with a bigger frame.",
        'medium' => "Body slightly larger and more filled out — transitioning from puppy proportions to adult frame. Slightly taller, longer legs.",
        default => "Body slightly larger and more filled-out than puppy stage.",
      };
    }

    return match ($size) {
      'toy'   => "Body fully adult-sized — compact and proportionate. Slightly more filled-out than puppy. Same small, dainty frame.",
      'small' => "Body is adult-sized — compact, slightly more muscular than puppy. Well-proportioned.",
      'medium' => "Body transitioning to full adult size — slightly more filled-out, longer legs, deeper chest.",
      'large' => "Body mostly adult-sized — strong, muscular, well-proportioned young adult.",
      'giant' => "Body large and still filling out — noticeably bigger than puppy, approaching full giant-breed size.",
      default => "Body is adult-sized and well-proportioned.",
    };
  }

  private function bodyChange3Years($size, $isBrachy, $profile)
  {
    return match ($size) {
      'toy'    => "Body at full adult size — compact, firm, healthy. Excellent muscle tone for a small dog.",
      'small'  => "Body fully mature — compact and muscular. Excellent condition.",
      'medium' => "Body in full adult prime — strong, well-muscled, balanced proportions.",
      'large'  => "Body at peak adult condition — powerful, well-muscled, deep chest, strong legs.",
      'giant'  => "Body fully mature at its large size — massive, solid, but healthy and well-proportioned. Carries weight well.",
      default  => "Body in full adult condition — strong, healthy, well-proportioned.",
    };
  }

  private function faceChange1Year($isBrachy, $profile)
  {
    if ($isBrachy) {
      return "Face developing adult proportions — slightly broader head, wrinkles beginning to form naturally. Still expressive and alert.";
    }
    $muzzle = $profile['muzzle_change'] ?? "Muzzle slightly longer and more defined than puppy. Face more angular and alert — the 'baby face' is transitioning to young adult features.";
    return $muzzle;
  }

  private function faceChange3Years($isBrachy, $profile)
  {
    if ($isBrachy) {
      return "Face fully mature — broader head, natural wrinkles of the breed more defined. Expressive, calm, confident look.";
    }
    return "Face fully mature — strong, well-defined muzzle and jawline. Calm, wise, confident expression. The face has lost the rounded puppy look and gained adult character.";
  }

  private function grayChange3Years($profile)
  {
    return match ($profile['gray_pattern']) {
      'none'      => "No gray hairs — this breed does not gray noticeably even as an adult. Coat color remains vivid.",
      'minimal'   => "Possibly a few light hairs on the muzzle tip — very subtle and breed-appropriate. Color otherwise unchanged.",
      'moderate'  => "A light dusting of gray/silver hairs on the muzzle and around the eyes — natural and distinguished. The base coat color is fully preserved.",
      'prominent' => "Noticeable silver/gray hairs on the muzzle, chin, and around the eyes — a natural and handsome sign of maturity. Underlying coat color preserved.",
      default     => "Subtle, natural graying only where the breed typically grays first.",
    };
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  BREED PROFILE  (expanded with physical-maturation data)
  // ─────────────────────────────────────────────────────────────────────────

  private function getBreedProfile($breed)
  {
    $b = strtolower($breed);

    $profile = [
      'breed'               => $breed,
      'size_category'       => 'medium',
      'coat_type'           => 'medium',
      'gray_pattern'        => 'moderate',
      'aging_speed'         => 'normal',
      'brachycephalic'      => false,
      'grows_significantly' => false,   // true = visible size increase expected
      'muzzle_change'       => null,
      'maturity_changes'    => '',
    ];

    // ── SIZE ────────────────────────────────────────────────────────────────
    if ($this->mb($b, ['chihuahua', 'pomeranian', 'yorkshire terrier', 'yorkie', 'papillon', 'maltese', 'toy poodle', 'italian greyhound', 'miniature pinscher'])) {
      $profile['size_category'] = 'toy';
      $profile['grows_significantly'] = false;
      $profile['maturity_changes'] = "Toy breed — adult size is reached early. Very little size change after 6–8 months. Focus on coat maturation and slight facial refinement.";
    } elseif ($this->mb($b, ['shih tzu', 'french bulldog', 'boston terrier', 'cocker spaniel', 'beagle', 'corgi', 'dachshund', 'scottish terrier', 'west highland', 'westie', 'cavalier', 'bichon', 'lhasa apso', 'havanese', 'miniature schnauzer', 'miniature poodle'])) {
      $profile['size_category'] = 'small';
      $profile['grows_significantly'] = false;
      $profile['maturity_changes'] = "Small breed — adult size reached by 10–12 months. Aging shows mainly in coat maturation and slight facial definition. Stays compact.";
    } elseif ($this->mb($b, ['great dane', 'mastiff', 'saint bernard', 'newfoundland', 'irish wolfhound', 'leonberger', 'bernese mountain dog', 'great pyrenees', 'tibetan mastiff'])) {
      $profile['size_category'] = 'giant';
      $profile['grows_significantly'] = true;
      $profile['maturity_changes'] = "Giant breed — takes 18–24 months to reach full adult size. At +1 year: noticeably taller and heavier, deeper chest, longer legs but still lean. At +3 years: massive, solid, fully mature giant dog.";
    } elseif ($this->mb($b, ['german shepherd', 'golden retriever', 'labrador', 'rottweiler', 'doberman', 'boxer', 'husky', 'malamute', 'standard poodle', 'border collie', 'australian shepherd', 'weimaraner', 'vizsla', 'dalmatian', 'samoyed'])) {
      $profile['size_category'] = 'large';
      $profile['grows_significantly'] = true;
      $profile['maturity_changes'] = "Large breed — full adult size reached around 12–18 months. Noticeable growth in height and muscle from puppy to 1 year. At 3 years: peak muscular condition.";
    } else {
      $profile['size_category'] = 'medium';
      $profile['grows_significantly'] = false;
      $profile['maturity_changes'] = "Medium breed — adult size reached around 10–14 months. Some filling-out of the chest and musculature expected.";
    }

    // ── COAT ────────────────────────────────────────────────────────────────
    if ($this->mb($b, ['poodle', 'bichon', 'maltese', 'shih tzu', 'lhasa apso', 'havanese', 'pomeranian', 'chow chow', 'keeshond', 'american eskimo', 'samoyed'])) {
      $profile['coat_type'] = 'curly/fluffy';
    } elseif ($this->mb($b, ['husky', 'malamute', 'akita', 'bernese mountain dog', 'great pyrenees', 'german shepherd', 'leonberger', 'newfoundland', 'norwegian elkhound'])) {
      $profile['coat_type'] = 'double_coat';
    } elseif ($this->mb($b, ['golden retriever', 'cocker spaniel', 'irish setter', 'cavalier king charles', 'afghan hound', 'yorkshire terrier'])) {
      $profile['coat_type'] = 'long_silky';
    } elseif ($this->mb($b, ['wire fox terrier', 'airedale', 'border terrier', 'jack russell', 'dachshund wire', 'scottish terrier', 'westie', 'welsh terrier'])) {
      $profile['coat_type'] = 'wire';
    } elseif ($this->mb($b, ['labrador', 'boxer', 'doberman', 'rottweiler', 'vizsla', 'weimaraner', 'dalmatian', 'great dane', 'whippet', 'italian greyhound', 'beagle', 'boston terrier'])) {
      $profile['coat_type'] = 'short';
    }

    // ── GRAYING ─────────────────────────────────────────────────────────────
    if ($this->mb($b, ['samoyed', 'bichon', 'maltese', 'white swiss shepherd', 'great pyrenees', 'american eskimo'])) {
      $profile['gray_pattern'] = 'none'; // already white
    } elseif ($this->mb($b, ['rottweiler', 'black labrador', 'black lab', 'pug', 'scottish terrier', 'doberman'])) {
      $profile['gray_pattern'] = 'minimal';
    } elseif ($this->mb($b, ['german shepherd', 'schnauzer', 'yorkshire terrier', 'weimaraner', 'border collie', 'australian shepherd'])) {
      $profile['gray_pattern'] = 'prominent';
    } else {
      $profile['gray_pattern'] = 'moderate';
    }

    // ── BRACHYCEPHALIC ──────────────────────────────────────────────────────
    if ($this->mb($b, ['pug', 'french bulldog', 'boston terrier', 'shih tzu', 'bulldog', 'boxer', 'mastiff', 'pekinese', 'pekingese', 'chow chow'])) {
      $profile['brachycephalic'] = true;
    }

    // ── BREED-SPECIFIC MUZZLE NOTES ─────────────────────────────────────────
    if ($this->mb($b, ['pomeranian'])) {
      $profile['muzzle_change'] = "Muzzle is slightly more defined than puppy. Head shape more refined. The fox-like adult face is emerging.";
      $profile['maturity_changes'] .= " Pomeranians lose their puppy 'cloud' fluffiness and develop the adult double coat with a defined ruff around the neck.";
    } elseif ($this->mb($b, ['golden retriever'])) {
      $profile['maturity_changes'] .= " Golden retrievers develop a fuller, darker golden coat with age. The face broadens and gains a calm, gentle expression.";
    } elseif ($this->mb($b, ['german shepherd'])) {
      $profile['maturity_changes'] .= " German Shepherds develop a broader, more angular head and a deeper, more muscular chest as adults.";
    } elseif ($this->mb($b, ['labrador'])) {
      $profile['maturity_changes'] .= " Labradors fill out significantly in the chest and hindquarters. Adult coat is short and dense.";
    } elseif ($this->mb($b, ['husky'])) {
      $profile['maturity_changes'] .= " Huskies develop a fuller, denser double coat and a more powerful build as adults.";
    } elseif ($this->mb($b, ['great dane'])) {
      $profile['maturity_changes'] .= " Great Danes grow dramatically — one of the tallest breeds. At 1 year they look like a big-boned adolescent; at 3 years they are a massive, regal giant.";
    }

    return $profile;
  }

  /**
   * Flexible breed name matching
   */
  private function mb($breedLower, $patterns)
  {
    foreach ($patterns as $pattern) {
      if (stripos($breedLower, $pattern) !== false) return true;
    }
    return false;
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  IMAGE HELPERS  (unchanged from original)
  // ─────────────────────────────────────────────────────────────────────────

  private function prepareHighQualityImage($fullPath)
  {
    try {
      $cacheKey = "hq_img_" . md5($fullPath);
      return Cache::remember($cacheKey, 600, function () use ($fullPath) {
        $imageContents = Storage::disk('object-storage')->get($fullPath);
        if (empty($imageContents)) throw new \Exception('Empty image file');

        $imageInfo = @getimagesizefromstring($imageContents);
        if ($imageInfo === false) throw new \Exception('Invalid image');

        $width  = $imageInfo[0];
        $height = $imageInfo[1];
        $targetSize = 1024; // Smaller = faster API response

        if ($width > $targetSize || $height > $targetSize) {
          $imageContents = $this->resizeImage($imageContents, $targetSize);
        }

        $img = imagecreatefromstring($imageContents);
        if ($img === false) throw new \Exception('Failed to create image');

        ob_start();
        imagejpeg($img, null, 90);
        $optimized = ob_get_clean();
        imagedestroy($img);

        Log::info("✅ Image prepared: " . round(strlen($optimized) / 1024, 2) . " KB");
        return ['base64' => base64_encode($optimized), 'mimeType' => 'image/jpeg'];
      });
    } catch (\Exception $e) {
      Log::error("Image prep failed: " . $e->getMessage());
      return null;
    }
  }

  private function resizeImage($imageContents, $maxSize)
  {
    $source = imagecreatefromstring($imageContents);
    if ($source === false) throw new \Exception('Failed to create source image');

    $width  = imagesx($source);
    $height = imagesy($source);
    $ratio  = min($maxSize / $width, $maxSize / $height);
    $newW   = (int)($width * $ratio);
    $newH   = (int)($height * $ratio);

    $resized = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

    ob_start();
    imagejpeg($resized, null, 90);
    $output = ob_get_clean();

    imagedestroy($source);
    imagedestroy($resized);
    return $output;
  }

  private function saveImage($imageOutput, $type, $resultId)
  {
    try {
      $img = imagecreatefromstring($imageOutput);
      if ($img === false) throw new \Exception('Failed to create output image');

      ob_start();
      imagewebp($img, null, 88);
      $webpData = ob_get_clean();
      imagedestroy($img);

      $filename = "transform_{$resultId}_{$type}_" . time() . ".webp";
      $path     = "simulations/{$filename}";
      Storage::disk('object-storage')->put($path, $webpData);

      Log::info("💾 Saved: {$path} (" . round(strlen($webpData) / 1024, 2) . " KB)");
      return $path;
    } catch (\Exception $e) {
      Log::error("Save failed: " . $e->getMessage());
      return null;
    }
  }

  private function updateStatus($result, $status, $paths = [], $profile = [], $error = null)
  {
    $data = [
      'status'     => $status,
      '1_years'    => $paths['1_years'] ?? null,
      '3_years'    => $paths['3_years'] ?? null,
      'updated_at' => now()->toIso8601String()
    ];

    if (!empty($profile))  $data['breed_profile'] = $profile;
    if ($error)            $data['error']          = $error;

    $result->update(['simulation_data' => json_encode($data)]);
    Cache::forget("simulation_status_{$result->scan_id}");
    Cache::forget("sim_status_{$result->scan_id}");
  }
}
