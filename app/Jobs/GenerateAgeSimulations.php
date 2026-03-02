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

  public $timeout    = 360;
  public $tries      = 3;
  public $backoff    = [20, 60, 120];

  // ─── Model priority list (best to fallback) ───────────────────────────────
  // Nano Banana Pro = gemini-3-pro-image-preview  (best image editing + thinking)
  // Nano Banana 2   = gemini-3.1-flash-image-preview (fast, good quality)
  // Nano Banana     = gemini-2.5-flash-image         (stable fallback)
  // Legacy fallback = gemini-2.0-flash-exp-image-generation
  private const MODEL_PRIORITY = [
    'gemini-3-pro-image-preview',            // Nano Banana Pro  — best quality
    'gemini-3.1-flash-image-preview',         // Nano Banana 2    — fast + quality
    'gemini-2.5-flash-image',                 // Nano Banana      — stable
    'gemini-2.0-flash-exp-image-generation',  // Legacy fallback
  ];

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

    $result = null;
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

      // ── Detect current age stage ───────────────────────────────────
      $currentAgeStage = $this->detectAgeStage($imageData);
      Log::info("🔍 Detected age stage: {$currentAgeStage}");

      // ── Build breed profile ────────────────────────────────────────
      $breedProfile = $this->getBreedProfile($this->breed);
      $breedProfile['detected_age_stage'] = $currentAgeStage;
      Log::info('📊 Breed Profile', [
        'breed'      => $breedProfile['breed'],
        'size'       => $breedProfile['size_category'],
        'coat'       => $breedProfile['coat_type'],
        'age_stage'  => $currentAgeStage,
      ]);

      // ── Select best available model ────────────────────────────────
      $selectedModel = $this->selectBestModel();
      Log::info("🤖 Using model: {$selectedModel}");

      // ── Run generation in parallel ─────────────────────────────────
      $simulations = $this->generateTransformations($imageData, $breedProfile, $selectedModel);

      $savedPaths = ['1_years' => null, '3_years' => null];

      if (!empty($simulations['1_year'])) {
        $savedPaths['1_years'] = $this->saveImage($simulations['1_year'], '1_year', $this->resultId, $imageData);
        Log::info("✅ 1-year saved: {$savedPaths['1_years']}");
      } else {
        Log::warning('⚠️ No image data for 1-year simulation');
      }

      if (!empty($simulations['3_years'])) {
        $savedPaths['3_years'] = $this->saveImage($simulations['3_years'], '3_years', $this->resultId, $imageData);
        Log::info("✅ 3-years saved: {$savedPaths['3_years']}");
      } else {
        Log::warning('⚠️ No image data for 3-year simulation');
      }

      $finalStatus = ($savedPaths['1_years'] || $savedPaths['3_years']) ? 'complete' : 'failed';
      $this->updateStatus($result, $finalStatus, $savedPaths, $breedProfile);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("🎉 SIMULATION {$finalStatus} in {$elapsed}s | model: {$selectedModel}");
    } catch (\Exception $e) {
      Log::error('❌ SIMULATION FAILED', [
        'result_id' => $this->resultId,
        'error'     => $e->getMessage(),
        'line'      => $e->getLine(),
        'file'      => basename($e->getFile()),
      ]);
      $r = $result ?? Results::find($this->resultId);
      if ($r) $this->updateStatus($r, 'failed', [], [], $e->getMessage());
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  MODEL SELECTION — try best model first, fall back on error
  // ─────────────────────────────────────────────────────────────────────────

  private function selectBestModel(): string
  {
    // Check if a preferred model is configured
    $configured = config('services.gemini.image_model') ?? env('GEMINI_IMAGE_MODEL');
    if ($configured) return $configured;

    // Otherwise return the top priority model; fallback happens at call time
    return self::MODEL_PRIORITY[0];
  }

    // ─────────────────────────────────────────────────────────────────────────
    //  AGE STAGE DETECTION (pre-pass to Gemini)
    // ─────────────────────────────────────────────────────────────────────────

  /**
   * Ask Gemini (text-only, cheap call) to classify the dog's current age stage.
   * Returns: 'puppy' | 'teenager' | 'young_adult' | 'adult' | 'senior'
   */
  private function detectAgeStage(array $imageData): string
  {
    try {
      $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
      $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

      $payload = [
        'contents' => [[
          'parts' => [
            [
              'text' => 'Look at this dog photo carefully. Classify the dog\'s current approximate age stage. '
                . 'Reply with ONLY one of these exact words: puppy | teenager | young_adult | adult | senior. '
                . 'Definitions: '
                . 'puppy = clearly a baby (0-6 months, very small, oversized paws/head, baby face, soft thin coat). '
                . 'teenager = adolescent (6-18 months, lanky/gangly, partially developed, still growing). '
                . 'young_adult = 1-2 years, mostly adult proportions but still filling out. '
                . 'adult = fully mature 2-6 years, peak condition. '
                . 'senior = visibly aging 7+ years, gray muzzle, less muscle, slower look. '
                . 'Reply with exactly one word only.',
            ],
            [
              'inlineData' => [
                'mimeType' => $imageData['mimeType'],
                'data'     => $imageData['base64'],
              ],
            ],
          ],
        ]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 10],
      ];

      $client   = new Client(['timeout' => 20]);
      $response = $client->post($endpoint, ['json' => $payload, 'headers' => ['Content-Type' => 'application/json']]);
      $data     = json_decode($response->getBody()->getContents(), true);
      $text     = trim(strtolower($data['candidates'][0]['content']['parts'][0]['text'] ?? 'adult'));

      $valid = ['puppy', 'teenager', 'young_adult', 'adult', 'senior'];
      foreach ($valid as $v) {
        if (str_contains($text, $v)) return $v;
      }
      return 'adult';
    } catch (\Exception $e) {
      Log::warning('Age detection failed, defaulting to adult: ' . $e->getMessage());
      return 'adult';
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  PARALLEL GENERATION WITH MODEL FALLBACK
  // ─────────────────────────────────────────────────────────────────────────

  private function generateTransformations(array $imageData, array $breedProfile, string $primaryModel): array
  {
    $results = ['1_year' => null, '3_years' => null];

    $prompt1Year  = $this->buildAgingPrompt($breedProfile, 1);
    $prompt3Years = $this->buildAgingPrompt($breedProfile, 3);

    // Try each model in priority order if previous fails
    $modelsToTry = array_unique(array_merge([$primaryModel], self::MODEL_PRIORITY));

    foreach ($modelsToTry as $modelName) {
      if ($results['1_year'] && $results['3_years']) break;

      Log::info("🔄 Attempting generation with: {$modelName}");

      $client  = new Client(['timeout' => 180, 'connect_timeout' => 15]);
      $maxAttempts = 2;

      for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        try {
          $promises = [];
          if (!$results['1_year']) {
            $promises['1_year']  = $this->createGenerationPromise($client, $prompt1Year, $imageData, $modelName);
          }
          if (!$results['3_years']) {
            $promises['3_years'] = $this->createGenerationPromise($client, $prompt3Years, $imageData, $modelName);
          }

          if (empty($promises)) break;

          $settled = Promise\Utils::settle($promises)->wait();

          foreach ($settled as $key => $result) {
            if ($result['state'] === 'fulfilled' && !empty($result['value'])) {
              $results[$key] = $result['value'];
              Log::info("✅ {$key} succeeded with {$modelName}");
            } else {
              $reason = $result['reason'] ?? null;
              Log::warning("⚠️ {$key} failed with {$modelName} attempt " . ($attempt + 1), [
                'reason' => $reason ? $reason->getMessage() : 'null value',
              ]);
            }
          }

          if ($results['1_year'] && $results['3_years']) break 2;

          if ($attempt < $maxAttempts - 1) {
            sleep((int) pow(2, $attempt + 1));
          }
        } catch (\Exception $e) {
          Log::error("Model {$modelName} attempt {$attempt} exception: " . $e->getMessage());
          if ($attempt < $maxAttempts - 1) sleep(5);
        }
      }
    }

    return $results;
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  GEMINI API CALL (async promise)
  // ─────────────────────────────────────────────────────────────────────────

  private function createGenerationPromise(Client $client, string $prompt, array $imageData, string $modelName)
  {
    $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    // Nano Banana / Gemini 3 models support thinking — lower temperature for fidelity
    $isThinkingModel = in_array($modelName, [
      'gemini-3-pro-image-preview',
      'gemini-3.1-flash-image-preview',
      'gemini-2.5-flash-image',
      'nano-banana-pro-preview',
    ]);

    $generationConfig = [
      'temperature'        => $isThinkingModel ? 0.1 : 0.2,
      'topK'               => $isThinkingModel ? 32 : 40,
      'topP'               => $isThinkingModel ? 0.75 : 0.80,
      'maxOutputTokens'    => 32768,
      'responseModalities' => ['IMAGE', 'TEXT'],
    ];

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
      'generationConfig' => $generationConfig,
      'safetySettings'   => [
        ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
      ],
    ];

    return $client->postAsync($endpoint, [
      'json'    => $payload,
      'headers' => ['Content-Type' => 'application/json'],
    ])->then(function ($response) use ($modelName) {
      return $this->extractImage($response, $modelName);
    });
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  EXTRACT IMAGE FROM RESPONSE
  // ─────────────────────────────────────────────────────────────────────────

  private function extractImage($response, string $modelName = ''): ?string
  {
    $body         = $response->getBody()->getContents();
    $responseData = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON from Gemini API ({$modelName})");
    }

    if (isset($responseData['error'])) {
      $errMsg  = $responseData['error']['message'] ?? 'Unknown API error';
      $errCode = $responseData['error']['code']    ?? 0;
      throw new \Exception("Gemini API error [{$errCode}] ({$modelName}): {$errMsg}");
    }

    if (!isset($responseData['candidates'][0])) {
      Log::error('No candidates from Gemini', [
        'model'    => $modelName,
        'preview'  => substr($body, 0, 800),
      ]);
      throw new \Exception("No candidates returned by {$modelName}");
    }

    $candidate    = $responseData['candidates'][0];
    $finishReason = $candidate['finishReason'] ?? '';

    if (in_array($finishReason, ['SAFETY', 'RECITATION', 'OTHER', 'PROHIBITED_CONTENT'])) {
      throw new \Exception("Generation blocked by {$modelName}: finishReason={$finishReason}");
    }

    $parts = $candidate['content']['parts'] ?? [];

    // Primary: inlineData image block
    foreach ($parts as $part) {
      if (isset($part['inlineData']['data']) && strlen($part['inlineData']['data']) > 200) {
        $decoded = base64_decode($part['inlineData']['data'], true);
        if ($decoded && strlen($decoded) > 2000) {
          Log::info("✅ Image from inlineData ({$modelName}) " . round(strlen($decoded) / 1024, 1) . ' KB');
          return $decoded;
        }
      }
    }

    // Fallback: base64 string in text block
    foreach ($parts as $part) {
      if (isset($part['text'])) {
        $text    = preg_replace('/```[\w]*\n?/', '', $part['text']);
        $text    = trim($text);
        $decoded = base64_decode($text, true);
        if ($decoded && strlen($decoded) > 5000) {
          Log::info("✅ Image from text block ({$modelName}) " . round(strlen($decoded) / 1024, 1) . ' KB');
          return $decoded;
        }
      }
    }

    throw new \Exception("No usable image data in response from {$modelName}");
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  MASTER PROMPT BUILDER
  //  — Four-phase approach:
  //    1. ANCHOR: lock every pixel of background/pose
  //    2. ASSESS: determine current age stage
  //    3. TRANSFORM: apply breed+age-accurate biological changes ONLY
  //    4. VERIFY: self-check checklist before outputting
  // ─────────────────────────────────────────────────────────────────────────

  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed        = $profile['breed'];
    $size         = $profile['size_category'];
    $coat         = $profile['coat_type'];
    $isBrachy     = $profile['brachycephalic'];
    $bodyShape    = $profile['body_shape'] ?? 'standard';
    $sizeNote     = $profile['size_note'] ?? '';
    $adultBody    = $profile['adult_body_note'] ?? '';
    $adultFace    = $profile['adult_face_note'] ?? '';
    $detectedAge  = $profile['detected_age_stage'] ?? 'unknown';

    $L = [];   // lines array

    // ══════════════════════════════════════════════════
    // HEADER
    // ══════════════════════════════════════════════════
    $L[] = '╔══════════════════════════════════════════════════════╗';
    $L[] = '║  TASK: DOG AGE PROGRESSION PHOTO EDIT               ║';
    $L[] = '╚══════════════════════════════════════════════════════╝';
    $L[] = '';
    $L[] = 'You are a professional photo retouching AI specializing in';
    $L[] = 'realistic canine age progression. This is a PHOTO EDIT, not';
    $L[] = 'a new image generation. You must edit the ATTACHED photograph.';
    $L[] = '';
    $L[] = "BREED DETECTED BY SYSTEM: {$breed}";
    $L[] = "CURRENT AGE STAGE IN PHOTO: {$detectedAge}";
    $L[] = "AGE ADVANCEMENT REQUESTED: +{$targetYears} year(s)";
    $L[] = '';

    // ══════════════════════════════════════════════════
    // PHASE 1 — PIXEL-PERFECT SCENE ANCHOR
    // ══════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 1 ▶ SCENE ANCHOR (do this FIRST before anything else)';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = 'Scan and memorize EVERY detail of the input photo:';
    $L[] = '';
    $L[] = '  📌 BACKGROUND — memorize and reproduce exactly:';
    $L[] = '     • Every element: grass, floor, wall, furniture, sky, trees, objects';
    $L[] = '     • Color tones, texture, blur level (depth of field)';
    $L[] = '     • Any shadows cast by the dog onto the background';
    $L[] = '';
    $L[] = '  📌 CAMERA — memorize and reproduce exactly:';
    $L[] = '     • Viewing angle (eye level, above, below)';
    $L[] = '     • Distance from subject (do not zoom in or out)';
    $L[] = '     • Crop and framing (do not recompose)';
    $L[] = '     • Focal length / perspective distortion';
    $L[] = '';
    $L[] = '  📌 LIGHTING — memorize and reproduce exactly:';
    $L[] = '     • Direction of main light source';
    $L[] = '     • Hard/soft quality of light';
    $L[] = '     • Position of all shadows on the dog and ground';
    $L[] = '     • Highlight placement on the coat';
    $L[] = '';
    $L[] = '  📌 DOG POSE — memorize and reproduce exactly:';
    $L[] = '     • ALL four leg positions and paw placements on the ground';
    $L[] = '     • Body orientation and rotation (which way dog faces)';
    $L[] = '     • Head angle: yaw (left/right), pitch (up/down), roll (tilt)';
    $L[] = '     • Ear position (pricked, folded, alert, relaxed)';
    $L[] = '     • Tail position (up, down, curled, tucked, wagging angle)';
    $L[] = '     • Whether sitting, standing, lying, crouching, running, jumping';
    $L[] = '     • Weight distribution (leaning left/right/forward/back)';
    $L[] = '';
    $L[] = '  🚫 ABSOLUTE PROHIBITIONS — these will make the result WRONG:';
    $L[] = '     ✗ Do NOT move, reposition, or adjust any limb or paw';
    $L[] = '     ✗ Do NOT change head angle or ear position';
    $L[] = '     ✗ Do NOT change body orientation or rotation';
    $L[] = '     ✗ Do NOT change the background (even slightly)';
    $L[] = '     ✗ Do NOT zoom in, zoom out, or reframe';
    $L[] = '     ✗ Do NOT add or remove any element from the scene';
    $L[] = '     ✗ Do NOT replace background with solid color/studio backdrop';
    $L[] = '     ✗ Do NOT resize the dog relative to the frame';
    $L[] = '     ✗ Do NOT change lighting direction or shadow positions';
    $L[] = '';

    // ══════════════════════════════════════════════════
    // PHASE 2 — CURRENT AGE CONFIRMATION
    // ══════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 2 ▶ CURRENT AGE CONFIRMATION';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = "The system pre-classified this dog as: [{$detectedAge}]";
    $L[] = '';
    $L[] = 'Use the following visual cues to confirm or override this classification:';
    $L[] = '';
    $L[] = '  PUPPY (0–6 months):';
    $L[] = '    • Head disproportionately large for body';
    $L[] = '    • Paws disproportionately large (classic puppy sign)';
    $L[] = '    • Very short, stubby legs relative to body';
    $L[] = '    • Round, soft facial features, chubby cheeks';
    $L[] = '    • Thin, underdeveloped, sparse coat';
    $L[] = '    • Round potbelly';
    $L[] = '    • Large, innocent, wide-open eyes';
    $L[] = '';
    $L[] = '  TEENAGER (6–18 months):';
    $L[] = '    • Gangly/lanky appearance — legs too long for body';
    $L[] = '    • Ears not yet fully upright (if an erect-ear breed)';
    $L[] = '    • Coat partially developed, still patchy or uneven';
    $L[] = '    • Face transitioning from puppy to adult proportions';
    $L[] = '    • Some puppy softness remains in features';
    $L[] = '';
    $L[] = '  YOUNG ADULT (1–2 years):';
    $L[] = '    • Mostly adult proportions but not fully filled out';
    $L[] = '    • Coat is developing but not at maximum density';
    $L[] = '    • Face defined but slightly softer than fully mature';
    $L[] = '';
    $L[] = '  ADULT (2–6 years):';
    $L[] = '    • Fully proportionate, filled-out adult body';
    $L[] = '    • Dense, full, healthy coat at peak condition';
    $L[] = '    • Strong, defined facial bone structure';
    $L[] = '    • No puppy softness at all';
    $L[] = '';
    $L[] = '  SENIOR (7+ years):';
    $L[] = '    • Visible gray on muzzle and around eyes';
    $L[] = '    • Slightly less muscle mass, softer body';
    $L[] = '    • Cloudier or slower-looking eyes';
    $L[] = '    • Coat may be slightly less lustrous';
    $L[] = '';
    $L[] = '  ⚠️  IMPORTANT: Do NOT assume the dog is a puppy.';
    $L[] = '  Apply ONLY the transformation appropriate to the actual age you see.';
    $L[] = '  If already adult, apply only subtle maturity changes — not a full puppy-to-adult transform.';
    $L[] = '';

    // ══════════════════════════════════════════════════
    // PHASE 3 — BREED-ACCURATE BIOLOGICAL TRANSFORMATION
    // ══════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "PHASE 3 ▶ BIOLOGICAL TRANSFORMATION: +{$targetYears} YEAR(S)";
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = "BREED: {$breed}";
    $L[] = "SIZE CLASS: " . strtoupper($size);
    $L[] = '';
    $L[] = '── SIZE & GROWTH RULE ──────────────────────────────────';
    $L[] = $sizeNote;
    $L[] = '';

    // Body shape special rules
    if ($bodyShape === 'long_low') {
      $L[] = '🔴 LONG-AND-LOW BREED RULE (critical):';
      $L[] = "   {$breed} has genetically short legs (chondrodystrophy).";
      $L[] = '   • Leg LENGTH must NEVER increase — legs stay very short.';
      $L[] = '   • Body grows LONGER and HEAVIER, not TALLER.';
      $L[] = '   • Height from ground stays minimal — this is not a build error.';
      $L[] = '   • Do NOT make this dog look taller. Do NOT elongate legs.';
      $L[] = '';
    } elseif ($bodyShape === 'sighthound') {
      $L[] = '🔴 SIGHTHOUND BREED RULE (critical):';
      $L[] = "   {$breed} is built for speed, not bulk.";
      $L[] = '   • DO NOT add heavy muscle or bulk.';
      $L[] = '   • Adult body is slender, elegant, lean — like a racing athlete.';
      $L[] = '   • Prominent arched back (roach back), very tucked waist, long thin legs.';
      $L[] = '';
    } elseif ($bodyShape === 'stocky') {
      $L[] = '── STOCKY BREED: grows wider/heavier, not necessarily taller.';
      $L[] = '';
    }

    if ($isBrachy) {
      $L[] = '🔴 BRACHYCEPHALIC BREED RULE (critical):';
      $L[] = "   {$breed} has a flat, shortened muzzle — this is permanent.";
      $L[] = '   • DO NOT elongate the muzzle or nose.';
      $L[] = '   • Flat face, pushed-in nose, prominent folds/wrinkles are permanent breed traits.';
      $L[] = '   • Aging adds depth/definition to wrinkles, NOT muzzle length.';
      $L[] = '';
    }

    // ── Coat rule (critical for breeds like Mudi, Pomeranian) ──────────
    $L[] = '── COAT PRESERVATION RULE (🔴 critical — read carefully) ──';
    $L[] = "   Current coat type for {$breed}: {$coat}";
    $L[] = '';
    switch ($coat) {
      case 'curly/fluffy':
        $L[] = '   This breed has a CURLY or FLUFFY coat.';
        $L[] = '   • The curls/fluffiness MUST be preserved in the aged version.';
        $L[] = '   • Aging makes the coat DENSER and MORE DEFINED — NOT straighter.';
        $L[] = '   • Do NOT change curly coat to straight, wavy, or flat coat.';
        $L[] = '   • The coat texture (curl pattern, puff shape) is a breed characteristic.';
        break;
      case 'double_coat':
        $L[] = '   This breed has a DOUBLE COAT (dense undercoat + guard hairs).';
        $L[] = '   • Aging makes the double coat THICKER and FULLER.';
        $L[] = '   • Do NOT change the coat to single-layer, short, or flat.';
        $L[] = '   • The coat becomes denser and more weather-resistant with age.';
        break;
      case 'long_silky':
        $L[] = '   This breed has a LONG SILKY coat.';
        $L[] = '   • Aging grows the coat LONGER and SILKIER.';
        $L[] = '   • Do NOT make the coat shorter, curlier, or wiry.';
        $L[] = '   • Feathering on ears, legs, belly, and tail becomes more pronounced.';
        break;
      case 'wire':
        $L[] = '   This breed has a WIRE/HARSH coat.';
        $L[] = '   • Aging makes the wiry texture MORE pronounced and DENSER.';
        $L[] = '   • Do NOT soften or smooth the coat — it should look rough and bristly.';
        $L[] = '   • Beard and eyebrow furnishings become more prominent.';
        break;
      case 'short':
        $L[] = '   This breed has a SHORT coat.';
        $L[] = '   • Aging makes the coat GLOSSIER, DENSER, and more defined.';
        $L[] = '   • Do NOT grow the coat longer or add texture that is not there.';
        break;
      default:
        $L[] = "   Preserve the existing coat texture and length accurately.";
        break;
    }
    $L[] = '';

    // ── Age-specific transformation ─────────────────────────────────────
    if ($targetYears === 1) {
      $L[] = '── TRANSFORMATION TARGET: +1 YEAR ─────────────────────────';
      $L[] = '';
      $L[] = '⚠️  CRITICAL RULE: The output MUST look VISIBLY DIFFERENT from the input.';
      $L[] = '    A viewer looking at the two images side-by-side must immediately notice';
      $L[] = '    the dog looks older. If the result looks nearly identical to the input,';
      $L[] = '    you have FAILED this task. Apply ALL changes listed for the age stage.';
      $L[] = '';
      $L[] = 'Apply changes based on the detected current age:';
      $L[] = '';
      $L[] = '  IF puppy → apply MAJOR transformation to early young adult:';
      $L[] = '    REMOVE:';
      $L[] = '      • Oversized round puppy head';
      $L[] = '      • Disproportionately large paws';
      $L[] = '      • Short stubby legs (if large/giant breed — elongate them)';
      $L[] = '      • Round soft baby face, chubby cheeks';
      $L[] = '      • Thin underdeveloped coat';
      $L[] = '      • Round potbelly';
      $L[] = '    ADD:';
      $L[] = '      • Head proportionate to adult body';
      $L[] = '      • Lean adolescent muscle definition (not yet fully built)';
      $L[] = '      • Developing adult coat texture (' . $this->coatChange1Year($coat) . ')';
      $L[] = '      • More angular, defined facial structure';
      $L[] = '      • Proportionate paws and legs';
      $L[] = '';
      $L[] = '  IF teenager → apply MODERATE transformation to young adult:';
      $L[] = '    • Reduce gangly proportions, fill out body noticeably';
      $L[] = '    • Erect ears if breed has them (fully upright now)';
      $L[] = '    • Coat becomes more uniform and developed';
      $L[] = '    • Face gains clear adult definition';
      $L[] = '';
      $L[] = '  IF young_adult → apply CLEAR transformation to full adult:';
      $L[] = '    • Visibly more filled-out chest, shoulders, and hindquarters';
      $L[] = '    • Coat reaches full adult density and peak condition';
      $L[] = '    • Face gains clearly more defined bone structure and stronger expression';
      $L[] = '    • Body is notably more muscular and balanced';
      $L[] = '';
      $L[] = '  IF adult → apply CLEARLY VISIBLE transformation to mature prime adult:';
      $L[] = '    ✦ These changes MUST be noticeable — not subtle. Make them clear:';
      $L[] = '    • MUSCLES: Noticeably more defined, denser musculature — chest, shoulders,';
      $L[] = '      hindquarters are clearly more powerful and filled-out than the input';
      $L[] = '    • FACE: Stronger, more chiseled facial bone structure; more defined stop;';
      $L[] = '      stronger, more prominent cheekbones; slightly heavier jaw; more mature';
      $L[] = '      and confident expression in the eyes';
      $L[] = '    • COAT: Noticeably richer, denser, more lustrous — coat quality at peak';
      $L[] = '    • BODY: Slightly heavier and broader overall — a dog at its physical peak';
      $L[] = '    • EXPRESSION: Calm, settled, confident — the look of a dog in its prime';
      $L[] = '    • This dog should look MEANINGFULLY older — prime of life vs. young adult';
      $L[] = '';
      $L[] = '  IF senior → apply VISIBLE transformation toward older senior:';
      $L[] = '    • More pronounced graying on muzzle, chin, and around eyes';
      $L[] = '    • Slightly reduced muscle tone and softer overall body';
      $L[] = '    • More gentle, wise, relaxed expression';
      $L[] = '    • Coat may be slightly less vibrant/lustrous';
      $L[] = '';
      $L[] = '── ADULT BODY TARGET ─────────────────────────────────────';
      $L[] = $adultBody;
      $L[] = '';
      $L[] = '── ADULT FACE TARGET ─────────────────────────────────────';
      $L[] = $adultFace;
      $L[] = '';
      $L[] = '── COAT AT 1 YEAR ────────────────────────────────────────';
      $L[] = $this->coatChange1Year($coat);
      $L[] = '';
      $L[] = '── GRAYING ────────────────────────────────────────────────';
      $L[] = 'NO gray hairs at this stage. This dog is young and vibrant.';
      $L[] = '';
      $L[] = '── EXPRESSION ─────────────────────────────────────────────';
      $L[] = 'Energetic, alert, curious young adult. Bright eyes, full of life.';
    } else {
      // +3 years
      $L[] = '── TRANSFORMATION TARGET: +3 YEARS ────────────────────────';
      $L[] = '';
      $L[] = '⚠️  CRITICAL RULE: +3 years MUST produce a CLEARLY and OBVIOUSLY different dog.';
      $L[] = '    Anyone looking at the before/after must immediately say "yes, that dog looks';
      $L[] = '    older." The changes must be SUBSTANTIAL and UNMISTAKABLE. If the result looks';
      $L[] = '    similar to the input, you have FAILED. Apply all changes listed below.';
      $L[] = '';
      $L[] = 'Apply changes based on the detected current age:';
      $L[] = '';
      $L[] = '  IF puppy OR teenager → apply FULL transformation to prime adult:';
      $L[] = '    • Complete adult body — absolutely no puppy/teen features remain';
      $L[] = '    • Full breed-characteristic skeletal structure at MAXIMUM size';
      $L[] = '    • Peak muscle development — noticeably powerful and filled-out';
      $L[] = '    • Complete adult coat at maximum beauty (' . $this->coatChange3Years($coat) . ')';
      $L[] = '    • Strong, confident, fully defined adult face — no softness remaining';
      $L[] = '';
      $L[] = '  IF young_adult → apply MAJOR transformation to prime adult:';
      $L[] = '    • Fully and visibly filled-out chest, shoulders, hindquarters';
      $L[] = '    • Clearly maximum breed muscle definition — powerfully built';
      $L[] = '    • Full coat density and texture at peak condition';
      $L[] = '    • Settled, confident mature expression — notably calmer and wiser';
      $L[] = '';
      $L[] = '  IF adult → apply SUBSTANTIAL and VISIBLE aging toward mature/senior adult:';
      $L[] = '    ✦ These changes MUST be clearly noticeable — substantial, not subtle:';
      $L[] = '    • MUZZLE GRAYING (MANDATORY — must be clearly visible):';
      $L[] = '      ' . $this->grayChange3Years($profile);
      $L[] = '      The graying must be OBVIOUS — a clear visual difference from the input.';
      $L[] = '    • FACE: More weathered, experienced look; slightly deeper facial lines;';
      $L[] = '      eyes carry more depth and wisdom; expression is calmer and more dignified;';
      $L[] = '      facial muscles slightly more relaxed than the prime adult look';
      $L[] = '    • BODY: Slightly less taut muscle — body is full and powerful but';
      $L[] = '      beginning the gradual transition from peak to settled maturity';
      $L[] = '    • COAT: Slightly richer, more settled texture — peak of coat development';
      $L[] = '    • EYES: Slightly more experienced, calm depth in the gaze';
      $L[] = '    • OVERALL: A dog that has lived life — distinguished, experienced, settled';
      $L[] = '    ✦ The result MUST be unmistakably different from the input photo.';
      $L[] = '';
      $L[] = '  IF senior → apply MEANINGFUL additional aging:';
      $L[] = '    • Clearly more pronounced graying covering muzzle, chin, around eyes';
      $L[] = '    • Visibly reduced muscle mass — softer body contours';
      $L[] = '    • More gentle, slower, tired expression';
      $L[] = '    • Coat noticeably less lustrous; possibly slightly thinner';
      $L[] = '    • Overall an obviously older, more veteran dog';
      $L[] = '';
      $L[] = '── ADULT BODY TARGET ─────────────────────────────────────';
      $L[] = $adultBody;
      $L[] = '';
      $L[] = '── ADULT FACE TARGET ─────────────────────────────────────';
      $L[] = $adultFace;
      $L[] = '';
      $L[] = '── COAT AT 3 YEARS ────────────────────────────────────────';
      $L[] = $this->coatChange3Years($coat);
      $L[] = '';
      $L[] = '── GRAYING ────────────────────────────────────────────────';
      $L[] = $this->grayChange3Years($profile);
      $L[] = '';
      $L[] = '── EXPRESSION ─────────────────────────────────────────────';
      $L[] = 'Calm, confident, settled, wise. The dignity of a dog in its prime.';
    }

    $L[] = '';

    // ── Biological realism rules ────────────────────────────────────────
    $L[] = '── BIOLOGICAL REALISM (apply to all transformations) ──────────';
    $L[] = '   • Growth follows real canine development — no artificial scaling or stretching';
    $L[] = '   • Fur direction stays consistent with original photo';
    $L[] = '   • Light falls on the coat the same way as in the original';
    $L[] = '   • Shadow positions on the dog match the original';
    $L[] = '   • Coat COLOR must be preserved — same hue, saturation, markings';
    $L[] = '   • Eye color preserved exactly';
    $L[] = '   • Nose color preserved exactly';
    $L[] = '   • Any distinctive markings (spots, patches, saddle, mask) preserved';
    $L[] = '   • Result must look like a REAL PHOTOGRAPH — no illustration/cartoon/art style';
    $L[] = '';

    // ── Health mandate ──────────────────────────────────────────────────
    $L[] = '── HEALTH MANDATE ─────────────────────────────────────────────';
    $L[] = '   ✅ Dog looks: healthy, well-fed, clean, well-groomed, happy or calm';
    $L[] = '   ❌ Dog must NOT look: sick, underweight, matted, sad, neglected, abused';
    $L[] = '   This is a well-loved, thriving pet dog.';
    $L[] = '';

    // ══════════════════════════════════════════════════
    // PHASE 4 — SELF-VERIFICATION CHECKLIST
    // ══════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 4 ▶ SELF-VERIFICATION (check ALL before outputting)';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = 'POSE CHECK:';
    $L[] = '  □ All four paw positions match the original exactly?';
    $L[] = '  □ Body orientation (facing direction) unchanged?';
    $L[] = '  □ Head angle (yaw, pitch, roll) unchanged?';
    $L[] = '  □ Ear position unchanged?';
    $L[] = '  □ Tail position unchanged?';
    $L[] = '';
    $L[] = 'SCENE CHECK:';
    $L[] = '  □ Background looks exactly like the input?';
    $L[] = '  □ Camera angle and zoom unchanged?';
    $L[] = '  □ Lighting direction and shadows unchanged?';
    $L[] = '  □ Nothing added or removed from scene?';
    $L[] = '';
    $L[] = 'AGING CHECK:';
    $L[] = "  □ Would a person seeing these two photos side-by-side IMMEDIATELY notice";
    $L[] = "    the dog looks +{$targetYears} year(s) older? (If NO → you must make the changes MORE visible)";
    $L[] = '  □ If dog was adult in input: are muscle definition, coat richness, facial';
    $L[] = '    maturity, and expression CLEARLY and NOTICEABLY different? (not subtle)';
    $L[] = '  □ If +3 years on adult: is muzzle graying CLEARLY VISIBLE?';
    $L[] = '  □ Breed-specific body shape preserved (not a generic dog)?';
    $L[] = '  □ Coat TYPE preserved (' . $coat . ' — not changed to different texture)?';
    $L[] = '  □ Coat color and markings preserved?';
    $L[] = '  □ Dog looks healthy and well-groomed?';
    $L[] = '';
    $L[] = '🚨 MOST IMPORTANT CHECK: Is the transformation VISIBLY OBVIOUS?';
    $L[] = '   If the output looks nearly the same as the input — REGENERATE with';
    $L[] = '   stronger, more dramatic aging effects before outputting.';
    $L[] = '⚠️  If ANY item is NO — you must fix it before outputting.';
    $L[] = '';
    $L[] = '╔══════════════════════════════════════════════════════╗';
    $L[] = '║  OUTPUT: The edited photograph.                      ║';
    $L[] = '║  Same dog. Same pose. Same scene. Biologically older.║';
    $L[] = '╚══════════════════════════════════════════════════════╝';

    return implode("\n", $L);
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  COAT & GRAY HELPERS
  // ─────────────────────────────────────────────────────────────────────────

  private function coatChange1Year(string $coat): string
  {
    return match ($coat) {
      'curly/fluffy'  => 'Curls more defined and denser — puppy fuzz replaced by characteristic adult curly/fluffy double coat. COAT REMAINS CURLY/FLUFFY.',
      'double_coat'   => 'Adult double coat developing — thicker undercoat, denser guard hairs forming. Healthy and lush.',
      'long_silky'    => 'Coat growing toward adult length — silky, flowing, well-groomed. Longer than puppy coat.',
      'wire'          => 'Wiry texture becoming defined — characteristic rough, dense, bristly texture of the breed. Beard and eyebrows more prominent.',
      'short'         => 'Short adult coat fully developed — smooth, glossy, healthy sheen. Dense and close-lying.',
      default         => 'Adult coat developing — becoming healthier, denser, and more defined.',
    };
  }

  private function coatChange3Years(string $coat): string
  {
    return match ($coat) {
      'curly/fluffy'  => 'Coat at full adult glory — dense, richly textured curls/fluff at peak condition. COAT REMAINS CURLY/FLUFFY. Well-groomed.',
      'double_coat'   => 'Dense, full double coat at peak — rich color and texture, thick undercoat, lustrous guard hairs.',
      'long_silky'    => 'Coat at full adult length — flowing, silky, beautiful and healthy. Feathering fully developed.',
      'wire'          => 'Wiry coat fully expressed — characteristically rough and dense at its best. Beard and eyebrows very prominent.',
      'short'         => 'Short coat glossy, dense, and sleek — fits the mature muscular body perfectly.',
      default         => 'Mature adult coat — full, healthy, clean, well-maintained.',
    };
  }

  private function grayChange3Years(array $profile): string
  {
    return match ($profile['gray_pattern'] ?? 'moderate') {
      'none'      => 'No gray hairs — this breed does not gray noticeably at 3 years. Coat color stays vivid.',
      'minimal'   => 'A few noticeable silver/gray hairs scattered on the muzzle tip — clearly visible on close inspection. Everything else unchanged.',
      'moderate'  => 'CLEARLY VISIBLE gray/silver graying on the muzzle tip, around the nostrils, and faint silver around the eyes — this must be obvious enough that a viewer immediately notices it. This is a distinguishing sign of age that MUST appear. Base coat color fully preserved everywhere else.',
      'prominent' => 'STRONGLY VISIBLE silver/gray graying covering the muzzle, chin, and around the eyes — unmistakable, handsome sign of maturity that any viewer would notice immediately. Coat color otherwise fully preserved.',
      default     => 'Visible muzzle-tip graying — clearly noticeable, not subtle.',
    };
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  BREED PROFILE DATABASE (comprehensive)
  // ─────────────────────────────────────────────────────────────────────────

  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    $default = [
      'breed'               => $breed,
      'size_category'       => 'medium',
      'body_shape'          => 'standard',
      'coat_type'           => 'short',
      'gray_pattern'        => 'moderate',
      'brachycephalic'      => false,
      'grows_significantly' => false,
      'adult_body_note'     => 'Well-proportioned adult body. Deeper chest than puppy, longer legs, healthy muscle.',
      'adult_face_note'     => 'Defined adult muzzle, proportionate head, alert and healthy expression.',
      'size_note'           => 'Expect moderate size increase from puppy to adult.',
    ];

    // ── MUDI (special case — curly medium herding dog) ────────────────
    if ($this->mb($b, ['mudi'])) {
      return array_merge($default, [
        'size_category'       => 'medium',
        'body_shape'          => 'athletic',
        'coat_type'           => 'curly/fluffy',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Mudis grow into medium-sized athletic herding dogs. Moderate size increase from puppy.',
        'adult_body_note'     => 'Medium, muscular, athletic body. Well-proportioned, agile build. Weight 8–13 kg. Slightly longer than tall. Strong hindquarters for herding.',
        'adult_face_note'     => 'Wedge-shaped head, slightly domed skull. Erect, pointed ears (fully upright in adults). Almond-shaped eyes. Defined, intelligent expression. Medium-length muzzle.',
      ]);
    }

    // ── TOY BREEDS ────────────────────────────────────────────────────
    if ($this->mb($b, ['chihuahua'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note' => 'Chihuahuas are one of the smallest breeds — adult is nearly the same size as puppy. No dramatic size change.',
        'adult_body_note' => 'Compact, fine-boned tiny body. Delicate legs. Weight 1.5–3 kg. Same tiny frame as puppy but more defined proportions.',
        'adult_face_note' => 'Large rounded apple-dome skull (permanent trait). Large, fully erect ears. Large, round, luminous eyes. Face becomes slightly more refined but stays delicate.',
      ]);
    }
    if ($this->mb($b, ['pomeranian'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern' => 'minimal',
        'size_note' => 'Pomeranians stay very small (1.8–3.5 kg). The characteristic rounded puff-ball shape develops more fully. NO height increase.',
        'adult_body_note' => 'Tiny compact body completely hidden beneath a massive, rounded, double coat. Body is compact and square. The thick fluffy coat is the breed\'s signature — it must stay rounded and puffed.',
        'adult_face_note' => 'Distinctive fox-like face with sharp, pointed muzzle emerging from the coat ruff. Small, erect, triangular ears at top of head. Small, bright, almond eyes. Thick lion-like mane around the neck.',
      ]);
    }
    if ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'prominent',
        'size_note' => 'Yorkshire Terriers stay tiny — 2–3 kg. Long silky coat develops fully.',
        'adult_body_note' => 'Very small, fine-boned, compact body hidden under long, silky, floor-length coat.',
        'adult_face_note' => 'Small flat face with medium-length muzzle. V-shaped, fully erect ears. Classic steel-blue and tan coloring becomes more defined.',
      ]);
    }
    if ($this->mb($b, ['maltese'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'Maltese stay tiny. Pure white long silky coat develops fully and dramatically.',
        'adult_body_note' => 'Tiny compact body completely covered in long, flowing, pure white silky coat.',
        'adult_face_note' => 'Gentle, sweet face. Medium-length muzzle, large dark eyes, drop ears hidden under long white silky hair.',
      ]);
    }
    if ($this->mb($b, ['papillon'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Papillons stay small — 3–5 kg. Signature butterfly ears grow very large and prominent.',
        'adult_body_note' => 'Fine-boned, elegant tiny body with flowing coat.',
        'adult_face_note' => 'SIGNATURE: Large butterfly-shaped ears, fully erect, heavily fringed with long hair — most distinctive feature of the breed.',
      ]);
    }
    if ($this->mb($b, ['italian greyhound'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'sighthound',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note' => 'Italian Greyhounds stay small. Their sighthound shape becomes more defined.',
        'adult_body_note' => 'Extremely slender, elegant sighthound. Arched back, very deep narrow chest, tucked-up abdomen, long thin legs. Graceful.',
        'adult_face_note' => 'Long, narrow, fine head. Large doe eyes. Folded-back ears when relaxed.',
      ]);
    }
    if ($this->mb($b, ['miniature pinscher', 'min pin'])) {
      return array_merge($default, [
        'size_category' => 'toy',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'gray_pattern' => 'minimal',
        'size_note' => 'Min Pins stay small but develop a lean, muscular, athletic build.',
        'adult_body_note' => 'Compact, muscular, athletic tiny body. High-stepping hackney gait. Very lean with defined muscle.',
        'adult_face_note' => 'Strong, narrow head. Fully erect ears. Alert, fearless, bold expression.',
      ]);
    }

    // ── SMALL BREEDS ──────────────────────────────────────────────────
    if ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'double_coat',
        'grows_significantly' => false,
        'gray_pattern' => 'moderate',
        'size_note' => 'Corgis are a long-and-low breed. THEY DO NOT GROW TALL. Short legs are a genetic trait (chondrodystrophy). Body gets more muscular and defined but stays low to ground.',
        'adult_body_note' => 'Long body, very short legs, deep chest, muscular hindquarters. Body length is much greater than height. Weight 10–14 kg. Always stays low to ground.',
        'adult_face_note' => 'Fox-like face. Large upright pointed ears — fully erect and prominent in adults. Strong muzzle. Alert, intelligent, foxy expression.',
      ]);
    }
    if ($this->mb($b, ['dachshund', 'doxie', 'sausage', 'wiener'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'size_note' => 'Dachshunds have extremely elongated bodies and very short legs. LEGS DO NOT GROW TALLER. Body grows longer and heavier.',
        'adult_body_note' => 'Extremely elongated body, very short stubby legs, deep keel-shaped chest. The iconic sausage dog silhouette. Weight 7–14 kg (standard).',
        'adult_face_note' => 'Long, tapered muzzle fully developed. Long, floppy ears. Strong jaw. Confident, alert expression.',
      ]);
    }
    if ($this->mb($b, ['beagle'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'gray_pattern' => 'moderate',
        'size_note' => 'Beagles grow moderately into a compact, sturdy hound.',
        'adult_body_note' => 'Solid, muscular, compact body. Deep chest, strong back, sturdy legs. Weight 9–11 kg.',
        'adult_face_note' => 'Classic hound face — long square muzzle, long floppy ears, large brown eyes, gentle expression.',
      ]);
    }
    if ($this->mb($b, ['french bulldog'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'French Bulldogs stay small and stocky. They get heavier and more muscular but NOT taller.',
        'adult_body_note' => 'Heavy, muscular, compact. Very wide shoulders and chest, narrow hindquarters. Short stocky legs. Weight 9–13 kg.',
        'adult_face_note' => 'Flat face with deep wrinkles/folds. Massive square head. Bat-like erect ears — breed signature. Very short pushed-in nose. Heavy jowls.',
      ]);
    }
    if ($this->mb($b, ['pug'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Pugs stay small and round. They may get heavier and rounder but NOT taller.',
        'adult_body_note' => 'Cobby, round, compact. Heavy for size. Wide body, deep chest. Weight 6–9 kg. Tightly curled tail.',
        'adult_face_note' => 'Massive round head, very flat face, deep wrinkles, large bulging eyes, very short nose, heavy jowls.',
      ]);
    }
    if ($this->mb($b, ['boston terrier'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'size_note' => 'Boston Terriers stay small and square. Weight 5–11 kg.',
        'adult_body_note' => 'Square, compact, muscular body. Deep chest, short back.',
        'adult_face_note' => 'Square flat face, large round eyes, erect ears. Tuxedo coat pattern (black and white) well-defined.',
      ]);
    }
    if ($this->mb($b, ['shih tzu'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'size_note' => 'Shih Tzus stay small and compact. Long flowing coat develops fully.',
        'adult_body_note' => 'Compact, sturdy, slightly longer than tall. Weight 4–8 kg. Covered in long flowing double coat.',
        'adult_face_note' => 'Sweet flat face with long flowing facial hair. Large dark eyes, broad muzzle. Distinctive topknot.',
      ]);
    }
    if ($this->mb($b, ['bichon frise', 'bichon'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'Bichon Frises stay small with a distinctive puffed-round white coat.',
        'adult_body_note' => 'Small compact body covered in dense, curly, white coat trimmed into a perfect sphere/round shape.',
        'adult_face_note' => 'Round, powder-puff face. Dark round eyes, black nose, surrounded by fluffy white rounded coat.',
      ]);
    }
    if ($this->mb($b, ['cavalier king charles', 'cavalier'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Cavaliers stay small and elegant. Weight 5–8 kg.',
        'adult_body_note' => 'Small, elegant, graceful body with flowing silky coat on ears, chest, legs, and tail.',
        'adult_face_note' => 'Gentle, melting expression. Large, round, dark eyes. Long, silky, flowing ears. Sweet, kind face.',
      ]);
    }
    if ($this->mb($b, ['cocker spaniel', 'english cocker', 'american cocker'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Cocker Spaniels grow moderately. Heavy feathering develops on ears, legs, and belly.',
        'adult_body_note' => 'Compact, sturdy with well-developed chest. Heavy silky feathering on ears, chest, legs.',
        'adult_face_note' => 'Broad, rounded head. Long, low-set, heavily feathered ears. Large, round, expressive eyes.',
      ]);
    }
    if ($this->mb($b, ['shiba inu', 'shiba'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Shiba Inus grow into compact, fox-like small dogs. Moderate size increase.',
        'adult_body_note' => 'Compact, well-muscled, agile. Thick double coat. Tightly curled tail. Weight 8–11 kg.',
        'adult_face_note' => 'Fox-like — triangular head, small erect triangular ears, small almond eyes with distinctive markings. Cream/white face markings.',
      ]);
    }
    if ($this->mb($b, ['miniature schnauzer'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'gray_pattern' => 'prominent',
        'size_note' => 'Miniature Schnauzers stay small and square. Distinctive beard and eyebrows are signature features.',
        'adult_body_note' => 'Square build — height equals length. Compact, muscular, wiry-coated.',
        'adult_face_note' => 'Rectangular strong head. SIGNATURE: long bushy eyebrows and very thick beard. V-shaped ears.',
      ]);
    }
    if ($this->mb($b, ['jack russell', 'jack russel', 'parson russell'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'athletic',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'size_note' => 'Jack Russells stay small but are very muscular and athletic.',
        'adult_body_note' => 'Small, tough, compact, athletic body. Weight 5–8 kg. Lean, hard muscle.',
        'adult_face_note' => 'Flat skull, strong muzzle. V-shaped drop or button ears. Alert, feisty, intelligent expression.',
      ]);
    }
    if ($this->mb($b, ['scottish terrier', 'scotty'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'size_note' => 'Scottish Terriers stay small and low-slung.',
        'adult_body_note' => 'Compact, low-slung, very sturdy. Short legs, barrel chest, thick wiry coat.',
        'adult_face_note' => 'Strong wedge-shaped head with very prominent beard and eyebrows. Erect pointed ears.',
      ]);
    }
    if ($this->mb($b, ['westie', 'west highland'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'stocky',
        'coat_type' => 'wire',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'West Highland White Terriers stay small and compact.',
        'adult_body_note' => 'Compact, sturdy, all-white wiry-coated body. Short legs, barrel chest.',
        'adult_face_note' => 'Round head with white wiry coat, prominent beard and eyebrows. Dark eyes. Erect pointed ears.',
      ]);
    }
    if ($this->mb($b, ['havanese'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'compact',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern' => 'none',
        'size_note' => 'Havanese stay small with a long, silky coat that develops dramatically.',
        'adult_body_note' => 'Small, sturdy body covered in long, silky, slightly wavy coat.',
        'adult_face_note' => 'Broad, rounded head, large almond eyes, drop ears with long silky hair. Sweet, alert expression.',
      ]);
    }
    if ($this->mb($b, ['lhasa apso'])) {
      return array_merge($default, [
        'size_category' => 'small',
        'body_shape' => 'long_low',
        'coat_type' => 'long_silky',
        'grows_significantly' => false,
        'size_note' => 'Lhasa Apsos stay small. Floor-length coat develops fully.',
        'adult_body_note' => 'Longer than tall, sturdy body beneath a heavy, long, flowing coat.',
        'adult_face_note' => 'Heavy floor-length coat falls over the face. Strong muzzle, dark eyes. Dignified expression.',
      ]);
    }

    // ── MEDIUM BREEDS ─────────────────────────────────────────────────
    if ($this->mb($b, ['border collie'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Border Collies grow into a lean, athletic medium dog. Noticeably taller and longer than puppy.',
        'adult_body_note' => 'Athletic, lithe, graceful. Lean muscle, not bulky. Well-proportioned, agile frame. Weight 14–20 kg.',
        'adult_face_note' => 'SIGNATURE: intense, intelligent, focused expression. Medium muzzle, semi-erect forward-tipping ears. Alert eyes.',
      ]);
    }
    if ($this->mb($b, ['australian shepherd', 'aussie'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Australian Shepherds grow into a well-muscled medium dog.',
        'adult_body_note' => 'Medium, muscular, agile, slightly longer than tall. Strong bone, well-developed chest.',
        'adult_face_note' => 'Balanced head, medium muzzle. Striking eye colors possible (blue, amber, brown). Semi-erect or rose ears.',
      ]);
    }
    if ($this->mb($b, ['whippet'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'sighthound',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Whippets grow into a slender, elegant sighthound.',
        'adult_body_note' => 'Slender sighthound — pronounced arched back, very deep narrow chest, extremely tucked waist, long thin legs. Weight 11–20 kg.',
        'adult_face_note' => 'Long, fine, lean head. Rose-shaped small ears. Alert, gentle expression.',
      ]);
    }
    if ($this->mb($b, ['bulldog', 'english bulldog'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => false,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Bulldogs get heavier and more wrinkled but NOT taller.',
        'adult_body_note' => 'Extremely wide, heavy, low-slung. Massive chest, short bowed legs, wide shoulders. Weight 22–25 kg. Classic waddling walk.',
        'adult_face_note' => 'Massive wrinkled face with deep skin folds, very flat nose, pronounced underbite, huge jowls.',
      ]);
    }
    if ($this->mb($b, ['chow chow'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Chow Chows grow into a large, lion-maned, dignified dog.',
        'adult_body_note' => 'Large, powerful, compact, square body. Distinctive stilted gait. Massive lion mane of fur. Weight 20–32 kg.',
        'adult_face_note' => 'Broad, massive head. Scowling dignified expression. Blue-black tongue. Heavy lion-like mane.',
      ]);
    }
    if ($this->mb($b, ['shar pei'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'size_note' => 'Shar Peis grow moderately. NOTE: wrinkles become tighter and less extreme in adults (puppies have MORE wrinkles proportionally).',
        'adult_body_note' => 'Medium, compact, square. Weight 18–25 kg. Wrinkles concentrated on head and shoulders.',
        'adult_face_note' => 'Broad hippopotamus-like muzzle. Small sunken eyes, small folded ears. Blue-black tongue visible.',
      ]);
    }
    if ($this->mb($b, ['dalmatian'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Dalmatians grow into a large, lean, muscular spotted dog — 23–27 kg. As adults they age visibly: coat becomes glossier and richer, muscles more defined, and muzzle gradually grays with age.',
        'adult_body_note'     => 'Large, lean, muscular, elegant athletic body. Long well-muscled legs, deep chest, well-defined musculature especially in shoulders, back, and hindquarters. Spots are crisp and clearly defined. Weight 23–27 kg.',
        'adult_face_note'     => 'Long, strong, refined head with well-defined stop and cheekbones. Alert brown or blue eyes with a confident expression. Moderately large spotted drop ears. At 3+ years: natural silver/gray graying clearly visible on muzzle tip and around the nose bridge.',
      ]);
    }
    if ($this->mb($b, ['standard poodle'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'curly/fluffy',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Standard Poodles grow into elegant, tall, curly-coated dogs.',
        'adult_body_note' => 'Elegant, well-proportioned, athletic. Squarely built, long neck, deep chest. Weight 20–32 kg.',
        'adult_face_note' => 'Long, straight, fine muzzle. Almond eyes, long flat ears. Refined, intelligent expression.',
      ]);
    }
    if ($this->mb($b, ['schnauzer', 'standard schnauzer'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Standard Schnauzers grow into square, wiry-coated medium dogs.',
        'adult_body_note' => 'Square build, strong, compact. Wiry coat. Prominent beard and eyebrows. Weight 14–20 kg.',
        'adult_face_note' => 'Rectangular head. SIGNATURE: long bushy eyebrows and very thick beard.',
      ]);
    }
    if ($this->mb($b, ['airedale'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'size_note' => 'Airedales are the largest terrier — grow into athletic, wiry-coated medium dogs.',
        'adult_body_note' => 'Well-balanced, athletic medium body. Dense, hard, wiry black and tan coat. Weight 18–29 kg.',
        'adult_face_note' => 'Long, flat skull. Small V-shaped drop ears. Wiry beard. Alert, intelligent expression.',
      ]);
    }

    // ── LARGE BREEDS ──────────────────────────────────────────────────
    if ($this->mb($b, ['labrador', 'lab'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Labradors grow dramatically — much taller, heavier, and broader than puppy.',
        'adult_body_note' => 'Broad, powerful, strongly built. Wide head, deep chest, strong neck, thick otter tail. Weight 25–36 kg.',
        'adult_face_note' => 'Broad, clean-cut head. Wide powerful muzzle. Kind, intelligent eyes. Drop ears.',
      ]);
    }
    if ($this->mb($b, ['golden retriever'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'long_silky',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Golden Retrievers grow into large, beautiful, feathered dogs.',
        'adult_body_note' => 'Large, well-balanced, powerful. Deep chest, flowing golden coat, feathering on legs/belly/tail. Weight 25–34 kg.',
        'adult_face_note' => 'Broad, slightly arched skull. Gentle, intelligent expression. Drop ears, golden framing coat.',
      ]);
    }
    if ($this->mb($b, ['german shepherd', 'alsatian'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'German Shepherds grow dramatically — much taller, broader chest, strong angular body.',
        'adult_body_note' => 'Strong, agile, muscular. Slightly longer than tall, deep chest, characteristic sloping back. Bushy tail. Weight 22–40 kg.',
        'adult_face_note' => 'Strong wedge-shaped head. SIGNATURE: fully erect, pointed ears. Alert, intelligent expression. Strong muzzle.',
      ]);
    }
    if ($this->mb($b, ['rottweiler'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Rottweilers grow into powerful, massive dogs. Very dramatic size increase.',
        'adult_body_note' => 'Massive, powerful, compact. Heavy bone, deep broad chest. Weight 35–60 kg. Black and tan markings fully defined.',
        'adult_face_note' => 'Broad, powerful head. Strong wide muzzle. Drop ears. Calm, confident expression.',
      ]);
    }
    if ($this->mb($b, ['doberman', 'dobermann'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Dobermans grow into sleek, powerful, elegant large dogs.',
        'adult_body_note' => 'Compact, muscular, elegant. Square build, deep chest, well-arched neck. Weight 32–45 kg.',
        'adult_face_note' => 'Long, wedge-shaped head. Erect ears. Alert, intelligent, proud expression.',
      ]);
    }
    if ($this->mb($b, ['boxer'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Boxers grow into muscular, powerful dogs with a distinctive square head.',
        'adult_body_note' => 'Powerful, medium-large, square body. Well-muscled, deep chest, short back. Weight 25–32 kg.',
        'adult_face_note' => 'Broad, blunt, squarish muzzle. Strong underjaw. Wrinkled forehead. Energetic, alert expression.',
      ]);
    }
    if ($this->mb($b, ['siberian husky', 'husky'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Huskies grow into medium-large dogs with a dense, lush double coat.',
        'adult_body_note' => 'Medium-large, athletic, well-muscled. Thick double coat, bushy tail. Weight 16–27 kg.',
        'adult_face_note' => 'Finely chiseled head. Almond eyes (blue, brown, or heterochromatic). Erect ears. Striking facial markings.',
      ]);
    }
    if ($this->mb($b, ['alaskan malamute', 'malamute'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Malamutes grow into very large, heavy, powerful sled dogs.',
        'adult_body_note' => 'Large, powerful, heavy-boned. Deep chest, strong shoulders, very heavy coat. Weight 34–43 kg.',
        'adult_face_note' => 'Broad, powerful head. Brown almond eyes (never blue). Erect ears. Friendly, dignified expression.',
      ]);
    }
    if ($this->mb($b, ['weimaraner'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Weimaraners grow into sleek, elegant, silver-gray large dogs.',
        'adult_body_note' => 'Large, athletic, elegant. Sleek silver-gray coat. Deep chest. Weight 23–32 kg.',
        'adult_face_note' => 'Moderately long head. Amber or blue-gray eyes. Long drop ears. Aristocratic expression.',
      ]);
    }
    if ($this->mb($b, ['vizsla'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Vizslas grow into lean, elegant, golden-rust hunting dogs.',
        'adult_body_note' => 'Lean, elegant, well-muscled. Golden-rust short coat. Deep chest. Weight 20–29 kg.',
        'adult_face_note' => 'Lean, aristocratic head. Warm golden-brown eyes. Broad drop ears.',
      ]);
    }
    if ($this->mb($b, ['akita'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'minimal',
        'size_note' => 'Akitas grow into very large, powerful, bear-like dogs.',
        'adult_body_note' => 'Large, powerful, heavy-boned. Deep broad chest, thick neck, curled tail. Weight 32–59 kg.',
        'adult_face_note' => 'Broad, massive bear-like head. Small triangular erect ears. Deep-set triangular eyes. Powerful muzzle. Dignified.',
      ]);
    }
    if ($this->mb($b, ['samoyed'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Samoyeds grow into medium-large dogs with a spectacular white double coat.',
        'adult_body_note' => 'Medium-large, well-proportioned under a thick, stand-off white double coat. Weight 16–30 kg.',
        'adult_face_note' => 'Wedge-shaped head. SIGNATURE: "Samoyed smile" — upturned mouth corners. Erect ears. Full white mane.',
      ]);
    }
    if ($this->mb($b, ['giant schnauzer'])) {
      return array_merge($default, [
        'size_category' => 'large',
        'body_shape' => 'square',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'gray_pattern' => 'prominent',
        'size_note' => 'Giant Schnauzers grow into powerful large dogs.',
        'adult_body_note' => 'Large, powerful, compact, square. Dense wiry coat. Weight 25–48 kg.',
        'adult_face_note' => 'Powerful rectangular head. Very prominent bushy eyebrows and thick beard. Bold expression.',
      ]);
    }

    // ── GIANT BREEDS ──────────────────────────────────────────────────
    if ($this->mb($b, ['great dane'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'Great Danes are the tallest breed. Growth is EXTREME. At 1yr: very tall adolescent like a young horse. At 3yr: one of the largest dogs on Earth. Stands 71–86 cm at shoulder.',
        'adult_body_note' => 'Enormous, powerful, elegant. Very long legs, deep massive chest. Weight 50–90 kg.',
        'adult_face_note' => 'Large rectangular expressive head. Strong muzzle, drop or cropped erect ears. Noble expression.',
      ]);
    }
    if ($this->mb($b, ['saint bernard'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Saint Bernards grow into enormous, massive dogs. One of the heaviest breeds.',
        'adult_body_note' => 'Enormous, very heavy, powerful. Deep wide chest, massive bone, thick coat. Weight 64–120 kg. Heavy jowls.',
        'adult_face_note' => 'Massive broad head. Deep wrinkles, hanging jowls, kind soulful eyes. Drop ears.',
      ]);
    }
    if ($this->mb($b, ['newfoundland', 'newfy'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Newfoundlands grow into massive, bear-like water dogs.',
        'adult_body_note' => 'Massive, heavy-boned, muscular. Thick water-resistant double coat. Weight 54–68 kg.',
        'adult_face_note' => 'Broad massive head. Soft dark eyes. Small drop ears. Gentle, sweet expression.',
      ]);
    }
    if ($this->mb($b, ['irish wolfhound'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'sighthound',
        'coat_type' => 'wire',
        'grows_significantly' => true,
        'size_note' => 'Irish Wolfhounds grow into one of the tallest dogs in the world. Dramatic growth.',
        'adult_body_note' => 'Enormous, long, lean, muscular sighthound. Very long legs, arched back, deep chest. Rough wiry coat. Weight 48–69 kg.',
        'adult_face_note' => 'Long, narrow head. Small folded ears. Gentle, calm expression. Rough wiry beard.',
      ]);
    }
    if ($this->mb($b, ['bernese mountain dog', 'bernese', 'berner'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Bernese Mountain Dogs grow into large, heavy, tri-colored mountain dogs.',
        'adult_body_note' => 'Large, heavy, sturdy. Broad chest, strong legs. Long thick silky tricolor coat (black, white, rust). Weight 36–55 kg.',
        'adult_face_note' => 'Broad flat skull. Tricolor face markings well-defined. Drop ears, dark brown eyes. Calm, gentle expression.',
      ]);
    }
    if ($this->mb($b, ['great pyrenees', 'pyrenean mountain'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern' => 'none',
        'size_note' => 'Great Pyrenees grow into massive, majestic white mountain dogs.',
        'adult_body_note' => 'Massive, well-balanced covered in thick white weather-resistant double coat. Weight 45–54+ kg.',
        'adult_face_note' => 'Large wedge-shaped head. Dark brown eyes with black rims. V-shaped drop ears. Regal, calm expression.',
      ]);
    }
    if ($this->mb($b, ['mastiff', 'english mastiff'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'stocky',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'brachycephalic' => true,
        'gray_pattern' => 'moderate',
        'size_note' => 'English Mastiffs are among the heaviest breeds. Growth is extreme — adults can exceed 100 kg.',
        'adult_body_note' => 'Enormous, massive, heavy. Very broad deep chest. Weight 54–100+ kg. Heavy jowls.',
        'adult_face_note' => 'Broad, wrinkled, massive head. Deep muzzle, black mask. Drop ears. Dignified, calm expression.',
      ]);
    }
    if ($this->mb($b, ['leonberger'])) {
      return array_merge($default, [
        'size_category' => 'giant',
        'body_shape' => 'athletic',
        'coat_type' => 'double_coat',
        'grows_significantly' => true,
        'size_note' => 'Leonbergers grow into giant, lion-maned, majestic dogs.',
        'adult_body_note' => 'Giant, muscular, well-proportioned. Thick lion-like mane around neck. Weight 41–75 kg.',
        'adult_face_note' => 'Elongated lion-like face. Black mask, medium muzzle. Drop ears. Gentle, friendly expression.',
      ]);
    }

    // ── MIXED / ASPIN ─────────────────────────────────────────────────
    if ($this->mb($b, ['aspin', 'askal', 'philippine', 'mixed', 'mongrel', 'mutt', 'crossbreed'])) {
      return array_merge($default, [
        'size_category' => 'medium',
        'body_shape' => 'athletic',
        'coat_type' => 'short',
        'grows_significantly' => true,
        'size_note' => 'Mixed breed dogs vary. Expect moderate growth into a lean, athletic adult.',
        'adult_body_note' => 'Lean, athletic, well-proportioned medium body. Short easy-care coat. Weight 10–25 kg.',
        'adult_face_note' => 'Defined adult muzzle, alert, intelligent expression. Features vary by mix.',
      ]);
    }

    // ── FALLBACK ──────────────────────────────────────────────────────
    return $default;
  }

  private function mb(string $breedLower, array $patterns): bool
  {
    foreach ($patterns as $pattern) {
      if (stripos($breedLower, $pattern) !== false) return true;
    }
    return false;
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  IMAGE PREPARATION
  // ─────────────────────────────────────────────────────────────────────────

  private function prepareHighQualityImage(string $fullPath): ?array
  {
    try {
      $cacheKey = 'hq_img_' . md5($fullPath);
      return Cache::remember($cacheKey, 600, function () use ($fullPath) {

        if (str_starts_with($fullPath, 'http://') || str_starts_with($fullPath, 'https://')) {
          $client        = new Client(['timeout' => 30]);
          $response      = $client->get($fullPath);
          $imageContents = $response->getBody()->getContents();
        } else {
          $imageContents = Storage::disk('object-storage')->get($fullPath);
        }

        if (empty($imageContents)) {
          throw new \Exception('Empty image file: ' . $fullPath);
        }

        $imageInfo = @getimagesizefromstring($imageContents);
        if ($imageInfo === false) {
          throw new \Exception('Unrecognized image format: ' . $fullPath);
        }

        $origWidth  = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $targetSize = 1024;

        if ($origWidth > $targetSize || $origHeight > $targetSize) {
          $imageContents = $this->resizeImage($imageContents, $targetSize);
          $resized       = @getimagesizefromstring($imageContents);
          $width         = $resized[0];
          $height        = $resized[1];
        } else {
          $width  = $origWidth;
          $height = $origHeight;
        }

        $img = @imagecreatefromstring($imageContents);
        if ($img === false) throw new \Exception('GD could not parse image');

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
    if ($source === false) throw new \Exception('GD resize: failed to create source');

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
      if ($img === false) throw new \Exception('GD could not parse output image');

      $outW = imagesx($img);
      $outH = imagesy($img);

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
