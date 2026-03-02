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

  public $timeout    = 300;
  public $tries      = 3;
  public $backoff    = [15, 45, 90];

  protected $resultId;
  protected $breed;
  protected $imagePath;

  public function __construct($resultId, $breed, $imagePath)
  {
    $this->resultId  = $resultId;
    $this->breed     = $breed;
    $this->imagePath = $imagePath;
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  ENTRY POINT
  // ─────────────────────────────────────────────────────────────────────────

  public function handle()
  {
    $startTime = microtime(true);

    try {
      Log::info('🐕 AGE SIMULATION STARTED', [
        'result_id' => $this->resultId,
        'breed'     => $this->breed,
      ]);

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      $this->updateStatus($result, 'generating', []);

      // ── Prepare image ──────────────────────────────────────────────
      $imageData = $this->prepareHighQualityImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception('Failed to prepare image from path: ' . $this->imagePath);
      }

      // ── Build breed profile ────────────────────────────────────────
      $breedProfile = $this->getBreedProfile($this->breed);
      Log::info('📊 Breed Profile', ['breed' => $breedProfile['breed'], 'size' => $breedProfile['size_category']]);

      // ── Run Gemini in parallel for 1yr + 3yr ──────────────────────
      $simulations = $this->generateTransformations($imageData, $breedProfile);

      $savedPaths = ['1_years' => null, '3_years' => null];

      if (!empty($simulations['1_year'])) {
        $savedPaths['1_years'] = $this->saveImage($simulations['1_year'], '1_year', $this->resultId, $imageData);
        Log::info("✅ 1-year saved: {$savedPaths['1_years']}");
      } else {
        Log::warning('⚠️ 1-year simulation returned no image data');
      }

      if (!empty($simulations['3_years'])) {
        $savedPaths['3_years'] = $this->saveImage($simulations['3_years'], '3_years', $this->resultId, $imageData);
        Log::info("✅ 3-years saved: {$savedPaths['3_years']}");
      } else {
        Log::warning('⚠️ 3-year simulation returned no image data');
      }

      // Consider complete even if only one image succeeded
      $finalStatus = ($savedPaths['1_years'] || $savedPaths['3_years']) ? 'complete' : 'failed';
      $this->updateStatus($result, $finalStatus, $savedPaths, $breedProfile);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("🎉 SIMULATION {$finalStatus} in {$elapsed}s");
    } catch (\Exception $e) {
      Log::error('❌ SIMULATION FAILED', [
        'result_id' => $this->resultId,
        'error'     => $e->getMessage(),
        'line'      => $e->getLine(),
        'file'      => $e->getFile(),
      ]);
      if (isset($result) && $result) {
        $this->updateStatus($result, 'failed', [], [], $e->getMessage());
      } else {
        // Attempt to mark failed even if $result wasn't loaded
        $r = Results::find($this->resultId);
        if ($r) $this->updateStatus($r, 'failed', [], [], $e->getMessage());
      }
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  GENERATION: parallel Gemini calls with retry
  // ─────────────────────────────────────────────────────────────────────────

  private function generateTransformations(array $imageData, array $breedProfile): array
  {
    $client  = new Client(['timeout' => 150, 'connect_timeout' => 15]);
    $results = ['1_year' => null, '3_years' => null];

    $prompt1Year  = $this->buildAgingPrompt($breedProfile, 1);
    $prompt3Years = $this->buildAgingPrompt($breedProfile, 3);

    $maxAttempts = 3;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
      try {
        Log::info('🔄 Generation attempt ' . ($attempt + 1) . "/{$maxAttempts}");

        $promises = [];
        if (!$results['1_year'])  $promises['1_year']  = $this->createGenerationPromise($client, $prompt1Year,  $imageData);
        if (!$results['3_years']) $promises['3_years'] = $this->createGenerationPromise($client, $prompt3Years, $imageData);

        if (empty($promises)) break;

        $settled = Promise\Utils::settle($promises)->wait();

        foreach ($settled as $key => $result) {
          if ($result['state'] === 'fulfilled' && !empty($result['value'])) {
            $results[$key] = $result['value'];
            Log::info("✅ {$key} generation succeeded");
          } else {
            $reason = $result['reason'] ?? null;
            Log::warning("⚠️ {$key} failed on attempt " . ($attempt + 1), [
              'reason' => $reason ? $reason->getMessage() : 'no value returned',
            ]);
          }
        }

        if ($results['1_year'] && $results['3_years']) break;

        if ($attempt < $maxAttempts - 1) {
          $delay = (int) pow(2, $attempt + 1);
          Log::info("⏳ Backing off {$delay}s before retry");
          sleep($delay);
        }
      } catch (\Exception $e) {
        Log::error("Generation attempt {$attempt} threw exception: " . $e->getMessage());
        if ($attempt < $maxAttempts - 1) sleep(5 * ($attempt + 1));
      }
    }

    return $results;
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  GEMINI ASYNC PROMISE
  // ─────────────────────────────────────────────────────────────────────────

  private function createGenerationPromise(Client $client, string $prompt, array $imageData)
  {
    $apiKey    = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    $modelName = 'gemini-2.0-flash-exp-image-generation';
    $endpoint  = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $payload = [
      'contents' => [[
        'parts' => [
          ['text' => $prompt],
          ['inlineData' => [
            'mimeType' => $imageData['mimeType'],
            'data'     => $imageData['base64'],
          ]],
        ],
      ]],
      'generationConfig' => [
        'temperature'        => 0.2,   // lower = more faithful to source
        'topK'               => 32,
        'topP'               => 0.80,
        'maxOutputTokens'    => 8192,
        'responseModalities' => ['IMAGE', 'TEXT'],
      ],
      'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
      ],
    ];

    return $client->postAsync($endpoint, [
      'json'    => $payload,
      'headers' => ['Content-Type' => 'application/json'],
    ])->then(function ($response) {
      return $this->extractImage($response);
    });
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  EXTRACT IMAGE BYTES FROM API RESPONSE
  // ─────────────────────────────────────────────────────────────────────────

  private function extractImage($response): ?string
  {
    $body = $response->getBody()->getContents();
    $responseData = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Invalid JSON from Gemini API');
    }

    if (isset($responseData['error'])) {
      $errMsg = $responseData['error']['message'] ?? 'Unknown API error';
      throw new \Exception("Gemini API error: {$errMsg}");
    }

    if (!isset($responseData['candidates'][0])) {
      // Log full response for debugging (truncated)
      Log::error('No candidates in Gemini response', [
        'response_preview' => substr($body, 0, 500),
      ]);
      throw new \Exception('No candidates returned by Gemini API');
    }

    $candidate = $responseData['candidates'][0];

    // Check finish reason
    $finishReason = $candidate['finishReason'] ?? '';
    if (in_array($finishReason, ['SAFETY', 'RECITATION', 'OTHER'])) {
      throw new \Exception("Gemini blocked response: finishReason={$finishReason}");
    }

    $parts = $candidate['content']['parts'] ?? [];

    // Primary: inlineData image
    foreach ($parts as $part) {
      if (isset($part['inlineData']['data']) && strlen($part['inlineData']['data']) > 100) {
        $decoded = base64_decode($part['inlineData']['data'], true);
        if ($decoded && strlen($decoded) > 1000) {
          Log::info('✅ Image extracted from inlineData (' . round(strlen($decoded) / 1024, 1) . ' KB)');
          return $decoded;
        }
      }
    }

    // Fallback: base64 in text block
    foreach ($parts as $part) {
      if (isset($part['text'])) {
        $text    = preg_replace('/```[\w]*\n?/', '', $part['text']);
        $text    = trim($text);
        $decoded = base64_decode($text, true);
        if ($decoded && strlen($decoded) > 5000) {
          Log::info('✅ Image extracted from text block (' . round(strlen($decoded) / 1024, 1) . ' KB)');
          return $decoded;
        }
      }
    }

    throw new \Exception('No usable image data found in Gemini response parts');
  }

    // ─────────────────────────────────────────────────────────────────────────
    //  MASTER PROMPT BUILDER  — posture-locked, breed-accurate aging
    // ─────────────────────────────────────────────────────────────────────────

  /**
   * Build a precise aging prompt that:
   *  1. Locks all posture/pose/background elements first
   *  2. Detects current age of the dog in the photo
   *  3. Applies breed-accurate biological aging
   *  4. Enforces healthy, well-groomed result
   */
  private function buildAgingPrompt(array $profile, int $years): string
  {
    $breed       = $profile['breed'];
    $size        = $profile['size_category'];
    $coat        = $profile['coat_type'];
    $isBrachy    = $profile['brachycephalic'];
    $grows       = $profile['grows_significantly'];
    $bodyShape   = $profile['body_shape'] ?? 'standard';
    $sizeNote    = $profile['size_note'] ?? '';
    $adultBody   = $profile['adult_body_note'] ?? '';
    $adultFace   = $profile['adult_face_note'] ?? '';

    $lines = [];

    // ══════════════════════════════════════════════════════════════════
    // PREAMBLE — frame this as a PHOTO EDIT, not a generation
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '════════════════════════════════════════════';
    $lines[] = 'TASK TYPE: PHOTO EDITING — NOT NEW IMAGE GENERATION';
    $lines[] = '════════════════════════════════════════════';
    $lines[] = 'You are editing an existing photograph.';
    $lines[] = 'Your ONLY job is to make the dog in this photo look biologically older.';
    $lines[] = 'Everything else in the image stays EXACTLY as it is.';
    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 1 — POSTURE & SCENE LOCK (most important section)
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'STEP 1 ▸ POSTURE LOCK — READ THIS FIRST, MEMORIZE, NEVER DEVIATE';
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'Before doing ANYTHING, scan and memorize these elements from the input photo:';
    $lines[] = '';
    $lines[] = '  POSTURE (FROZEN — must be identical in output):';
    $lines[] = '    • Exact position of all four legs (where each paw touches the ground/surface)';
    $lines[] = '    • Body orientation (facing direction, angle of torso)';
    $lines[] = '    • Head angle and tilt (left/right/up/down — copy exactly)';
    $lines[] = '    • Ear position (up, down, folded, alert — copy exactly)';
    $lines[] = '    • Tail position (up, down, tucked, wagging angle — copy exactly)';
    $lines[] = '    • Sitting / standing / lying pose type (do NOT change this)';
    $lines[] = '';
    $lines[] = '  ENVIRONMENT (FROZEN — must be identical in output):';
    $lines[] = '    • Background (wall, floor, grass, room, outdoor — every detail)';
    $lines[] = '    • Camera angle and distance (do not zoom in or out)';
    $lines[] = '    • Lighting direction and quality (shadows stay where they are)';
    $lines[] = '    • Any objects, furniture, or people visible in frame';
    $lines[] = '    • Image crop/framing (do not reframe or resize the composition)';
    $lines[] = '';
    $lines[] = '  ❌ YOU ARE FORBIDDEN FROM:';
    $lines[] = '    • Moving or repositioning any leg, paw, or limb';
    $lines[] = '    • Changing the head angle or ear position';
    $lines[] = '    • Changing body orientation or rotation';
    $lines[] = '    • Changing the background in any way';
    $lines[] = '    • Adding or removing anything from the environment';
    $lines[] = '    • Changing camera angle, zoom, or crop';
    $lines[] = '    • Replacing the background with a studio/plain backdrop';
    $lines[] = '    • Making the dog appear to grow "taller" by stretching legs (growth = natural skeletal development, not scaling)';
    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 2 — CURRENT AGE ASSESSMENT
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'STEP 2 ▸ ASSESS CURRENT DOG AGE IN PHOTO';
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'Determine the approximate current age of the dog:';
    $lines[] = '';
    $lines[] = '  PUPPY indicators (if visible):';
    $lines[] = '    • Head appears oversized relative to body';
    $lines[] = '    • Legs are short and stubby relative to body length';
    $lines[] = '    • Face is round and soft, large innocent eyes, chubby cheeks';
    $lines[] = '    • Coat is thin, underdeveloped, or sparse';
    $lines[] = '    • Belly appears round and pudgy';
    $lines[] = '    • Paws look too large for the body';
    $lines[] = '';
    $lines[] = '  YOUNG ADULT indicators (if visible):';
    $lines[] = '    • Body proportions are mostly adult but may not be fully filled out';
    $lines[] = '    • Coat is developing but not at peak density';
    $lines[] = '    • Face is defined but slightly softer than fully mature';
    $lines[] = '';
    $lines[] = '  MATURE ADULT indicators (if visible):';
    $lines[] = '    • Fully proportionate adult body';
    $lines[] = '    • Dense, full coat';
    $lines[] = '    • Defined, strong facial structure';
    $lines[] = '    • No puppy softness remaining';
    $lines[] = '';
    $lines[] = '  ⚠️ DO NOT ASSUME THE DOG IS A PUPPY. Read the photo carefully.';
    $lines[] = '  If the dog already appears fully adult, apply only SUBTLE maturity changes.';
    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 3 — BREED IDENTIFICATION & GROWTH RULES
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = "STEP 3 ▸ BREED: {$breed} | SIZE: " . strtoupper($size);
    $lines[] = '────────────────────────────────────────────';
    $lines[] = $sizeNote;
    $lines[] = '';

    // Size-category growth guidance
    switch ($size) {
      case 'toy':
      case 'small':
        $lines[] = "SIZE RULE: {$breed} is a " . ($size === 'toy' ? 'TOY' : 'SMALL') . " breed.";
        $lines[] = '  • Do NOT dramatically increase body size or leg length.';
        $lines[] = '  • Growth is mostly in coat development, facial definition, and proportion refinement.';
        $lines[] = '  • Adult weight will be similar to puppy weight — no "big dog" transformation.';
        break;
      case 'medium':
        $lines[] = "SIZE RULE: {$breed} is a MEDIUM breed.";
        $lines[] = '  • Moderate skeletal growth if currently a puppy — taller legs, deeper chest.';
        $lines[] = '  • Do not over-scale. Changes should be natural and proportionate.';
        break;
      case 'large':
        $lines[] = "SIZE RULE: {$breed} is a LARGE breed.";
        $lines[] = '  • Significant skeletal development if currently a puppy.';
        $lines[] = '  • Broader chest, longer legs, more muscular build.';
        $lines[] = '  • Changes must look like natural canine development, not artificial scaling.';
        break;
      case 'giant':
        $lines[] = "SIZE RULE: {$breed} is a GIANT breed.";
        $lines[] = '  • Dramatic physical growth if currently a puppy — one of the most visually impactful changes.';
        $lines[] = '  • Massive chest, very long legs, heavy bone density.';
        $lines[] = '  • Still must look natural and proportionate — not a cartoon or exaggeration.';
        break;
    }

    if ($bodyShape === 'long_low') {
      $lines[] = '';
      $lines[] = "BODY SHAPE RULE: {$breed} has a LONG-AND-LOW body shape.";
      $lines[] = '  • This breed does NOT grow tall. Legs stay short.';
      $lines[] = '  • Body grows longer and heavier, but height from ground stays minimal.';
      $lines[] = '  • DO NOT increase leg length or make the dog taller.';
    } elseif ($bodyShape === 'sighthound') {
      $lines[] = '';
      $lines[] = "BODY SHAPE RULE: {$breed} has a SIGHTHOUND body shape.";
      $lines[] = '  • Stays lean and slender — do NOT bulk up or add heavy muscle.';
      $lines[] = '  • Prominent arched back, tucked waist, very long thin legs.';
    } elseif ($bodyShape === 'stocky') {
      $lines[] = '';
      $lines[] = "BODY SHAPE RULE: {$breed} has a STOCKY body shape.";
      $lines[] = '  • Grows wider, heavier, and more powerful — not necessarily taller.';
    }

    if ($isBrachy) {
      $lines[] = '';
      $lines[] = "BRACHYCEPHALIC RULE: {$breed} has a flat, pushed-in face.";
      $lines[] = '  • The flat face structure is a permanent breed characteristic — preserve it.';
      $lines[] = '  • Aging adds definition to wrinkles/folds, not muzzle elongation.';
    }

    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 4 — TARGET AGE TRANSFORMATION
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = "STEP 4 ▸ AGE TARGET: +{$years} YEAR(S) FROM CURRENT AGE IN PHOTO";
    $lines[] = '────────────────────────────────────────────';

    if ($years === 1) {
      $lines[] = 'TARGET: Show the dog as it would appear approximately 1 year older than it looks right now.';
      $lines[] = '';
      $lines[] = '  IF currently a puppy → transform to young adult:';
      $lines[] = '    • Remove puppy softness: reduce rounded baby face, shrink proportionally oversized head';
      $lines[] = '    • Define muzzle structure and adult facial bone structure';
      $lines[] = '    • Develop adult coat (thicker, fuller, more textured)';
      $lines[] = '    • Apply breed-appropriate skeletal growth (see size rules above)';
      $lines[] = '    • Lean adolescent build — not yet fully muscled';
      $lines[] = '    • Eyes become more defined and focused';
      $lines[] = '';
      $lines[] = '  IF currently a young adult → transition to mature adult:';
      $lines[] = '    • Slightly more filled-out chest and shoulders';
      $lines[] = '    • Coat reaches fuller adult density';
      $lines[] = '    • Face gains slightly more defined structure';
      $lines[] = '    • Subtle maturity in expression';
      $lines[] = '';
      $lines[] = '  IF currently a mature adult → subtle maturity:';
      $lines[] = '    • Minor changes only — slightly more settled expression';
      $lines[] = '    • Very subtle coat texture shifts if breed-appropriate';
      $lines[] = '    • No dramatic changes';
      $lines[] = '';
      $lines[] = '  ADULT BODY TARGET:';
      $lines[] = $adultBody;
      $lines[] = '';
      $lines[] = '  ADULT FACE TARGET:';
      $lines[] = $adultFace;
      $lines[] = '';
      $lines[] = '  COAT AT 1 YEAR: ' . $this->coatChange1Year($coat);
      $lines[] = '  GRAYING: NONE — this dog is young. No gray hairs anywhere.';
      $lines[] = '  EXPRESSION: Energetic, alert, curious. Full of life.';
    } else {
      // 3 years
      $lines[] = 'TARGET: Show the dog as it would appear approximately 3 years older than it looks right now.';
      $lines[] = '';
      $lines[] = '  IF currently a puppy → transform to fully mature adult:';
      $lines[] = '    • Complete adult body — no puppy features remain';
      $lines[] = '    • Full breed-characteristic skeletal structure';
      $lines[] = '    • Peak muscle development appropriate to breed';
      $lines[] = '    • Complete adult coat at its best condition';
      $lines[] = '    • Strong, defined adult face';
      $lines[] = '';
      $lines[] = '  IF currently a young adult → fully mature peak adult:';
      $lines[] = '    • Fully filled-out chest and shoulders';
      $lines[] = '    • Maximum adult muscle definition for this breed';
      $lines[] = '    • Full coat density and texture';
      $lines[] = '    • Confident, settled mature expression';
      $lines[] = '';
      $lines[] = '  IF currently a mature adult → senior-approaching adult:';
      $lines[] = '    • Slightly heavier or more settled body';
      $lines[] = '    • Natural muzzle graying (breed-appropriate)';
      $lines[] = '    • More dignified, calm expression';
      $lines[] = '';
      $lines[] = '  ADULT BODY TARGET:';
      $lines[] = $adultBody;
      $lines[] = '';
      $lines[] = '  ADULT FACE TARGET:';
      $lines[] = $adultFace;
      $lines[] = '';
      $lines[] = '  COAT AT 3 YEARS: ' . $this->coatChange3Years($coat);
      $lines[] = '  GRAYING: ' . $this->grayChange3Years($profile);
      $lines[] = '  EXPRESSION: Calm, confident, settled, mature dignity.';
    }

    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 5 — BIOLOGICAL REALISM RULES
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'STEP 5 ▸ BIOLOGICAL REALISM REQUIREMENTS';
    $lines[] = '────────────────────────────────────────────';
    $lines[] = '  • Growth must follow real canine development curves — not artificial scaling';
    $lines[] = '  • Fur direction must remain consistent with the original image';
    $lines[] = '  • Preserve the lighting interaction with the fur (same light source, same shadow placement)';
    $lines[] = '  • Natural weight distribution must be respected given the locked posture';
    $lines[] = '  • Skin tension and natural muscle distribution must look real and proportionate';
    $lines[] = '  • Colors must be preserved — coat color, eye color, nose color, markings';
    $lines[] = '  • No artificial effects, filters, or stylization — this must look like a real photograph';
    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 6 — HEALTH MANDATE
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'STEP 6 ▸ HEALTH MANDATE — NON-NEGOTIABLE';
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'The dog MUST look:';
    $lines[] = '  ✓ Healthy and well-fed (appropriate weight for breed)';
    $lines[] = '  ✓ Clean and well-groomed';
    $lines[] = '  ✓ Happy or calm — bright eyes, not sad or suffering';
    $lines[] = '  ✓ This is a thriving, loved pet dog';
    $lines[] = '';
    $lines[] = 'The dog MUST NOT look:';
    $lines[] = '  ✗ Sick, underweight, or lethargic';
    $lines[] = '  ✗ Matted, dirty, or neglected';
    $lines[] = '  ✗ Sad, frightened, or in pain';
    $lines[] = '';

    // ══════════════════════════════════════════════════════════════════
    // STEP 7 — FINAL VERIFICATION CHECKLIST
    // ══════════════════════════════════════════════════════════════════
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'STEP 7 ▸ FINAL SELF-VERIFICATION BEFORE OUTPUT';
    $lines[] = '────────────────────────────────────────────';
    $lines[] = 'Before outputting the image, verify EVERY item:';
    $lines[] = '';
    $lines[] = '  POSTURE CHECK:';
    $lines[] = '    □ All four leg/paw positions identical to input? YES';
    $lines[] = '    □ Head angle and tilt identical to input? YES';
    $lines[] = '    □ Body orientation identical to input? YES';
    $lines[] = '    □ Ear and tail position identical to input? YES';
    $lines[] = '';
    $lines[] = '  ENVIRONMENT CHECK:';
    $lines[] = '    □ Background identical to input (same room/outdoors/surface)? YES';
    $lines[] = '    □ Camera angle and zoom identical? YES';
    $lines[] = '    □ Lighting and shadows consistent? YES';
    $lines[] = '    □ Nothing added or removed from scene? YES';
    $lines[] = '';
    $lines[] = '  AGING CHECK:';
    $lines[] = "    □ Dog looks clearly +{$years} year(s) older than input? YES";
    $lines[] = '    □ Changes are breed-accurate and biologically realistic? YES';
    $lines[] = '    □ Dog looks healthy and well-groomed? YES';
    $lines[] = '    □ Coat color and markings preserved? YES';
    $lines[] = '';
    $lines[] = 'If ANY item is NO — regenerate before outputting.';
    $lines[] = '';
    $lines[] = '════════════════════════════════════════════';
    $lines[] = 'OUTPUT: The edited photograph — same dog, same pose, same scene, biologically older.';
    $lines[] = '════════════════════════════════════════════';

    return implode("\n", $lines);
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  COAT & GRAY HELPERS
  // ─────────────────────────────────────────────────────────────────────────

  private function coatChange1Year(string $coat): string
  {
    return match ($coat) {
      'curly/fluffy'  => 'Fuller, more settled adult coat — puppy fuzz replaced by characteristic adult plush double coat. Clean, well-groomed, fluffy.',
      'double_coat'   => 'Adult double coat developing — thicker undercoat, denser guard hairs. Healthy, lush coat.',
      'long_silky'    => 'Coat reaching adult length — silky, flowing, well-groomed. Slightly longer than puppy coat.',
      'wire'          => 'Wiry adult texture defined — characteristic rough, dense texture of the breed. Tidy and well-kept.',
      'short'         => 'Short adult coat fully developed — smooth, glossy, healthy sheen. Dense and close-lying.',
      default         => 'Adult coat fully developed — healthy, clean, well-groomed.',
    };
  }

  private function coatChange3Years(string $coat): string
  {
    return match ($coat) {
      'curly/fluffy'  => 'Coat at full adult glory — dense, well-formed, plush at peak condition. Clean and well-groomed.',
      'double_coat'   => 'Dense, full double coat at peak — rich in color and texture, thick undercoat and lustrous guard hairs.',
      'long_silky'    => 'Coat at full adult length — flowing, silky, well-maintained. Beautiful and healthy.',
      'wire'          => 'Wiry coat fully expressed — characteristic dense, rough texture of mature breed. Tidy.',
      'short'         => 'Short coat glossy and healthy — fits the mature muscular body well. Dense and sleek.',
      default         => 'Mature adult coat — full, healthy, clean, well-maintained.',
    };
  }

  private function grayChange3Years(array $profile): string
  {
    return match ($profile['gray_pattern'] ?? 'moderate') {
      'none'      => 'No gray hairs — this breed does not gray noticeably at 3 years. Coat color remains vivid.',
      'minimal'   => 'Possibly a very few light hairs on the muzzle tip — subtle and barely noticeable. Color otherwise unchanged.',
      'moderate'  => 'Light dusting of gray/silver hairs on the muzzle tip and around the eyes — natural and distinguished. Base coat color fully preserved.',
      'prominent' => 'Noticeable silver/gray hairs on muzzle, chin, and around the eyes — natural, handsome sign of maturity. Underlying coat color preserved.',
      default     => 'Subtle, natural graying only on muzzle tip — breed-appropriate minimal graying.',
    };
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  BREED PROFILE DATABASE
  // ─────────────────────────────────────────────────────────────────────────

  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    $profile = [
      'breed'               => $breed,
      'size_category'       => 'medium',
      'body_shape'          => 'standard',
      'coat_type'           => 'short',
      'gray_pattern'        => 'moderate',
      'brachycephalic'      => false,
      'grows_significantly' => false,
      'adult_body_note'     => 'Well-proportioned adult body, deeper chest, longer legs than puppy, healthy muscle development.',
      'adult_face_note'     => 'Defined adult muzzle, proportionate head, alert and healthy expression.',
      'size_note'           => 'This breed grows into a well-proportioned adult dog. Expect moderate size increase from puppy.',
    ];

    // ── TOY ──────────────────────────────────────────────────────────────
    if ($this->mb($b, ['chihuahua'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note'           => 'Chihuahuas are tiny. Adult size is nearly identical to puppy — no dramatic size change.',
        'adult_body_note'     => 'Compact, fine-boned tiny body. Short delicate legs. Weight 2–3 kg. Same tiny frame as puppy but proportions become slightly more defined.',
        'adult_face_note'     => 'Large rounded apple-dome head (permanent breed trait). Large erect ears. Large expressive eyes. Face becomes slightly more refined but retains large-eyed round-headed look.',
      ]);
    } elseif ($this->mb($b, ['pomeranian'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Pomeranians stay very small. No height increase.',
        'adult_body_note'     => 'Tiny compact body hidden beneath a thick double coat. Weight 2–3.5 kg.',
        'adult_face_note'     => 'Distinctive fox-like face with sharp pointed muzzle, alert eyes, small erect ears. Thick neck ruff develops.',
      ]);
    } elseif ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Yorkshire Terriers stay tiny — 2–3 kg. No size increase.',
        'adult_body_note'     => 'Very small, fine-boned, compact body. Long silky coat reaches the floor in adults.',
        'adult_face_note'     => 'Small flat face with medium-length muzzle. V-shaped erect ears. Classic steel blue and tan adult coloring develops.',
      ]);
    } elseif ($this->mb($b, ['maltese'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note'           => 'Maltese stay tiny. Pure white long silky coat develops fully.',
        'adult_body_note'     => 'Tiny compact body completely covered in long, flowing pure white silky coat.',
        'adult_face_note'     => 'Gentle, sweet face. Medium muzzle, dark eyes, drop ears hidden under long white hair.',
      ]);
    } elseif ($this->mb($b, ['papillon'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note'           => 'Papillons stay small — 3–5 kg. Butterfly ears fully develop.',
        'adult_body_note'     => 'Fine-boned, elegant tiny body with flowing coat.',
        'adult_face_note'     => 'Large butterfly-shaped erect ears fringed with long hair — breed signature. Fine-boned elegant face.',
      ]);
    } elseif ($this->mb($b, ['miniature pinscher', 'min pin'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Min Pins stay small but develop a lean, muscular athletic build.',
        'adult_body_note'     => 'Compact, muscular, athletic tiny body. High-stepping hackney gait. Very lean with defined muscle.',
        'adult_face_note'     => 'Strong, narrow head. Erect ears. Alert, fearless expression.',
      ]);
    } elseif ($this->mb($b, ['italian greyhound'])) {
      return array_merge($profile, [
        'size_category'       => 'toy',
        'body_shape' => 'sighthound',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note'           => 'Italian Greyhounds stay slender and small. Sighthound shape becomes more defined.',
        'adult_body_note'     => 'Extremely slender, elegant, arched back, deep narrow chest, tucked-up abdomen. Long thin legs.',
        'adult_face_note'     => 'Long, narrow, fine head. Large doe eyes. Folded-back ears when relaxed.',
      ]);

      // ── SMALL ────────────────────────────────────────────────────────────
    } elseif ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'double_coat',
        'grows_significantly' => false,
        'gray_pattern' => 'moderate',
        'size_note'           => 'Corgis are a long-and-low breed. They DO NOT grow tall. Adult Corgis stay close to the ground with short legs.',
        'adult_body_note'     => 'Long body, very short legs (dwarf breed), deep chest, muscular hindquarters. Body length much greater than height. Weight 10–14 kg.',
        'adult_face_note'     => 'Fox-like face with large upright pointed ears (fully erect). Strong muzzle. Alert, intelligent expression.',
      ]);
    } elseif ($this->mb($b, ['dachshund', 'doxie', 'sausage', 'wiener'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note'           => 'Dachshunds are extremely long and very low. They DO NOT grow tall — legs stay very short.',
        'adult_body_note'     => 'Extremely elongated body, very short stubby legs, deep keel chest. Iconic sausage dog silhouette.',
        'adult_face_note'     => 'Long tapered muzzle. Long floppy ears. Strong jaw. Confident, alert expression.',
      ]);
    } elseif ($this->mb($b, ['beagle'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'gray_pattern' => 'moderate',
        'size_note'           => 'Beagles grow moderately — some height and width but stays compact.',
        'adult_body_note'     => 'Solid, muscular, compact body. Deep chest, strong back, sturdy legs. Weight 9–11 kg.',
        'adult_face_note'     => 'Classic hound face — long square muzzle, long floppy ears, large brown eyes, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['french bulldog'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'French Bulldogs stay small and stocky. They get heavier and more muscular but not taller.',
        'adult_body_note'     => 'Heavy, muscular, compact. Very wide shoulders and chest, narrow hindquarters. Weight 9–13 kg.',
        'adult_face_note'     => 'Flat face with deep wrinkles/folds. Massive square head. Bat-like erect ears — breed signature. Very short pushed-in nose.',
      ]);
    } elseif ($this->mb($b, ['pug'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Pugs stay small and round. They may get heavier but not taller.',
        'adult_body_note'     => 'Cobby, round, compact. Heavy for size. Deep chest, wide body. Weight 6–9 kg.',
        'adult_face_note'     => 'Massive round head, very flat face, deep wrinkles, bulging eyes, very short nose. Curly tail.',
      ]);
    } elseif ($this->mb($b, ['boston terrier'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'size_note'           => 'Boston Terriers stay small and square. Weight 5–11 kg.',
        'adult_body_note'     => 'Square, compact, muscular. Deep chest, short back.',
        'adult_face_note'     => 'Square flat face, large round eyes, erect ears. Tuxedo pattern well-defined.',
      ]);
    } elseif ($this->mb($b, ['shih tzu'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'size_note'           => 'Shih Tzus stay small and compact. Long flowing coat develops fully.',
        'adult_body_note'     => 'Compact, sturdy, slightly longer than tall. Weight 4–8 kg. Covered in long flowing double coat.',
        'adult_face_note'     => 'Sweet flat face with long flowing facial hair. Large dark eyes, broad muzzle. Topknot of hair.',
      ]);
    } elseif ($this->mb($b, ['bichon frise', 'bichon'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note'           => 'Bichon Frises stay small with a puffy white rounded coat.',
        'adult_body_note'     => 'Small compact body covered in dense, curly, white coat trimmed into a rounded shape.',
        'adult_face_note'     => 'Round powder-puff face. Dark round eyes, black nose, surrounded by fluffy white coat.',
      ]);
    } elseif ($this->mb($b, ['cavalier king charles', 'cavalier'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note'           => 'Cavaliers stay small and elegant. Weight 5–8 kg.',
        'adult_body_note'     => 'Small, elegant, graceful body with flowing silky coat on ears, chest, legs, and tail.',
        'adult_face_note'     => 'Gentle, mournful large dark eyes. Long floppy silky ears. Sweet melting expression.',
      ]);
    } elseif ($this->mb($b, ['cocker spaniel', 'english cocker', 'american cocker'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note'           => 'Cocker Spaniels grow moderately. Heavy feathering develops on ears, legs, and belly.',
        'adult_body_note'     => 'Compact, sturdy with well-developed chest. Heavy silky feathering on ears, chest, legs.',
        'adult_face_note'     => 'Broad, well-rounded head. Long, low-set, heavily feathered ears. Large, round, expressive eyes.',
      ]);
    } elseif ($this->mb($b, ['shiba inu', 'shiba'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Shiba Inus grow into compact, fox-like small dogs. Moderate size increase.',
        'adult_body_note'     => 'Compact, well-muscled, agile. Thick double coat. Curled tail. Weight 8–11 kg.',
        'adult_face_note'     => 'Fox-like face — triangular head, small erect triangular ears, small squinting eyes. Cream/white markings.',
      ]);
    } elseif ($this->mb($b, ['miniature schnauzer'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Miniature Schnauzers stay small and square. Distinctive beard and eyebrows develop.',
        'adult_body_note'     => 'Square build — height equals length. Compact, muscular, wiry-coated.',
        'adult_face_note'     => 'Rectangular strong head. Signature long bushy eyebrows and thick beard. V-shaped ears.',
      ]);
    } elseif ($this->mb($b, ['jack russell', 'jack russel', 'parson russell'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'athletic',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'size_note'           => 'Jack Russells stay small but very muscular and athletic.',
        'adult_body_note'     => 'Small, tough, compact, athletic. Weight 5–8 kg. Lean muscle.',
        'adult_face_note'     => 'Flat skull, strong muzzle. V-shaped drop ears or button ears. Alert, feisty expression.',
      ]);
    } elseif ($this->mb($b, ['scottish terrier', 'scotty', 'westie', 'west highland'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'size_note'           => 'Scottish/West Highland Terriers stay small and low-slung. Wiry coat becomes very defined.',
        'adult_body_note'     => 'Compact, low-slung, very sturdy. Short legs, barrel chest, thick wiry coat.',
        'adult_face_note'     => 'Wedge-shaped head with beard and prominent eyebrows. Erect pointed ears. Determined expression.',
      ]);
    } elseif ($this->mb($b, ['havanese'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note'           => 'Havanese stay small with a long, silky, flowing coat.',
        'adult_body_note'     => 'Small, sturdy body covered in long, silky, slightly wavy coat.',
        'adult_face_note'     => 'Broad, rounded head, large almond eyes, drop ears with long silky hair.',
      ]);
    } elseif ($this->mb($b, ['lhasa apso'])) {
      return array_merge($profile, [
        'size_category'       => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note'           => 'Lhasa Apsos stay small. Long heavy coat reaching the floor develops fully.',
        'adult_body_note'     => 'Longer than tall, sturdy body beneath a heavy, long, flowing coat.',
        'adult_face_note'     => 'Heavy floor-length coat falls over the face. Strong muzzle, dark eyes. Dignified expression.',
      ]);

      // ── MEDIUM ───────────────────────────────────────────────────────────
    } elseif ($this->mb($b, ['border collie'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Border Collies grow into a lean, athletic medium-sized dog. Noticeably taller and longer than puppy.',
        'adult_body_note'     => 'Athletic, lithe, graceful. Lean muscle, not bulky. Well-proportioned agile frame. Weight 14–20 kg.',
        'adult_face_note'     => 'Intelligent, intense expression — breed signature. Medium muzzle, semi-erect forward-tipping ears. Alert, focused eyes.',
      ]);
    } elseif ($this->mb($b, ['australian shepherd', 'aussie'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Australian Shepherds grow into a well-muscled medium dog.',
        'adult_body_note'     => 'Medium, muscular, agile, slightly longer than tall. Strong bone, well-developed chest.',
        'adult_face_note'     => 'Balanced head, medium muzzle. Striking eye colors (blue, amber, or brown). Semi-erect or rose ears.',
      ]);
    } elseif ($this->mb($b, ['bulldog', 'english bulldog'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Bulldogs get heavier and more wrinkled but not taller. Wide and low to ground.',
        'adult_body_note'     => 'Extremely wide, heavy, low-slung. Massive chest, short bowed legs, wide shoulders. Weight 22–25 kg.',
        'adult_face_note'     => 'Massive wrinkled face with deep skin folds, flat nose, pronounced underbite, huge jowls.',
      ]);
    } elseif ($this->mb($b, ['chow chow'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Chow Chows grow into a large, lion-maned, dignified dog.',
        'adult_body_note'     => 'Large, powerful, compact, square body. Distinctive stilted gait. Lion mane of fur around neck. Weight 20–32 kg.',
        'adult_face_note'     => 'Broad, massive head. Scowling dignified expression. Blue-black tongue. Heavy lion-like mane.',
      ]);
    } elseif ($this->mb($b, ['shar pei'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'size_note'           => 'Shar Peis grow moderately. Wrinkles become tighter and more defined as they grow.',
        'adult_body_note'     => 'Medium, compact, square. Weight 18–25 kg. Wrinkles concentrated on head and shoulders.',
        'adult_face_note'     => 'Broad hippopotamus-like muzzle. Small sunken eyes, small folded ears. Blue-black tongue.',
      ]);
    } elseif ($this->mb($b, ['whippet'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'sighthound',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note'           => 'Whippets grow into a slender, elegant sighthound.',
        'adult_body_note'     => 'Slender sighthound — prominent arched back, very deep narrow chest, extremely tucked waist, long thin legs. Weight 11–20 kg.',
        'adult_face_note'     => 'Long, fine, lean head. Rose-shaped small ears. Alert, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['dalmatian'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note'           => 'Dalmatians grow into a large, lean, muscular, spotted dog.',
        'adult_body_note'     => 'Large, lean, muscular, elegant. Long legs, deep chest. Weight 23–27 kg. Spots fully developed.',
        'adult_face_note'     => 'Long, strong, clean-cut head. Alert eyes, moderately large drop ears. Athletic, distinguished look.',
      ]);
    } elseif ($this->mb($b, ['standard poodle'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note'           => 'Standard Poodles grow into elegant, tall, curly-coated dogs.',
        'adult_body_note'     => 'Elegant, well-proportioned, athletic. Squarely built, long neck, deep chest. Weight 20–32 kg.',
        'adult_face_note'     => 'Long, straight, fine muzzle. Almond eyes, long flat ears. Refined, intelligent expression.',
      ]);
    } elseif ($this->mb($b, ['schnauzer', 'standard schnauzer'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Standard Schnauzers grow into square, wiry-coated medium dogs.',
        'adult_body_note'     => 'Square build, strong, compact. Wiry coat. Distinctive beard and eyebrows. Weight 14–20 kg.',
        'adult_face_note'     => 'Rectangular head. Prominent bushy eyebrows and thick beard — breed signature.',
      ]);
    } elseif ($this->mb($b, ['airedale'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'size_note'           => 'Airedales are the largest terrier — grow into athletic, wiry-coated medium dogs.',
        'adult_body_note'     => 'Well-balanced, athletic medium body. Dense, hard, wiry black and tan coat. Weight 18–29 kg.',
        'adult_face_note'     => 'Long, flat skull. Small V-shaped drop ears. Wiry beard. Alert, intelligent expression.',
      ]);

      // ── LARGE ────────────────────────────────────────────────────────────
    } elseif ($this->mb($b, ['labrador', 'lab'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note'           => 'Labradors grow dramatically from puppy to adult — much taller, heavier, and broader.',
        'adult_body_note'     => 'Broad, powerful, strongly built. Wide head, deep chest, strong neck, thick otter tail. Weight 25–36 kg.',
        'adult_face_note'     => 'Broad, clean-cut head. Wide, powerful muzzle. Kind, intelligent eyes. Drop ears.',
      ]);
    } elseif ($this->mb($b, ['golden retriever'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'long_silky',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note'           => 'Golden Retrievers grow into large, beautiful, feathered dogs. Clear height and bulk increase.',
        'adult_body_note'     => 'Large, well-balanced, powerful with flowing golden coat. Deep chest, strong neck, feathering on legs, belly, tail. Weight 25–34 kg.',
        'adult_face_note'     => 'Broad, slightly arched skull. Gentle, intelligent expression. Drop ears, golden coat framing face.',
      ]);
    } elseif ($this->mb($b, ['german shepherd', 'alsatian'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note'           => 'German Shepherds grow dramatically — much taller, broader chest, strong angular body.',
        'adult_body_note'     => 'Strong, agile, muscular. Slightly longer than tall, deep chest, characteristic sloping back. Bushy tail. Weight 22–40 kg.',
        'adult_face_note'     => 'Strong wedge-shaped head. Fully erect pointed ears — breed signature. Alert, intelligent expression. Strong muzzle.',
      ]);
    } elseif ($this->mb($b, ['rottweiler'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Rottweilers grow into powerful, massive dogs. Very dramatic size increase.',
        'adult_body_note'     => 'Massive, powerful, compact. Heavy bone, deep broad chest, well-muscled. Weight 35–60 kg. Black and tan markings fully defined.',
        'adult_face_note'     => 'Broad, powerful head. Strong wide muzzle. Drop ears. Calm, confident expression.',
      ]);
    } elseif ($this->mb($b, ['doberman', 'dobermann'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Dobermans grow into sleek, powerful, elegant large dogs.',
        'adult_body_note'     => 'Compact, muscular, elegant. Square build, deep chest, well-arched neck. Weight 32–45 kg.',
        'adult_face_note'     => 'Long, wedge-shaped head. Erect ears. Alert, intelligent, proud expression.',
      ]);
    } elseif ($this->mb($b, ['boxer'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'moderate',
        'size_note'           => 'Boxers grow into muscular, powerful dogs with a distinctive square head.',
        'adult_body_note'     => 'Powerful, medium-large, square body. Well-muscled, deep chest, short back. Weight 25–32 kg.',
        'adult_face_note'     => 'Broad, blunt, squarish muzzle. Strong underjaw. Wrinkled forehead. Energetic, alert expression.',
      ]);
    } elseif ($this->mb($b, ['siberian husky', 'husky'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note'           => 'Huskies grow into medium-large dogs with a dense, lush double coat.',
        'adult_body_note'     => 'Medium-large, athletic, well-muscled. Thick double coat, bushy tail. Weight 16–27 kg.',
        'adult_face_note'     => 'Finely chiseled head. Almond eyes (blue, brown, or heterochromatic). Erect ears. Striking facial markings.',
      ]);
    } elseif ($this->mb($b, ['alaskan malamute', 'malamute'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note'           => 'Malamutes grow into very large, heavy, powerful sled dogs.',
        'adult_body_note'     => 'Large, powerful, heavy-boned. Deep chest, strong shoulders, heavy coat. Weight 34–43 kg.',
        'adult_face_note'     => 'Broad, powerful head. Brown almond eyes (never blue). Erect ears. Friendly, dignified expression.',
      ]);
    } elseif ($this->mb($b, ['weimaraner'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Weimaraners grow into sleek, elegant, gray-coated large dogs.',
        'adult_body_note'     => 'Large, athletic, elegant. Sleek silver-gray coat. Deep chest. Weight 23–32 kg.',
        'adult_face_note'     => 'Moderately long head. Amber or blue-gray eyes. Long drop ears. Aristocratic expression.',
      ]);
    } elseif ($this->mb($b, ['vizsla'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note'           => 'Vizslas grow into lean, elegant, golden-rust hunting dogs.',
        'adult_body_note'     => 'Lean, elegant, well-muscled. Golden-rust short coat. Deep chest. Weight 20–29 kg.',
        'adult_face_note'     => 'Lean, aristocratic head. Warm golden-brown eyes. Broad drop ears. Distinguished, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['akita'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note'           => 'Akitas grow into very large, powerful, bear-like dogs.',
        'adult_body_note'     => 'Large, powerful, heavy-boned. Deep broad chest, thick neck, curled tail. Weight 32–59 kg.',
        'adult_face_note'     => 'Broad, massive bear-like head. Small triangular erect ears. Deep-set triangular eyes. Powerful muzzle. Dignified expression.',
      ]);
    } elseif ($this->mb($b, ['samoyed'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note'           => 'Samoyeds grow into medium-large dogs covered in a spectacular white double coat.',
        'adult_body_note'     => 'Medium-large, well-proportioned under a thick white double coat. Weight 16–30 kg.',
        'adult_face_note'     => 'Wedge-shaped head. Distinctive "Samoyed smile" — upturned mouth corners. Erect ears. Full white mane.',
      ]);
    } elseif ($this->mb($b, ['giant schnauzer'])) {
      return array_merge($profile, [
        'size_category'       => 'large',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note'           => 'Giant Schnauzers grow into powerful large dogs.',
        'adult_body_note'     => 'Large, powerful, compact, square. Dense wiry coat. Weight 25–48 kg.',
        'adult_face_note'     => 'Powerful rectangular head. Very prominent bushy eyebrows and thick beard. Bold expression.',
      ]);

      // ── GIANT ────────────────────────────────────────────────────────────
    } elseif ($this->mb($b, ['great dane'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note'           => 'Great Danes are the tallest dog breed. Growth from puppy to adult is EXTREME. At 1 year: very tall, long-legged adolescent. At 3 years: one of the largest dogs on Earth.',
        'adult_body_note'     => 'Enormous, powerful, elegant. Very long legs, deep massive chest, well-arched neck. Weight 50–90 kg. Stands 71–86 cm at shoulder.',
        'adult_face_note'     => 'Large, rectangular, expressive head. Strong muzzle, drop or cropped erect ears. Gentle, noble expression despite massive size.',
      ]);
    } elseif ($this->mb($b, ['saint bernard'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note'           => 'Saint Bernards grow into enormous, massive dogs. One of the heaviest breeds.',
        'adult_body_note'     => 'Enormous, very heavy, powerful. Deep wide chest, massive bone, thick coat. Weight 64–120 kg. Jowly.',
        'adult_face_note'     => 'Massive broad head. Deep wrinkles, hanging jowls and lips. Kind, soulful eyes. Drop ears.',
      ]);
    } elseif ($this->mb($b, ['newfoundland', 'newfy'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note'           => 'Newfoundlands grow into massive, bear-like water dogs.',
        'adult_body_note'     => 'Massive, heavy-boned, muscular. Thick water-resistant double coat. Weight 54–68 kg.',
        'adult_face_note'     => 'Broad, massive head. Soft, dark eyes. Small drop ears. Gentle, sweet expression.',
      ]);
    } elseif ($this->mb($b, ['irish wolfhound'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'sighthound',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'size_note'           => 'Irish Wolfhounds grow into one of the tallest dogs in the world. Very dramatic growth.',
        'adult_body_note'     => 'Enormous, long, lean, muscular sighthound. Very long legs, arched back, deep chest. Rough wiry coat. Weight 48–69 kg.',
        'adult_face_note'     => 'Long, narrow head. Small folded ears. Gentle, calm expression. Rough wiry beard.',
      ]);
    } elseif ($this->mb($b, ['bernese mountain dog', 'bernese', 'berner'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note'           => 'Bernese Mountain Dogs grow into large, heavy, tri-colored mountain dogs.',
        'adult_body_note'     => 'Large, heavy, sturdy. Broad chest, strong legs. Long thick silky tricolor coat (black, white, rust). Weight 36–55 kg.',
        'adult_face_note'     => 'Broad, flat skull. Tricolor face markings well-defined. Drop ears, dark brown eyes. Calm, gentle expression.',
      ]);
    } elseif ($this->mb($b, ['great pyrenees', 'pyrenean mountain'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note'           => 'Great Pyrenees grow into massive, majestic white mountain dogs.',
        'adult_body_note'     => 'Massive, well-balanced covered in thick white weather-resistant double coat. Weight 45–54+ kg.',
        'adult_face_note'     => 'Large, wedge-shaped head. Dark brown eyes with black eye rims. V-shaped drop ears. Regal, calm expression.',
      ]);
    } elseif ($this->mb($b, ['mastiff', 'english mastiff'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'moderate',
        'size_note'           => 'English Mastiffs are among the heaviest breeds. Growth is extreme — adult males can exceed 100 kg.',
        'adult_body_note'     => 'Enormous, massive, heavy. Very broad deep chest. Weight 54–100+ kg. Jowly, wrinkled.',
        'adult_face_note'     => 'Broad, wrinkled, massive head. Deep muzzle, black mask. Drop ears. Dignified, calm expression. Heavy jowls.',
      ]);
    } elseif ($this->mb($b, ['leonberger'])) {
      return array_merge($profile, [
        'size_category'       => 'giant',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note'           => 'Leonbergers grow into giant, lion-maned, majestic dogs.',
        'adult_body_note'     => 'Giant, muscular, well-proportioned. Thick lion-like mane around neck. Weight 41–75 kg.',
        'adult_face_note'     => 'Elongated lion-like face. Black mask, medium-length muzzle. Drop ears. Gentle, friendly expression.',
      ]);

      // ── MIXED / UNKNOWN ──────────────────────────────────────────────────
    } elseif ($this->mb($b, ['aspin', 'askal', 'philippine', 'mixed', 'mongrel', 'mutt', 'crossbreed'])) {
      return array_merge($profile, [
        'size_category'       => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note'           => 'Mixed breed dogs vary. Expect moderate to significant growth into a lean, athletic adult.',
        'adult_body_note'     => 'Lean, athletic, well-proportioned medium body. Short easy-care coat. Weight 10–25 kg depending on parentage.',
        'adult_face_note'     => 'Defined adult muzzle and facial structure. Alert, intelligent expression.',
      ]);
    }

    // ── FALLBACK for any unrecognized breed ──────────────────────────────
    return $profile;
  }

  /**
   * Flexible substring-based breed matching
   */
  private function mb(string $breedLower, array $patterns): bool
  {
    foreach ($patterns as $pattern) {
      if (stripos($breedLower, $pattern) !== false) return true;
    }
    return false;
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  IMAGE HELPERS
  // ─────────────────────────────────────────────────────────────────────────

  private function prepareHighQualityImage(string $fullPath): ?array
  {
    try {
      $cacheKey = 'hq_img_' . md5($fullPath);
      return Cache::remember($cacheKey, 600, function () use ($fullPath) {

        // Support both full URL paths and relative storage paths
        if (str_starts_with($fullPath, 'http://') || str_starts_with($fullPath, 'https://')) {
          // Download from URL
          $client = new Client(['timeout' => 30]);
          $response = $client->get($fullPath);
          $imageContents = $response->getBody()->getContents();
        } else {
          $imageContents = Storage::disk('object-storage')->get($fullPath);
        }

        if (empty($imageContents)) {
          throw new \Exception('Empty or missing image file: ' . $fullPath);
        }

        $imageInfo = @getimagesizefromstring($imageContents);
        if ($imageInfo === false) {
          throw new \Exception('Could not identify image format for: ' . $fullPath);
        }

        $origWidth  = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $targetSize = 1024;

        if ($origWidth > $targetSize || $origHeight > $targetSize) {
          $imageContents = $this->resizeImage($imageContents, $targetSize);
          $resized = @getimagesizefromstring($imageContents);
          $width   = $resized[0];
          $height  = $resized[1];
        } else {
          $width  = $origWidth;
          $height = $origHeight;
        }

        $img = @imagecreatefromstring($imageContents);
        if ($img === false) {
          throw new \Exception('GD failed to parse image data');
        }

        ob_start();
        imagejpeg($img, null, 90);
        $optimized = ob_get_clean();
        imagedestroy($img);

        Log::info("✅ Image prepared: {$width}x{$height} — " . round(strlen($optimized) / 1024, 2) . ' KB');

        return [
          'base64'      => base64_encode($optimized),
          'mimeType'    => 'image/jpeg',
          'width'       => $width,
          'height'      => $height,
          'origWidth'   => $origWidth,
          'origHeight'  => $origHeight,
          'aspectRatio' => round($origWidth / $origHeight, 4),
        ];
      });
    } catch (\Exception $e) {
      Log::error('Image preparation failed: ' . $e->getMessage(), ['path' => $fullPath]);
      return null;
    }
  }

  private function resizeImage(string $imageContents, int $maxSize): string
  {
    $source = @imagecreatefromstring($imageContents);
    if ($source === false) throw new \Exception('GD failed to create source image for resize');

    $width  = imagesx($source);
    $height = imagesy($source);
    $ratio  = min($maxSize / $width, $maxSize / $height);
    $newW   = (int) ($width * $ratio);
    $newH   = (int) ($height * $ratio);

    $resized = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

    ob_start();
    imagejpeg($resized, null, 90);
    $output = ob_get_clean();

    imagedestroy($source);
    imagedestroy($resized);
    return $output;
  }

  private function saveImage(string $imageOutput, string $type, $resultId, ?array $imageData = null): ?string
  {
    try {
      $img = @imagecreatefromstring($imageOutput);
      if ($img === false) throw new \Exception('GD failed to parse output image bytes');

      $outW = imagesx($img);
      $outH = imagesy($img);

      // Resize output to match original dimensions if available
      if ($imageData && isset($imageData['origWidth'], $imageData['origHeight'])) {
        $targetW = $imageData['origWidth'];
        $targetH = $imageData['origHeight'];

        if ($outW !== $targetW || $outH !== $targetH) {
          $resized = imagecreatetruecolor($targetW, $targetH);
          imagecopyresampled($resized, $img, 0, 0, 0, 0, $targetW, $targetH, $outW, $outH);
          imagedestroy($img);
          $img = $resized;
          Log::info("🔧 Output resized: {$outW}x{$outH} → {$targetW}x{$targetH}");
        }
      }

      ob_start();
      imagewebp($img, null, 88);
      $webpData = ob_get_clean();
      imagedestroy($img);

      $filename = "transform_{$resultId}_{$type}_" . time() . '.webp';
      $path     = "simulations/{$filename}";
      Storage::disk('object-storage')->put($path, $webpData);

      Log::info("💾 Saved: {$path} (" . round(strlen($webpData) / 1024, 2) . ' KB)');
      return $path;
    } catch (\Exception $e) {
      Log::error('saveImage failed: ' . $e->getMessage(), ['type' => $type, 'result_id' => $resultId]);
      return null;
    }
  }

  private function updateStatus(Results $result, string $status, array $paths = [], array $profile = [], ?string $error = null): void
  {
    $data = [
      'status'     => $status,
      '1_years'    => $paths['1_years'] ?? null,
      '3_years'    => $paths['3_years'] ?? null,
      'updated_at' => now()->toIso8601String(),
    ];

    if (!empty($profile)) $data['breed_profile'] = $profile;
    if ($error)           $data['error']         = $error;

    $result->update(['simulation_data' => json_encode($data)]);

    Cache::forget("simulation_status_{$result->scan_id}");
    Cache::forget("sim_status_{$result->scan_id}");
  }
}
