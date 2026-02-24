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
    $grows    = $profile['grows_significantly'];

    // ── Build a vivid, specific description of what this dog looks like RIGHT NOW (puppy) ──
    $currentDesc = $this->describePuppyStage($size, $coat, $isBrachy);

    // ── Build a vivid, specific description of what adult looks like ──
    $adultDesc = $this->describeAdultStage($profile, $years);

    // ── Aging-specific physical changes ──
    $physicalChanges = $this->describePhysicalChanges($profile, $years);

    $lines = [];
    $lines[] = "PHOTO EDITING TASK: Transform this puppy into an adult {$breed} aged {$years} year(s) older.";
    $lines[] = "";
    $lines[] = "================================================================";
    $lines[] = "SCENE PRESERVATION (copy these EXACTLY, pixel-perfect):";
    $lines[] = "================================================================";
    $lines[] = "- Background: every object, color, texture, lighting — IDENTICAL";
    $lines[] = "- Camera angle and zoom — IDENTICAL";
    $lines[] = "- If the dog is being held, the hands/arms stay in the same position";
    $lines[] = "- All people, furniture, floor visible in the photo — IDENTICAL";
    $lines[] = "- Photo quality, grain, brightness — IDENTICAL";
    $lines[] = "FORBIDDEN: white background, studio backdrop, different camera angle, removed people";
    $lines[] = "";
    $lines[] = "================================================================";
    $lines[] = "THE DOG RIGHT NOW (puppy features to REPLACE):";
    $lines[] = "================================================================";
    $lines[] = $currentDesc;
    $lines[] = "";
    $lines[] = "================================================================";
    $lines[] = "WHAT THE DOG MUST LOOK LIKE IN {$years} YEAR(S) (apply ALL of these):";
    $lines[] = "================================================================";
    $lines[] = $adultDesc;
    $lines[] = "";
    $lines[] = "================================================================";
    $lines[] = "SPECIFIC PHYSICAL CHANGES TO MAKE:";
    $lines[] = "================================================================";
    $lines[] = $physicalChanges;
    $lines[] = "";
    $lines[] = "================================================================";
    $lines[] = "HEALTH RULE — the dog must look:";
    $lines[] = "================================================================";
    $lines[] = "- Healthy, well-fed, well-groomed, clean coat";
    $lines[] = "- Happy or calm expression — NOT sad, sick, thin, or neglected";
    $lines[] = "- Natural biological aging — this dog is THRIVING";
    $lines[] = "";
    $lines[] = "VERIFY BEFORE OUTPUTTING:";
    $lines[] = "[ ] Dog is visibly and clearly older/bigger than in the input photo";
    $lines[] = "[ ] Background matches input exactly";
    $lines[] = "[ ] Coat color and markings are preserved";
    $lines[] = "[ ] Dog looks healthy and well-groomed";
    $lines[] = "[ ] Puppy proportions (big head, small body) are gone — replaced by adult proportions";
    $lines[] = "";
    $lines[] = "Output the transformed image now.";

    return implode("
", $lines);
  }

  /**
   * Describe the puppy stage clearly so the model knows what to REPLACE
   */
  private function describePuppyStage($size, $coat, $isBrachy)
  {
    $lines = [];
    $lines[] = "- Oversized head relative to body (puppy proportion)";
    $lines[] = "- Short, stubby legs relative to body";
    $lines[] = "- Round, soft puppy face with chubby cheeks";
    $lines[] = "- Small, compact body";
    $lines[] = "- Soft, thin puppy coat (not fully developed)";
    $lines[] = "- Wide, innocent, large-looking eyes relative to face";
    $lines[] = "- Small, underdeveloped muzzle";
    $lines[] = "- Belly may be slightly round/pudgy (puppy belly)";
    $lines[] = "- Floppy, unsteady energy — everything looks soft and small";
    return implode("
", $lines);
  }

  /**
   * Describe exactly what the adult dog should look like at 1yr or 3yr
   */
  private function describeAdultStage($profile, $years)
  {
    $breed       = $profile['breed'];
    $size        = $profile['size_category'];
    $bodyShape   = $profile['body_shape'] ?? 'standard';
    $coat        = $profile['coat_type'];
    $isBrachy    = $profile['brachycephalic'];
    $grows       = $profile['grows_significantly'];
    $sizeNote    = $profile['size_note'] ?? '';
    $adultBody   = $profile['adult_body_note'] ?? '';
    $adultFace   = $profile['adult_face_note'] ?? '';

    $lines = [];

    if ($years === 1) {
      $lines[] = "AGE TARGET: 1 year old {$breed} — young adult";
      $lines[] = "";
      $lines[] = "SIZE & GROWTH:";
      $lines[] = $sizeNote;

      if ($grows && in_array($size, ['large', 'giant'])) {
        $lines[] = "At 1 year: body is CLEARLY AND DRAMATICALLY LARGER than the puppy in the input photo.";
        $lines[] = "Legs are much longer. Chest is deeper. Overall frame is significantly bigger.";
        $lines[] = "This is a large/giant breed — the growth difference must be OBVIOUS.";
      } elseif ($grows && $size === 'medium') {
        $lines[] = "At 1 year: body is noticeably bigger — taller, longer legs, deeper chest.";
        $lines[] = "The growth is visible and clear, not subtle.";
      } elseif (in_array($bodyShape, ['long_low']) || in_array($size, ['toy', 'small'])) {
        $lines[] = "At 1 year: body size is similar to puppy but proportions are more adult.";
        $lines[] = "The face is more defined, coat is more developed, but overall size change is minimal.";
      }

      $lines[] = "";
      $lines[] = "ADULT BODY (what the dog's body must look like):";
      $lines[] = $adultBody;

      $lines[] = "";
      $lines[] = "ADULT FACE & HEAD (what the dog's face must look like):";
      $lines[] = $adultFace;

      $lines[] = "";
      $lines[] = "COAT AT 1 YEAR:";
      $lines[] = $this->coatChange1Year($coat, $size);

      $lines[] = "";
      $lines[] = "GRAYING AT 1 YEAR: None. This dog is young and in its prime. No gray hairs.";

      $lines[] = "";
      $lines[] = "EXPRESSION: Energetic, alert, curious young adult. Full of life.";
    } else {
      // 3 YEARS
      $lines[] = "AGE TARGET: 3 years old {$breed} — fully mature adult in peak condition";
      $lines[] = "";
      $lines[] = "SIZE & GROWTH:";
      $lines[] = $sizeNote;

      if ($grows && in_array($size, ['large', 'giant'])) {
        $lines[] = "At 3 years: body is at FULL ADULT SIZE — peak condition, maximum size for this breed.";
        $lines[] = "Muscular, powerful, fully filled-out. The puppy is now a completely grown adult.";
      } elseif ($grows && $size === 'medium') {
        $lines[] = "At 3 years: fully mature body — well-muscled, balanced, completely adult proportions.";
      } elseif (in_array($bodyShape, ['long_low']) || in_array($size, ['toy', 'small'])) {
        $lines[] = "At 3 years: full adult body — slightly heavier and more defined than puppy, but same distinctive body shape.";
      }

      $lines[] = "";
      $lines[] = "ADULT BODY (what the dog's body must look like):";
      $lines[] = $adultBody;

      $lines[] = "";
      $lines[] = "ADULT FACE & HEAD (what the dog's face must look like):";
      $lines[] = $adultFace;

      $lines[] = "";
      $lines[] = "COAT AT 3 YEARS:";
      $lines[] = $this->coatChange3Years($coat, $size);

      $lines[] = "";
      $lines[] = "GRAYING AT 3 YEARS:";
      $lines[] = $this->grayChange3Years($profile);

      $lines[] = "";
      $lines[] = "EXPRESSION: Calm, confident, wise, settled adult. Mature dignity.";
    }

    return implode("
", $lines);
  }

  private function describePhysicalChanges($profile, $years)
  {
    $size      = $profile['size_category'];
    $bodyShape = $profile['body_shape'] ?? 'standard';
    $grows     = $profile['grows_significantly'];

    $lines = [];
    $lines[] = "REMOVE these puppy features from the dog in the photo:";
    $lines[] = "  - Oversized round head relative to body → replace with proportionate adult head";
    $lines[] = "  - Short stubby legs → replace with longer, more muscular adult legs";
    $lines[] = "  - Chubby, round, soft face → replace with defined adult facial structure";
    $lines[] = "  - Thin, underdeveloped puppy coat → replace with full adult coat";
    $lines[] = "  - Pudgy, round puppy belly → replace with leaner adult torso";
    $lines[] = "  - Wide innocent puppy eyes relative to face → eyes now proportionate to adult head";
    $lines[] = "  - Wobbly, uncertain puppy posture → confident adult stance";
    $lines[] = "";

    if ($years === 1) {
      $lines[] = "ADD these 1-year-old adult features:";
      if ($grows && in_array($size, ['large', 'giant'])) {
        $lines[] = "  + MUCH taller and longer body — this is a large/giant breed growing fast";
        $lines[] = "  + Significantly longer, stronger legs";
        $lines[] = "  + Noticeably deeper and broader chest";
        $lines[] = "  + Lean adolescent muscle — not yet fully filled out but clearly much bigger";
      } elseif ($grows && $size === 'medium') {
        $lines[] = "  + Taller body — legs longer, chest deeper";
        $lines[] = "  + More muscular, leaner adult build";
      } elseif ($bodyShape === 'long_low') {
        $lines[] = "  + Body stays low and long (breed characteristic) — but more muscular";
        $lines[] = "  + Face more defined with adult muzzle length";
        $lines[] = "  + DO NOT make the dog taller — this breed is naturally low to ground";
      } else {
        $lines[] = "  + Slightly larger, more filled-out body";
        $lines[] = "  + More defined adult face and muzzle";
      }
      $lines[] = "  + Fuller, adult-developed coat";
      $lines[] = "  + More defined, angular adult face structure";
      $lines[] = "  + Proportionate adult head (not oversized like puppy)";
    } else {
      $lines[] = "ADD these 3-year-old fully mature adult features:";
      if ($grows && in_array($size, ['large', 'giant'])) {
        $lines[] = "  + FULLY GROWN body — maximum adult size for this breed";
        $lines[] = "  + Powerful, muscular build fully developed";
        $lines[] = "  + Complete adult proportions — nothing puppy-like remains";
      } elseif ($bodyShape === 'long_low') {
        $lines[] = "  + Body stays characteristically long and low (do NOT make taller)";
        $lines[] = "  + Heavier and more muscular than puppy but same low profile";
        $lines[] = "  + Fully adult face with complete muzzle development";
      } elseif ($bodyShape === 'stocky') {
        $lines[] = "  + Heavier, broader, more powerful stocky adult build";
        $lines[] = "  + Deeper chest, strong neck, solid legs";
      } elseif ($bodyShape === 'sighthound') {
        $lines[] = "  + Elegant, slender sighthound adult silhouette";
        $lines[] = "  + Arched back, tucked waist, very long legs fully developed";
        $lines[] = "  + DO NOT bulk up — this breed stays lean and slender";
      } else {
        $lines[] = "  + Full adult size and muscular development";
        $lines[] = "  + Complete adult body proportions";
      }
      $lines[] = "  + Fully mature adult coat at its best condition";
      $lines[] = "  + Complete adult face — strong, defined, no puppy softness";
      $lines[] = "  + Calm, settled, mature adult expression";
      if ($profile['gray_pattern'] !== 'none') {
        $lines[] = "  + Natural light graying on muzzle tip (breed-appropriate, very subtle)";
      }
    }

    return implode("
", $lines);
  }

  private function coatChange1Year($coat, $size)
  {
    return match ($coat) {
      'curly/fluffy' => "Fuller, more settled adult coat — puppy fuzz replaced by the breed's adult plush double coat. Clean, well-groomed, and fluffy.",
      'double_coat'  => "Adult double coat developing — thicker undercoat, denser guard hairs. Healthy lush coat.",
      'long_silky'   => "Coat reaching adult length — silky, flowing, well-groomed. Slightly longer than puppy coat.",
      'wire'         => "Wiry adult texture becoming defined — characteristic rough, dense texture of the breed. Tidy and well-kept.",
      'short'        => "Short adult coat fully developed — smooth, glossy, healthy sheen. Dense and close-lying.",
      default        => "Adult coat fully developed — healthy, clean, well-groomed.",
    };
  }

  private function coatChange3Years($coat, $size)
  {
    return match ($coat) {
      'curly/fluffy' => "Coat at full adult glory — dense, well-formed, plush at peak condition. Clean and well-groomed.",
      'double_coat'  => "Dense, full double coat at peak — rich in color and texture, thick undercoat and lustrous guard hairs.",
      'long_silky'   => "Coat at full adult length — flowing, silky, well-maintained. Beautiful and healthy.",
      'wire'         => "Wiry coat fully expressed — characteristic dense, rough texture of mature breed. Tidy.",
      'short'        => "Short coat glossy and healthy — fits the mature muscular body well. Dense and sleek.",
      default        => "Mature adult coat — full, healthy, clean, well-maintained.",
    };
  }

  private function grayChange3Years($profile)
  {
    return match ($profile['gray_pattern'] ?? 'moderate') {
      'none'     => "No gray hairs — this breed does not gray noticeably. Coat color remains vivid and rich.",
      'minimal'  => "Possibly a few light hairs on the muzzle tip — very subtle and barely noticeable. Color otherwise unchanged.",
      'moderate' => "Light dusting of gray/silver hairs on the muzzle tip and around the eyes — natural and distinguished. Base coat color fully preserved.",
      'prominent' => "Noticeable silver/gray hairs on the muzzle, chin, and around the eyes — natural, handsome sign of maturity. Underlying coat color preserved.",
      default    => "Subtle, natural graying only where the breed typically grays first — muzzle tip.",
    };
  }

  private function getBreedProfile($breed)
  {
    $b = strtolower($breed);

    // ── DEFAULT profile ──────────────────────────────────────────────────────
    $profile = [
      'breed'               => $breed,
      'size_category'       => 'medium',
      'body_shape'          => 'standard',   // standard | long_low | stocky | sighthound | square | athletic
      'coat_type'           => 'short',
      'gray_pattern'        => 'moderate',
      'brachycephalic'      => false,
      'grows_significantly' => false,
      'adult_body_note'     => '',           // precise adult body description
      'adult_face_note'     => '',           // precise adult face description
      'size_note'           => '',           // explicit size change instruction
    ];

    // ════════════════════════════════════════════════════════════════════════
    // BREED DATABASE — ordered from most specific to most general
    // Each entry sets: size, body_shape, coat, gray, brachy, grows, notes
    // ════════════════════════════════════════════════════════════════════════

    // ── TOY / VERY SMALL — barely change size ───────────────────────────────
    if ($this->mb($b, ['chihuahua'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note' => 'Chihuahuas are one of the smallest breeds. Adult size is nearly the same as puppy — no dramatic size increase.',
        'adult_body_note' => 'Compact, fine-boned tiny body. Legs stay short and delicate. Weight 2–3 kg. Same tiny frame as puppy but proportions become slightly more defined.',
        'adult_face_note' => 'Large rounded apple-dome head (breed characteristic — stays). Large ears fully erect. Eyes large and expressive. Face becomes slightly more refined but retains the characteristic large-eyed, round-headed look.',
      ]);
    } elseif ($this->mb($b, ['pomeranian'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern' => 'minimal',
        'size_note' => 'Pomeranians reach adult size quickly and stay very small. No height increase.',
        'adult_body_note' => 'Tiny, compact body hidden beneath a thick double coat. Fox-like frame. Weight 2–3.5 kg.',
        'adult_face_note' => 'Distinctive fox-like face with sharp pointed muzzle, alert eyes, and small erect ears. The rounded puppy face sharpens into the characteristic Pomeranian fox face. Thick ruff of fur around the neck develops.',
      ]);
    } elseif ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'prominent',
        'size_note' => 'Yorkshire Terriers stay tiny — 2–3 kg. No size increase.',
        'adult_body_note' => 'Very small, fine-boned, compact body. Long silky coat reaches the floor in adults.',
        'adult_face_note' => 'Small flat face with medium-length muzzle. V-shaped erect ears. Coat grows long and silky, parted down the middle. Classic steel blue and tan adult coloring develops.',
      ]);
    } elseif ($this->mb($b, ['maltese'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'Maltese stay tiny. Pure white long silky coat develops fully.',
        'adult_body_note' => 'Tiny compact body completely covered in long, flowing pure white silky coat.',
        'adult_face_note' => 'Gentle, sweet face. Medium muzzle, dark eyes, drop ears hidden under long white hair.',
      ]);
    } elseif ($this->mb($b, ['papillon'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Papillons stay small — 3–5 kg. Distinctive butterfly ears fully develop.',
        'adult_body_note' => 'Fine-boned, elegant tiny body with flowing coat.',
        'adult_face_note' => 'Distinctive large butterfly-shaped ears fully erect and fringed with long hair — signature breed feature. Fine-boned elegant face.',
      ]);
    } elseif ($this->mb($b, ['miniature pinscher', 'min pin'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'gray_pattern' => 'minimal',
        'size_note' => 'Min Pins stay small but develop a lean, muscular athletic build.',
        'adult_body_note' => 'Compact, muscular, athletic tiny body. High-stepping hackney gait. Very lean with defined muscle.',
        'adult_face_note' => 'Strong, narrow head. Cropped or natural erect ears. Alert, fearless expression.',
      ]);
    } elseif ($this->mb($b, ['italian greyhound'])) {
      $profile = array_merge($profile, [
        'size_category' => 'toy',
        'body_shape' => 'sighthound',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note' => 'Italian Greyhounds stay slender and small. Their sighthound shape becomes more defined.',
        'adult_body_note' => 'Extremely slender, elegant, arched back, deep narrow chest, tucked-up abdomen. Long thin legs. Graceful sighthound silhouette.',
        'adult_face_note' => 'Long, narrow, fine head. Large doe eyes. Folded-back ears when relaxed.',
      ]);

      // ── SMALL BREEDS — slight size increase, distinct body shapes ───────────
    } elseif ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'double_coat',
        'grows_significantly' => false,
        'gray_pattern' => 'moderate',
        'size_note' => 'Corgis are a long-and-low breed. They DO NOT grow tall. Adult Corgis stay close to the ground with short legs. The body gets longer and more muscular but height barely changes.',
        'adult_body_note' => 'Long body, very short legs (dwarf breed), deep chest, muscular hindquarters. Body length is much greater than height. Weight 10–14 kg. Stays low to the ground always.',
        'adult_face_note' => 'Fox-like face fully developed. Large upright pointed ears (fully erect in adults). Strong muzzle. Alert, intelligent expression.',
      ]);
    } elseif ($this->mb($b, ['dachshund', 'doxie', 'sausage dog', 'wiener'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note' => 'Dachshunds are extremely long and very low. They DO NOT grow tall — their legs stay very short. The body gets longer and heavier.',
        'adult_body_note' => 'Extremely elongated body, very short stubby legs, deep keel chest. The iconic sausage dog silhouette becomes even more pronounced. Weight 7–14 kg (standard) or 3–5 kg (miniature).',
        'adult_face_note' => 'Long tapered muzzle fully developed. Long floppy ears. Strong jaw. Confident, alert expression.',
      ]);
    } elseif ($this->mb($b, ['beagle'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'gray_pattern' => 'moderate',
        'size_note' => 'Beagles grow moderately — some height and width increase but stays compact.',
        'adult_body_note' => 'Solid, muscular, compact body. Deep chest, strong back, sturdy legs. Weight 9–11 kg. Sturdy, energetic hound build.',
        'adult_face_note' => 'Classic hound face — long, square muzzle, long floppy ears, large brown eyes, gentle expression. Dewlap (loose skin under chin) more visible.',
      ]);
    } elseif ($this->mb($b, ['french bulldog'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'French Bulldogs stay small and stocky. They get heavier and more muscular but not taller.',
        'adult_body_note' => 'Heavy, muscular, compact body. Very wide shoulders and chest, narrow hindquarters. Short, stocky legs. Weight 9–13 kg. Barrel-chested with a cobby build.',
        'adult_face_note' => 'Flat face fully developed with deep wrinkles/folds. Massive square head. Bat-like erect ears — breed signature. Very short pushed-in nose. Jowly, thick-set face.',
      ]);
    } elseif ($this->mb($b, ['boston terrier'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'size_note' => 'Boston Terriers stay small and square. Weight 5–11 kg. No significant height increase.',
        'adult_body_note' => 'Square, compact, muscular body. Well-balanced proportions, deep chest, short back.',
        'adult_face_note' => 'Square flat face, large round eyes, erect ears. Tuxedo coat pattern (black and white) becomes well-defined.',
      ]);
    } elseif ($this->mb($b, ['pug'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Pugs stay small and round. They may get heavier and rounder but not taller.',
        'adult_body_note' => 'Cobby, round, compact body. Heavy for size. Deep chest, wide body. Weight 6–9 kg.',
        'adult_face_note' => 'Massive round head, very flat face, deep wrinkles, bulging eyes, very short nose. Curly tail tightly curled. Classic pug expression.',
      ]);
    } elseif ($this->mb($b, ['shih tzu'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'size_note' => 'Shih Tzus stay small and compact. Long flowing coat develops fully.',
        'adult_body_note' => 'Compact, sturdy, slightly longer than tall. Weight 4–8 kg. Covered in long flowing double coat.',
        'adult_face_note' => 'Sweet flat face with long flowing facial hair. Large dark eyes, broad muzzle. Distinctive topknot of hair on head.',
      ]);
    } elseif ($this->mb($b, ['lhasa apso'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Lhasa Apsos stay small. Long heavy coat reaching the floor develops fully.',
        'adult_body_note' => 'Longer than tall, sturdy, well-developed body beneath a heavy, long, flowing coat.',
        'adult_face_note' => 'Heavy floor-length coat falls over the face. Strong muzzle, dark eyes. Dignified expression.',
      ]);
    } elseif ($this->mb($b, ['bichon frise', 'bichon'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'Bichon Frises stay small with a distinctive puffy white rounded coat.',
        'adult_body_note' => 'Small compact body completely covered in dense, curly, white coat trimmed into a rounded shape.',
        'adult_face_note' => 'Round, powder-puff face. Dark round eyes, black nose, surrounded by fluffy white coat.',
      ]);
    } elseif ($this->mb($b, ['cavalier king charles', 'cavalier'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Cavaliers stay small and elegant. Weight 5–8 kg.',
        'adult_body_note' => 'Small, elegant, graceful body with flowing silky coat on ears, chest, legs, and tail.',
        'adult_face_note' => 'Gentle, mournful large dark eyes. Long floppy silky ears. Sweet melting expression — breed signature.',
      ]);
    } elseif ($this->mb($b, ['cocker spaniel', 'english cocker', 'american cocker'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Cocker Spaniels grow moderately. Adult body is compact with heavy feathering on ears, legs, and belly.',
        'adult_body_note' => 'Compact, sturdy body with well-developed chest. Heavy silky feathering on ears, chest, legs.',
        'adult_face_note' => 'Broad, well-rounded head. Long, low-set, heavily feathered ears. Large, round, expressive eyes.',
      ]);
    } elseif ($this->mb($b, ['scottish terrier', 'scotty', 'westie', 'west highland'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'size_note' => 'Scottish/West Highland Terriers stay small and low-slung. Wiry coat becomes very defined.',
        'adult_body_note' => 'Compact, low-slung, very sturdy body. Short legs, barrel chest, thick wiry coat.',
        'adult_face_note' => 'Strong, wedge-shaped head with beard and eyebrows (furnishings) prominent. Erect pointed ears. Determined, feisty expression.',
      ]);
    } elseif ($this->mb($b, ['miniature schnauzer'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'gray_pattern' => 'prominent',
        'size_note' => 'Miniature Schnauzers stay small and square. Distinctive beard, eyebrows, and leg furnishings develop.',
        'adult_body_note' => 'Square build — height equals length. Compact, muscular, wiry-coated. Distinctive bushy eyebrows and beard.',
        'adult_face_note' => 'Rectangular strong head. Signature long bushy eyebrows and thick beard are key adult features. V-shaped folded ears.',
      ]);
    } elseif ($this->mb($b, ['havanese'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'Havanese stay small with a long, silky, flowing coat that develops with age.',
        'adult_body_note' => 'Small, sturdy body covered in long, silky, slightly wavy coat.',
        'adult_face_note' => 'Broad, rounded head, large almond eyes, drop ears with long silky hair. Sweet, alert expression.',
      ]);

      // ── MEDIUM BREEDS — moderate growth, varied body shapes ─────────────────
    } elseif ($this->mb($b, ['border collie'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Border Collies grow into a lean, athletic medium-sized dog. Noticeably taller and longer than puppy.',
        'adult_body_note' => 'Athletic, lithe, graceful body built for endurance. Lean muscle, not bulky. Well-proportioned, agile frame. Weight 14–20 kg.',
        'adult_face_note' => 'Intelligent, intense expression — breed signature. Medium-length muzzle, semi-erect ears that tip forward. Alert, focused eyes.',
      ]);
    } elseif ($this->mb($b, ['australian shepherd', 'aussie'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Australian Shepherds grow into a well-muscled medium dog. Clear growth in height and body.',
        'adult_body_note' => 'Medium, muscular, agile, slightly longer than tall. Strong bone, well-developed chest.',
        'adult_face_note' => 'Balanced head, medium muzzle. Stunning eye colors (blue, amber, or brown). Semi-erect or rose ears.',
      ]);
    } elseif ($this->mb($b, ['whippet'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'sighthound',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Whippets grow into a slender, elegant sighthound. They get taller with a very distinctive arched back and deep chest.',
        'adult_body_note' => 'Slender, elegant sighthound — prominent arched back (roach back), very deep narrow chest, extremely tucked-up waist, long thin legs. Weight 11–20 kg.',
        'adult_face_note' => 'Long, fine, lean head. Rose-shaped small ears. Alert, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['basenji'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Basenjis grow into a lean, elegant, athletic small dog.',
        'adult_body_note' => 'Lean, elegant, well-muscled small dog. High on leg, with tightly curled tail. Forehead wrinkles characteristic.',
        'adult_face_note' => 'Wrinkled forehead, erect ears, almond-shaped eyes. Proud, alert expression.',
      ]);
    } elseif ($this->mb($b, ['bulldog', 'english bulldog'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Bulldogs get heavier and more wrinkled but not taller. Very heavy, wide, and low to the ground.',
        'adult_body_note' => 'Extremely wide, heavy, low-slung body. Massive chest, short bowed legs, wide shoulders. Weight 22–25 kg. The classic waddling walk develops.',
        'adult_face_note' => 'Massive wrinkled face with deep skin folds, very flat nose, pronounced underbite, huge jowls, rope of skin over nose. Distinctive pushed-in face becomes even more pronounced.',
      ]);
    } elseif ($this->mb($b, ['shar pei'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'size_note' => 'Shar Peis grow moderately. The signature wrinkles become tighter and more defined as they grow (puppies have MORE wrinkles proportionally).',
        'adult_body_note' => 'Medium, compact, square body. Weight 18–25 kg. Notably, wrinkles are less extreme in adults than puppies — skin tightens somewhat.',
        'adult_face_note' => 'Broad, full head with hippopotamus-like muzzle. Wrinkles concentrated on head and shoulders. Small sunken eyes, small folded ears. Blue-black tongue visible.',
      ]);
    } elseif ($this->mb($b, ['chow chow'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Chow Chows grow into a large, lion-maned, dignified dog.',
        'adult_body_note' => 'Large, powerful, compact, square body. Distinctive stilted gait. Lion-like mane of fur around neck. Weight 20–32 kg.',
        'adult_face_note' => 'Broad, massive head. Scowling, dignified expression. Blue-black tongue. Small, thick, rounded ears. Heavy lion-like mane of fur.',
      ]);
    } elseif ($this->mb($b, ['dalmatian'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Dalmatians grow into a large, lean, muscular, spotted dog. Significant height and length increase.',
        'adult_body_note' => 'Large, lean, muscular, elegant body. Long legs, deep chest, well-defined musculature. Weight 23–27 kg. Spots become fully developed.',
        'adult_face_note' => 'Long, strong, clean-cut head. Alert eyes, moderately large drop ears. Distinguished, athletic look.',
      ]);
    } elseif ($this->mb($b, ['standard poodle'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Standard Poodles grow into elegant, tall, curly-coated dogs. Significant height increase.',
        'adult_body_note' => 'Elegant, well-proportioned, athletic body. Squarely built, long neck, deep chest. Weight 20–32 kg. Dense curly coat.',
        'adult_face_note' => 'Long, straight, fine muzzle. Almond eyes, long flat ears. Refined, intelligent expression.',
      ]);

      // ── LARGE BREEDS — significant growth ───────────────────────────────────
    } elseif ($this->mb($b, ['labrador', 'lab'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Labradors grow dramatically from puppy to adult — much taller, heavier, and broader.',
        'adult_body_note' => 'Broad, powerful, strongly built body. Wide head, deep chest, strong neck, thick "otter" tail. Weight 25–36 kg. Stocky, powerful, athletic build.',
        'adult_face_note' => 'Broad, clean-cut head. Wide, powerful muzzle. Kind, intelligent eyes. Drop ears.',
      ]);
    } elseif ($this->mb($b, ['golden retriever'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'long_silky',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Golden Retrievers grow into large, beautiful, feathered dogs. Clear height and bulk increase.',
        'adult_body_note' => 'Large, well-balanced, powerful body with flowing golden coat. Deep chest, strong neck, feathering on legs, belly, and tail. Weight 25–34 kg.',
        'adult_face_note' => 'Broad, slightly arched skull. Gentle, intelligent expression. Drop ears, golden coat framing face. Friendly, calm expression.',
      ]);
    } elseif ($this->mb($b, ['german shepherd', 'alsatian'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'German Shepherds grow dramatically — much taller, broader chest, strong angular body.',
        'adult_body_note' => 'Strong, agile, muscular body. Slightly longer than tall, deep chest, characteristic sloping back. Bushy tail. Weight 22–40 kg.',
        'adult_face_note' => 'Strong, wedge-shaped head. Erect, pointed ears — breed signature, fully upright in adults. Alert, intelligent expression. Strong muzzle.',
      ]);
    } elseif ($this->mb($b, ['rottweiler'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Rottweilers grow into powerful, massive dogs. Very dramatic size increase.',
        'adult_body_note' => 'Massive, powerful, compact body. Heavy bone, deep broad chest, well-muscled. Weight 35–60 kg. Black and tan markings fully defined.',
        'adult_face_note' => 'Broad, powerful head. Strong wide muzzle, well-defined stop. Drop ears, calm confident expression.',
      ]);
    } elseif ($this->mb($b, ['doberman', 'dobermann'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Dobermans grow into sleek, powerful, elegant large dogs.',
        'adult_body_note' => 'Compact, muscular, elegant body. Square build, deep chest, well-arched neck. Sleek coat. Weight 32–45 kg.',
        'adult_face_note' => 'Long, wedge-shaped head. Erect ears (cropped or natural). Alert, intelligent, proud expression.',
      ]);
    } elseif ($this->mb($b, ['boxer'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Boxers grow into muscular, powerful dogs with a distinctive square head.',
        'adult_body_note' => 'Powerful, medium-large, square body. Well-muscled, deep chest, short back, strong legs. Weight 25–32 kg.',
        'adult_face_note' => 'Broad, blunt, squarish muzzle. Strong underjaw. Wrinkled forehead. Energetic, alert expression.',
      ]);
    } elseif ($this->mb($b, ['siberian husky', 'husky'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Huskies grow into medium-large dogs with a dense, lush double coat.',
        'adult_body_note' => 'Medium-large, athletic, well-muscled body. Thick double coat, bushy tail. Weight 16–27 kg. Built for endurance.',
        'adult_face_note' => 'Medium-sized, finely chiseled head. Almond eyes (blue, brown, or heterochromatic). Erect ears. Striking facial markings.',
      ]);
    } elseif ($this->mb($b, ['alaskan malamute', 'malamute'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Malamutes grow into very large, heavy, powerful sled dogs.',
        'adult_body_note' => 'Large, powerful, heavy-boned body. Deep chest, strong shoulders, heavy coat. Weight 34–43 kg. Compact but very powerful.',
        'adult_face_note' => 'Broad, powerful head. Brown almond eyes (never blue). Erect ears. Friendly, dignified expression.',
      ]);
    } elseif ($this->mb($b, ['weimaraner'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Weimaraners grow into sleek, elegant, gray-coated large dogs.',
        'adult_body_note' => 'Large, athletic, elegant body. Sleek silver-gray coat. Deep chest, strong back. Weight 23–32 kg.',
        'adult_face_note' => 'Moderately long head. Amber or blue-gray eyes. Long drop ears. Aristocratic, alert expression.',
      ]);
    } elseif ($this->mb($b, ['vizsla'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Vizslas grow into lean, elegant, golden-rust hunting dogs.',
        'adult_body_note' => 'Lean, elegant, well-muscled body. Golden-rust short coat. Deep chest. Weight 20–29 kg.',
        'adult_face_note' => 'Lean, aristocratic head. Warm golden-brown eyes. Broad drop ears. Distinguished, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['samoyed'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Samoyeds grow into medium-large dogs covered in a spectacular white double coat.',
        'adult_body_note' => 'Medium-large, well-proportioned body hidden under a thick white double coat that stands off the body. Weight 16–30 kg.',
        'adult_face_note' => 'Wedge-shaped head. Distinctive "Samoyed smile" — upturned mouth corners. Almond eyes. Erect ears. Surrounded by full white mane.',
      ]);
    } elseif ($this->mb($b, ['akita'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Akitas grow into very large, powerful, bear-like dogs.',
        'adult_body_note' => 'Large, powerful, heavy-boned body. Deep broad chest, thick neck, curled tail. Weight 32–59 kg.',
        'adult_face_note' => 'Broad, massive bear-like head. Small triangular erect ears. Dark, deep-set triangular eyes. Powerful muzzle. Dignified, alert expression.',
      ]);

      // ── GIANT BREEDS — dramatic growth ──────────────────────────────────────
    } elseif ($this->mb($b, ['great dane'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Great Danes are the tallest dog breed. Growth from puppy to adult is EXTREME. At 1 year: looks like a small horse — very tall, long-legged, deep-chested adolescent. At 3 years: one of the largest dogs you will ever see. Towering height, massive frame.',
        'adult_body_note' => 'Enormous, powerful, elegant body. Very long legs, deep massive chest, well-arched neck. Weight 50–90 kg. Stands 71–86 cm at shoulder — the tallest dog breed.',
        'adult_face_note' => 'Large, rectangular, expressive head. Strong muzzle, drop ears (or cropped erect). Gentle, noble expression despite massive size.',
      ]);
    } elseif ($this->mb($b, ['saint bernard'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Saint Bernards grow into enormous, massive dogs. One of the heaviest breeds.',
        'adult_body_note' => 'Enormous, very heavy, powerful body. Deep, wide chest, massive bone, thick coat. Weight 64–120 kg. Jowly, drooling.',
        'adult_face_note' => 'Massive broad head. Deep wrinkles, hanging jowls and lips (drooling). Kind, soulful eyes. Drop ears.',
      ]);
    } elseif ($this->mb($b, ['newfoundland', 'newfy'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Newfoundlands grow into massive, bear-like water dogs.',
        'adult_body_note' => 'Massive, heavy-boned, muscular body. Thick, water-resistant double coat. Weight 54–68 kg. Broad, powerful build.',
        'adult_face_note' => 'Broad, massive head. Soft, dark eyes. Small drop ears. Gentle, sweet expression despite enormous size.',
      ]);
    } elseif ($this->mb($b, ['irish wolfhound'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'sighthound',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'size_note' => 'Irish Wolfhounds are the tallest sighthound. Growth is DRAMATIC — they become one of the tallest dogs in the world. Very long, lean, powerful sighthound build.',
        'adult_body_note' => 'Enormous, long, lean, muscular sighthound. Very long legs, arched back, deep chest. Rough wiry coat. Weight 48–69 kg. Stands over 79 cm.',
        'adult_face_note' => 'Long, narrow head. Small folded ears. Gentle, calm expression. Rough wiry beard and eyebrows.',
      ]);
    } elseif ($this->mb($b, ['bernese mountain dog', 'bernese', 'berner'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Bernese Mountain Dogs grow into large, heavy, tri-colored mountain dogs.',
        'adult_body_note' => 'Large, heavy, sturdy body. Broad chest, strong legs. Long, thick, silky tricolor coat (black, white, rust). Weight 36–55 kg.',
        'adult_face_note' => 'Broad, flat skull. Tricolor face markings become well-defined. Drop ears, dark brown eyes. Calm, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['great pyrenees', 'pyrenean mountain'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Great Pyrenees grow into massive, majestic white mountain dogs.',
        'adult_body_note' => 'Massive, well-balanced body covered in thick, white, weather-resistant double coat. Weight 45–54+ kg.',
        'adult_face_note' => 'Large, wedge-shaped head. Dark brown eyes with black eye rims. V-shaped drop ears. Regal, calm expression.',
      ]);
    } elseif ($this->mb($b, ['mastiff', 'english mastiff'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'English Mastiffs are among the heaviest breeds. Growth is extreme — adult males can exceed 100 kg.',
        'adult_body_note' => 'Enormous, massive, heavy body. Very broad, deep chest. Weight 54–100+ kg. Jowly, wrinkled face.',
        'adult_face_note' => 'Broad, wrinkled, massive head. Deep muzzle, black mask. Drop ears. Dignified, calm expression. Heavy jowls.',
      ]);
    } elseif ($this->mb($b, ['leonberger'])) {
      $profile = array_merge($profile, [
        'size_category' => 'giant',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Leonbergers grow into giant, lion-maned, majestic dogs.',
        'adult_body_note' => 'Giant, muscular, well-proportioned body. Thick lion-like mane around neck. Weight 41–75 kg.',
        'adult_face_note' => 'Elongated, lion-like face. Black mask, medium-length muzzle. Drop ears. Gentle, friendly expression. Distinguished black facial mask.',
      ]);

      // ── WORKING / HERDING / MISCELLANEOUS ───────────────────────────────────
    } elseif ($this->mb($b, ['schnauzer', 'standard schnauzer'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Standard Schnauzers grow into square, wiry-coated medium dogs.',
        'adult_body_note' => 'Square build, strong, compact. Wiry salt-and-pepper or black coat. Distinctive beard and eyebrows. Weight 14–20 kg.',
        'adult_face_note' => 'Rectangular head. Prominent bushy eyebrows and thick beard — breed signature. V-shaped ears.',
      ]);
    } elseif ($this->mb($b, ['giant schnauzer'])) {
      $profile = array_merge($profile, [
        'size_category' => 'large',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Giant Schnauzers grow into powerful large dogs with the signature schnauzer look.',
        'adult_body_note' => 'Large, powerful, compact, square body. Dense wiry coat. Weight 25–48 kg.',
        'adult_face_note' => 'Powerful rectangular head. Very prominent bushy eyebrows and thick beard. Erect or drop ears. Alert, bold expression.',
      ]);
    } elseif ($this->mb($b, ['airedale', 'airedale terrier'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'size_note' => 'Airedales are the largest terrier. They grow into athletic, wiry-coated medium dogs.',
        'adult_body_note' => 'Well-balanced, athletic medium body. Dense, hard, wiry black and tan coat. Weight 18–29 kg.',
        'adult_face_note' => 'Long, flat skull. Small V-shaped drop ears. Wiry beard. Alert, intelligent expression.',
      ]);
    } elseif ($this->mb($b, ['jack russell', 'jack russel', 'parson russell'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'athletic',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'size_note' => 'Jack Russells stay small but very muscular and athletic. No significant height increase.',
        'adult_body_note' => 'Small, tough, compact, athletic body. Weight 5–8 kg. Lean muscle, active build.',
        'adult_face_note' => 'Flat skull, strong muzzle. V-shaped drop ears or button ears. Alert, intelligent, feisty expression.',
      ]);
    } elseif ($this->mb($b, ['shiba inu', 'shiba'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Shiba Inus grow into compact, fox-like small dogs. Moderate size increase.',
        'adult_body_note' => 'Compact, well-muscled, agile small body. Thick double coat. Curled tail. Weight 8–11 kg.',
        'adult_face_note' => 'Fox-like face — triangular head, small erect triangular ears, small squinting eyes. Alert, independent expression. Characteristic cream/white markings.',
      ]);
    } elseif ($this->mb($b, ['spitz'])) {
      $profile = array_merge($profile, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'double_coat',
        'grows_significantly' => false,
        'size_note' => 'Spitz breeds stay compact with a thick double coat and curled tail.',
        'adult_body_note' => 'Compact, well-proportioned body with thick stand-off coat and curled tail. Fox-like build.',
        'adult_face_note' => 'Fox-like pointed muzzle, erect ears, almond eyes. Alert, lively expression.',
      ]);
    } elseif ($this->mb($b, ['aspin', 'askal', 'asong', 'philippine', 'mixed', 'mongrel', 'mutt', 'crossbreed'])) {
      $profile = array_merge($profile, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Mixed breed dogs vary. Based on current puppy size, expect moderate to significant growth into a lean, athletic adult.',
        'adult_body_note' => 'Lean, athletic, well-proportioned medium body. Short, easy-care coat. Weight 10–25 kg depending on parentage.',
        'adult_face_note' => 'Defined adult muzzle and facial structure. Alert, intelligent expression. Features depend on breed mix.',
      ]);
    }

    // ── FALLBACK for unknown breeds ──────────────────────────────────────────
    if (empty($profile['adult_body_note'])) {
      $profile['size_note'] = "This breed grows into a well-proportioned adult dog. Expect moderate size increase from puppy.";
      $profile['adult_body_note'] = "Well-proportioned adult body, deeper chest, longer legs than puppy, healthy muscle development.";
      $profile['adult_face_note'] = "Defined adult muzzle, proportionate head, alert and healthy expression.";
    }

    // ── COAT override if not set by breed-specific block ────────────────────
    if ($profile['coat_type'] === 'short') {
      // keep short
    } elseif ($this->mb($b, ['poodle', 'bichon', 'pomeranian', 'chow', 'keeshond', 'american eskimo', 'samoyed', 'shih tzu', 'lhasa', 'havanese', 'maltese'])) {
      $profile['coat_type'] = 'curly/fluffy';
    } elseif ($this->mb($b, ['husky', 'malamute', 'akita', 'bernese', 'pyrenees', 'newfoundland', 'elkhound', 'shepherd'])) {
      $profile['coat_type'] = $profile['coat_type'] === 'short' ? 'double_coat' : $profile['coat_type'];
    } elseif ($this->mb($b, ['golden', 'cocker', 'setter', 'cavalier', 'yorkshire', 'yorkie', 'afghan'])) {
      $profile['coat_type'] = $profile['coat_type'] === 'short' ? 'long_silky' : $profile['coat_type'];
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
