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

  private const MODEL_PRIORITY = [
    'gemini-3-pro-image-preview',
    'gemini-3.1-flash-image-preview',
    'gemini-2.5-flash-image',
    'gemini-2.0-flash-exp-image-generation',
  ];

  // ══════════════════════════════════════════════════════════════════════
  // KEY FIX #1: We UPSCALE small images before sending to Gemini.
  // A 135x180 image gives Gemini almost nothing to work with.
  // We send at least 768px (longest side), and save output at that size.
  // We do NOT shrink back to the tiny original — we keep the larger output.
  // ══════════════════════════════════════════════════════════════════════
  private const SEND_SIZE = 768;   // minimum longest side sent to Gemini
  private const MAX_SIZE  = 1024;  // maximum sent to Gemini

  protected $resultId;
  protected $breed;
  protected $imagePath;

  public function __construct($resultId, $breed, $imagePath)
  {
    $this->resultId  = $resultId;
    $this->breed     = $breed;
    $this->imagePath = $imagePath;
  }

  // ─────────────────────────────────────────────────────────────────────
  //  ENTRY POINT
  // ─────────────────────────────────────────────────────────────────────

  public function handle()
  {
    $startTime = microtime(true);
    $result    = null;

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

      // KEY FIX: use v3 cache key so stale tiny-image cache is bypassed
      $imageData = $this->prepareImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception('Failed to prepare image: ' . $this->imagePath);
      }

      Log::info("📐 Image sent to Gemini: {$imageData['sendWidth']}x{$imageData['sendHeight']} (original was {$imageData['origWidth']}x{$imageData['origHeight']})");

      $currentAgeStage = $this->detectAgeStage($imageData);
      Log::info("🔍 Detected age stage: {$currentAgeStage}");

      $breedProfile = $this->getBreedProfile($this->breed);
      $breedProfile['detected_age_stage'] = $currentAgeStage;

      $selectedModel = $this->selectBestModel();
      Log::info("🤖 Using model: {$selectedModel}");

      $simulations = $this->generateTransformations($imageData, $breedProfile, $selectedModel);

      $savedPaths = ['1_years' => null, '3_years' => null];

      if (!empty($simulations['1_year'])) {
        $savedPaths['1_years'] = $this->saveImage($simulations['1_year'], '1_year', $this->resultId);
        Log::info("✅ 1-year saved: {$savedPaths['1_years']}");
      }
      if (!empty($simulations['3_years'])) {
        $savedPaths['3_years'] = $this->saveImage($simulations['3_years'], '3_years', $this->resultId);
        Log::info("✅ 3-years saved: {$savedPaths['3_years']}");
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

  // ─────────────────────────────────────────────────────────────────────
  //  MODEL SELECTION
  // ─────────────────────────────────────────────────────────────────────

  private function selectBestModel(): string
  {
    $configured = config('services.gemini.image_model') ?? env('GEMINI_IMAGE_MODEL');
    if ($configured) return $configured;
    return self::MODEL_PRIORITY[0];
  }

  // ─────────────────────────────────────────────────────────────────────
  //  KEY FIX #2: IMPROVED AGE STAGE DETECTION
  //  The old prompt was too vague and kept returning "adult" for puppies.
  //  New prompt has very explicit puppy signals.
  // ─────────────────────────────────────────────────────────────────────

  private function detectAgeStage(array $imageData): string
  {
    try {
      $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
      $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

      $payload = [
        'contents' => [[
          'parts' => [
            [
              'text' =>
              'Study this dog photo carefully. Determine the age stage.\n\n' .
                'PUPPY SIGNALS (if you see ANY of these → it is a puppy or teenager):\n' .
                '- Head looks oversized/disproportionately large relative to body\n' .
                '- Short, stumpy, stubby legs relative to body\n' .
                '- Round, barrel/pot belly\n' .
                '- Soft, round, chubby baby face\n' .
                '- Paws look too large for the legs\n' .
                '- Thin, sparse, or fuzzy puppy coat\n' .
                '- Short muzzle relative to head\n' .
                '- Floppy soft ears larger than expected\n\n' .
                'If this dog is CLEARLY a baby/puppy (under 5 months) → reply: puppy\n' .
                'If adolescent/still growing (5-18 months) → reply: teenager\n' .
                'If young but mostly grown (1-2 years) → reply: young_adult\n' .
                'If fully mature prime (2-6 years) → reply: adult\n' .
                'If older/aging (7+ years, gray muzzle) → reply: senior\n\n' .
                'Reply with EXACTLY ONE WORD only. No explanation.',
            ],
            [
              'inlineData' => [
                'mimeType' => $imageData['mimeType'],
                'data'     => $imageData['base64'],
              ],
            ],
          ],
        ]],
        'generationConfig' => ['temperature' => 0.05, 'maxOutputTokens' => 10],
      ];

      $client   = new Client(['timeout' => 20]);
      $response = $client->post($endpoint, [
        'json'    => $payload,
        'headers' => ['Content-Type' => 'application/json'],
      ]);
      $data = json_decode($response->getBody()->getContents(), true);
      $text = trim(strtolower($data['candidates'][0]['content']['parts'][0]['text'] ?? 'adult'));

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

  // ─────────────────────────────────────────────────────────────────────
  //  PARALLEL GENERATION WITH MODEL FALLBACK
  // ─────────────────────────────────────────────────────────────────────

  private function generateTransformations(array $imageData, array $breedProfile, string $primaryModel): array
  {
    $results = ['1_year' => null, '3_years' => null];

    $prompt1Year  = $this->buildAgingPrompt($breedProfile, 1);
    $prompt3Years = $this->buildAgingPrompt($breedProfile, 3);

    $modelsToTry = array_unique(array_merge([$primaryModel], self::MODEL_PRIORITY));

    foreach ($modelsToTry as $modelName) {
      if ($results['1_year'] && $results['3_years']) break;

      Log::info("🔄 Attempting generation with: {$modelName}");

      $client      = new Client(['timeout' => 180, 'connect_timeout' => 15]);
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
              Log::warning("⚠️ {$key} failed attempt " . ($attempt + 1), [
                'reason' => $reason ? $reason->getMessage() : 'null value',
              ]);
            }
          }

          if ($results['1_year'] && $results['3_years']) break 2;
          if ($attempt < $maxAttempts - 1) sleep((int) pow(2, $attempt + 1));
        } catch (\Exception $e) {
          Log::error("Model {$modelName} attempt {$attempt} exception: " . $e->getMessage());
          if ($attempt < $maxAttempts - 1) sleep(5);
        }
      }
    }

    return $results;
  }

  // ─────────────────────────────────────────────────────────────────────
  //  GEMINI API CALL
  // ─────────────────────────────────────────────────────────────────────

  private function createGenerationPromise(Client $client, string $prompt, array $imageData, string $modelName)
  {
    $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $isThinkingModel = in_array($modelName, [
      'gemini-3-pro-image-preview',
      'gemini-3.1-flash-image-preview',
      'gemini-2.5-flash-image',
    ]);

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
        'temperature'        => $isThinkingModel ? 0.15 : 0.25,
        'topK'               => $isThinkingModel ? 32 : 40,
        'topP'               => $isThinkingModel ? 0.75 : 0.80,
        'maxOutputTokens'    => 32768,
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
    ])->then(function ($response) use ($modelName) {
      return $this->extractImage($response, $modelName);
    });
  }

  // ─────────────────────────────────────────────────────────────────────
  //  EXTRACT IMAGE FROM RESPONSE
  // ─────────────────────────────────────────────────────────────────────

  private function extractImage($response, string $modelName = ''): ?string
  {
    $body         = $response->getBody()->getContents();
    $responseData = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON from Gemini ({$modelName})");
    }
    if (isset($responseData['error'])) {
      $msg  = $responseData['error']['message'] ?? 'Unknown API error';
      $code = $responseData['error']['code']    ?? 0;
      throw new \Exception("Gemini API error [{$code}] ({$modelName}): {$msg}");
    }
    if (!isset($responseData['candidates'][0])) {
      throw new \Exception("No candidates from {$modelName}");
    }

    $candidate    = $responseData['candidates'][0];
    $finishReason = $candidate['finishReason'] ?? '';

    if (in_array($finishReason, ['SAFETY', 'RECITATION', 'OTHER', 'PROHIBITED_CONTENT'])) {
      throw new \Exception("Blocked by {$modelName}: {$finishReason}");
    }

    $parts = $candidate['content']['parts'] ?? [];

    foreach ($parts as $part) {
      if (isset($part['inlineData']['data']) && strlen($part['inlineData']['data']) > 200) {
        $decoded = base64_decode($part['inlineData']['data'], true);
        if ($decoded && strlen($decoded) > 2000) {
          Log::info("✅ Image from inlineData ({$modelName}) " . round(strlen($decoded) / 1024, 1) . ' KB');
          return $decoded;
        }
      }
    }

    foreach ($parts as $part) {
      if (isset($part['text'])) {
        $text    = trim(preg_replace('/```[\w]*\n?/', '', $part['text']));
        $decoded = base64_decode($text, true);
        if ($decoded && strlen($decoded) > 5000) {
          Log::info("✅ Image from text block ({$modelName}) " . round(strlen($decoded) / 1024, 1) . ' KB');
          return $decoded;
        }
      }
    }

    throw new \Exception("No usable image data from {$modelName}");
  }

  // ─────────────────────────────────────────────────────────────────────
  //  PROMPT BUILDER — STRONG, EXPLICIT, BREED + AGE AWARE
  // ─────────────────────────────────────────────────────────────────────

  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed       = $profile['breed'];
    $size        = $profile['size_category'];
    $coat        = $profile['coat_type'];
    $isBrachy    = $profile['brachycephalic'];
    $bodyShape   = $profile['body_shape'] ?? 'standard';
    $sizeNote    = $profile['size_note'] ?? '';
    $adultBody   = $profile['adult_body_note'] ?? '';
    $adultFace   = $profile['adult_face_note'] ?? '';
    $detectedAge = $profile['detected_age_stage'] ?? 'adult';
    $aging1yr    = $profile['aging_1yr'] ?? '';
    $aging3yr    = $profile['aging_3yr'] ?? '';

    $L = [];

    $L[] = '╔═══════════════════════════════════════════════════════════════╗';
    $L[] = '║  PRODUCE A CLEARLY AND OBVIOUSLY AGED VERSION OF THIS DOG.    ║';
    $L[] = '║  THE AGING MUST BE UNMISTAKABLE AND DRAMATIC.                 ║';
    $L[] = '╚═══════════════════════════════════════════════════════════════╝';
    $L[] = '';
    $L[] = "BREED: {$breed}";
    $L[] = "CURRENT AGE STAGE (detected): {$detectedAge}";
    $L[] = "TASK: Show what this EXACT dog looks like +{$targetYears} year(s) from now.";
    $L[] = '';
    $L[] = '🚨 FAILURE DEFINITION: If the output looks nearly identical to the input,';
    $L[] = '   you have FAILED. A viewer must IMMEDIATELY see the dog is older.';
    $L[] = '';

    // ── SCENE LOCK ────────────────────────────────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 1 — FREEZE THESE (do NOT change them):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  • Background, floor, all objects in scene';
    $L[] = '  • Camera angle, zoom, crop, framing';
    $L[] = '  • Lighting, shadows, highlights';
    $L[] = '  • Pose: exact paw positions, body orientation, head angle';
    $L[] = '  • Any humans/hands in the photo';
    $L[] = '🚫 Do NOT move limbs, zoom in/out, or change the scene.';
    $L[] = '';

    // ── MANDATORY AGING ───────────────────────────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "PHASE 2 — MANDATORY AGING CHANGES (+{$targetYears} year(s)):";
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '⚠️  ALL of these changes MUST be clearly and obviously visible.';
    $L[] = '';

    if ($targetYears === 1) {
      if ($aging1yr) {
        $L[] = $aging1yr;
        $L[] = '';
      }
      switch ($detectedAge) {
        case 'puppy':
          $L[] = '╔═══════════════════════════════════════════════════════════════╗';
          $L[] = '║  CURRENT: PUPPY (baby dog). TARGET: TEENAGER / YOUNG ADULT    ║';
          $L[] = '║  THIS IS THE BIGGEST GROWTH PHASE. BE VERY DRAMATIC.          ║';
          $L[] = '╚═══════════════════════════════════════════════════════════════╝';
          $L[] = '';
          $L[] = '🔴 BODY — every item must be clearly visible:';
          $L[] = '  ✦ LEGS: Dramatically LONGER. The short stubby baby legs grow into';
          $L[] = '    long lean adolescent legs. This is the #1 most obvious body change.';
          $L[] = '    The leg length difference must be UNMISTAKABLE.';
          $L[] = '  ✦ OVERALL SIZE: Dog is significantly BIGGER — clearly larger animal';
          $L[] = '  ✦ BELLY: Round puppy potbelly is GONE — flat, tucked belly';
          $L[] = '  ✦ CHEST: Deeper and more defined';
          $L[] = '  ✦ NECK: Longer, more defined';
          $L[] = '';
          $L[] = '🔴 FACE — every item must be clearly visible:';
          $L[] = '  ✦ MUZZLE: Much LONGER and more pointed — the baby muzzle is short,';
          $L[] = '    the 1-year muzzle is dramatically longer and more defined.';
          $L[] = '    This is the #1 most obvious face change.';
          $L[] = '  ✦ HEAD: Now proportionate to body — no longer oversized';
          $L[] = '  ✦ FACE: More angular — soft baby roundness replaced by defined structure';
          $L[] = '  ✦ EYES: Alert, less wide-eyed baby look';
          $L[] = '';
          $L[] = '🔴 COAT:';
          $L[] = "  ✦ Replace thin fuzzy puppy coat with developing adult coat ({$coat})";
          $L[] = '  ✦ Noticeably thicker and more defined';
          break;

        case 'teenager':
          $L[] = 'CURRENT: TEENAGER → TARGET: YOUNG ADULT';
          $L[] = '  ✦ Body fills out — broader chest, more muscular shoulders';
          $L[] = '  ✦ No more gangly look — proportions balanced out';
          $L[] = '  ✦ More defined, square adult muzzle';
          $L[] = "  ✦ Adult coat density ({$coat})";
          break;

        case 'young_adult':
          $L[] = 'CURRENT: YOUNG ADULT → TARGET: PRIME ADULT';
          $L[] = '  ✦ Chest clearly deeper and barrel-shaped';
          $L[] = '  ✦ Shoulders visibly broader and more muscular';
          $L[] = '  ✦ Bone structure more chiseled — this dog is at its physical PEAK';
          $L[] = "  ✦ Coat at absolute peak richness ({$coat})";
          break;

        default:
          $L[] = 'CURRENT: ADULT → TARGET: MATURE ADULT (+1yr)';
          $L[] = '  ✦ Chest clearly broader, shoulders more muscular';
          $L[] = '  ✦ Dog appears 5–10% heavier and more substantial';
          $L[] = '  ✦ Face: more chiseled cheekbones, stronger muzzle';
          $L[] = '  ✦ Expression: more settled and confident';
          $L[] = "  ✦ Coat richer and denser ({$coat})";
          break;
      }
    } else {
      if ($aging3yr) {
        $L[] = $aging3yr;
        $L[] = '';
      }
      switch ($detectedAge) {
        case 'puppy':
        case 'teenager':
          $L[] = '╔═══════════════════════════════════════════════════════════════╗';
          $L[] = '║  CURRENT: PUPPY/TEEN. TARGET: FULLY GROWN 3-YEAR-OLD ADULT   ║';
          $L[] = '║  COMPLETE TRANSFORMATION REQUIRED. Very dramatic.             ║';
          $L[] = '╚═══════════════════════════════════════════════════════════════╝';
          $L[] = '';
          $L[] = '🔴 BODY — complete adult transformation:';
          $L[] = '  ✦ FULL ADULT SIZE: Dog is now completely grown — this is an';
          $L[] = '    enormous difference. A 3-year-old dog vs a puppy is a';
          $L[] = '    completely different looking animal.';
          $L[] = '  ✦ LEGS: Fully grown adult legs — long, lean, strong';
          $L[] = '  ✦ CHEST: Full deep adult chest — no more barrel puppy shape';
          $L[] = '  ✦ MUSCLE: Full adult muscle development throughout';
          $L[] = '  ✦ BELLY: Flat and tucked — ZERO puppy belly remains';
          $L[] = '';
          $L[] = '🔴 FACE — complete adult transformation:';
          $L[] = '  ✦ MUZZLE: Full adult length — this is the BIGGEST facial change.';
          $L[] = '    The adult muzzle is 2-3× longer than the puppy muzzle.';
          $L[] = '  ✦ HEAD: Properly proportioned — no longer oversized';
          $L[] = '  ✦ FACE: Strong, defined bone structure — sharp cheekbones';
          $L[] = '  ✦ EXPRESSION: Confident, calm, fully mature adult';
          $L[] = '';
          $L[] = '🔴 COAT:';
          $L[] = "  ✦ Full beautiful adult coat ({$coat}) — completely replaces puppy fuzz";
          break;

        case 'young_adult':
        default:
          $L[] = 'CURRENT: ADULT → TARGET: MATURE/DISTINGUISHED ADULT (+3yrs)';
          $L[] = '';
          $L[] = '╔═══════════════════════════════════════════════════════════════╗';
          $L[] = '║  MUZZLE GRAYING — MANDATORY. NON-NEGOTIABLE.                 ║';
          $L[] = '║  Clear gray/white/silver hairs on muzzle/chin MUST be visible ║';
          $L[] = '║  If graying is missing = FAILED. Regenerate.                 ║';
          $L[] = '╚═══════════════════════════════════════════════════════════════╝';
          $L[] = '';
          $L[] = '  ' . $this->grayChange3Years($profile);
          $L[] = '';
          $L[] = '🔴 BODY:';
          $L[] = '  ✦ Noticeably heavier and more substantial overall';
          $L[] = '  ✦ Chest broader and more barrel-like';
          $L[] = '  ✦ Slightly less taut muscle — settled mature bulk';
          $L[] = '';
          $L[] = '🔴 FACE:';
          $L[] = '  ✦ Deeper wrinkles/folds';
          $L[] = '  ✦ Deeper-set, wiser eyes';
          $L[] = '  ✦ Expression: calm, experienced, dignified — this dog has LIVED';
          break;
      }
    }

    $L[] = '';

    // ── BREED BIOLOGY ─────────────────────────────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "PHASE 3 — BREED BIOLOGY (never violate these):";
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "  Size: {$size} | Body: {$bodyShape}";
    $L[] = "  {$sizeNote}";
    $L[] = "  Adult body: {$adultBody}";
    $L[] = "  Adult face: {$adultFace}";

    if ($bodyShape === 'long_low') {
      $L[] = '🔴 LONG-AND-LOW: Legs NEVER grow taller. Body grows heavier/longer only.';
    }
    if ($bodyShape === 'sighthound') {
      $L[] = '🔴 SIGHTHOUND: Do NOT add bulk. Stays slender and lean.';
    }
    if ($isBrachy) {
      $L[] = '🔴 BRACHYCEPHALIC: NEVER elongate muzzle. Flat face is PERMANENT.';
      $L[] = '   Aging = deeper wrinkles, heavier jowls. NOT muzzle length.';
    }

    $L[] = '';
    $L[] = "🔴 COAT = {$coat}: ";
    switch ($coat) {
      case 'curly/fluffy':
        $L[] = '   Curls MUST remain. Denser with age. Never straighten.';
        break;
      case 'double_coat':
        $L[] = '   Becomes THICKER and FULLER. Never single-layer.';
        break;
      case 'long_silky':
        $L[] = '   Grows LONGER and LUSHER. Never shorter or curlier.';
        break;
      case 'wire':
        $L[] = '   Wiry texture MORE bristly. Never soften.';
        break;
      case 'short':
        $L[] = '   Becomes GLOSSIER and DENSER. Never grow it out.';
        break;
    }
    $L[] = '';

    // ── REALISM ───────────────────────────────────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 4 — REALISM (must be preserved):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  • Coat color, markings, patches — identical to input';
    $L[] = '  • Eye color — identical to input';
    $L[] = '  • Nose color — identical to input';
    $L[] = '  • Photorealistic — NOT illustration or art style';
    $L[] = '  • Dog looks healthy and happy';
    $L[] = '';

    // ── FINAL GATE ────────────────────────────────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 5 — FINAL CHECK before outputting:';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "Would a stranger IMMEDIATELY and CONFIDENTLY say the output";
    $L[] = "looks +{$targetYears} year(s) older than the input?";
    $L[] = "YES → output.  NO / MAYBE → regenerate with STRONGER effects.";

    if (in_array($detectedAge, ['puppy', 'teenager'])) {
      $L[] = '';
      $L[] = '🚨 PUPPY CHECK: Is the dog now CLEARLY bigger, with a MUCH longer';
      $L[] = '   muzzle and longer legs? If it still looks like a puppy → REGENERATE.';
    }

    $L[] = '';
    $L[] = '╔═══════════════════════════════════════════════════════════════╗';
    $L[] = '║  OUTPUT: Same dog. Same scene. Same pose. OBVIOUSLY older.    ║';
    $L[] = '╚═══════════════════════════════════════════════════════════════╝';

    return implode("\n", $L);
  }

  // ─────────────────────────────────────────────────────────────────────
  //  HELPERS
  // ─────────────────────────────────────────────────────────────────────

  private function grayChange3Years(array $profile): string
  {
    return match ($profile['gray_pattern'] ?? 'moderate') {
      'none'      => 'NO graying — this breed does not gray visibly at 3 years.',
      'minimal'   => '🔴 MINIMAL graying: a few silver/gray hairs on muzzle tip only.',
      'moderate'  => '🔴 MODERATE GRAYING (MANDATORY): Clear gray/silver on muzzle tip,
         chin, around nostrils, faint silver around eyes. MUST be obvious.',
      'prominent' => '🔴 PROMINENT GRAYING (MANDATORY): Strong silver/gray covering entire
         muzzle, chin, and around eyes. Unmistakable sign of maturity.',
      default     => '🔴 Visible muzzle-tip graying — clearly noticeable.',
    };
  }

  // ─────────────────────────────────────────────────────────────────────
  //  KEY FIX #1 + #3: IMAGE PREPARATION — UPSCALE SMALL IMAGES
  //  Old code only downscaled. A 135×180 image stayed at 135×180.
  //  New code UPSCALES anything below SEND_SIZE (768px).
  //  Also uses new cache key 'hq_img_v3_' so old tiny-image cache is bypassed.
  // ─────────────────────────────────────────────────────────────────────

  private function prepareImage(string $fullPath): ?array
  {
    try {
      // v3 cache key — invalidates old v1/v2 cached tiny images
      $cacheKey = 'hq_img_v3_' . md5($fullPath);

      return Cache::remember($cacheKey, 600, function () use ($fullPath) {

        if (str_starts_with($fullPath, 'http://') || str_starts_with($fullPath, 'https://')) {
          $client        = new Client(['timeout' => 30]);
          $imageContents = $client->get($fullPath)->getBody()->getContents();
        } else {
          $imageContents = Storage::disk('object-storage')->get($fullPath);
        }

        if (empty($imageContents)) {
          throw new \Exception('Empty image: ' . $fullPath);
        }

        $info = @getimagesizefromstring($imageContents);
        if (!$info) throw new \Exception('Cannot parse image: ' . $fullPath);

        $origW = $info[0];
        $origH = $info[1];
        $longest = max($origW, $origH);

        // Scale so longest side lands in [SEND_SIZE, MAX_SIZE]
        if ($longest < self::SEND_SIZE) {
          // UPSCALE — tiny image, Gemini needs more pixels to edit
          $targetSize    = self::SEND_SIZE;
          $imageContents = $this->scaleImage($imageContents, $targetSize);
          Log::info("📐 Upscaled {$origW}x{$origH} → longest side {$targetSize}px");
        } elseif ($longest > self::MAX_SIZE) {
          // DOWNSCALE — too large
          $targetSize    = self::MAX_SIZE;
          $imageContents = $this->scaleImage($imageContents, $targetSize);
          Log::info("📐 Downscaled {$origW}x{$origH} → longest side {$targetSize}px");
        }
        // else: already in good range, leave as-is

        // Get dimensions after any scaling
        $scaledInfo = @getimagesizefromstring($imageContents);
        $sendW = $scaledInfo ? $scaledInfo[0] : $origW;
        $sendH = $scaledInfo ? $scaledInfo[1] : $origH;

        // Convert to JPEG
        $img = @imagecreatefromstring($imageContents);
        if (!$img) throw new \Exception('GD cannot parse image');
        ob_start();
        imagejpeg($img, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        Log::info("✅ Image ready: {$sendW}x{$sendH} (orig {$origW}x{$origH}) — " . round(strlen($jpeg) / 1024, 1) . ' KB');

        return [
          'base64'      => base64_encode($jpeg),
          'mimeType'    => 'image/jpeg',
          'sendWidth'   => $sendW,
          'sendHeight'  => $sendH,
          'origWidth'   => $origW,
          'origHeight'  => $origH,
          // Save outputs at sendWidth/sendHeight, NOT the tiny original
          'saveWidth'   => $sendW,
          'saveHeight'  => $sendH,
        ];
      });
    } catch (\Exception $e) {
      Log::error('prepareImage failed: ' . $e->getMessage());
      return null;
    }
  }

  private function scaleImage(string $imageContents, int $targetLongestSide): string
  {
    $src = @imagecreatefromstring($imageContents);
    if (!$src) throw new \Exception('GD scaleImage: cannot create source');

    $w     = imagesx($src);
    $h     = imagesy($src);
    $ratio = $targetLongestSide / max($w, $h);
    $newW  = max(1, (int) round($w * $ratio));
    $newH  = max(1, (int) round($h * $ratio));

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    ob_start();
    imagejpeg($dst, null, 92);
    $out = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);
    return $out;
  }

  // ─────────────────────────────────────────────────────────────────────
  //  KEY FIX #3: SAVE IMAGE — keep at Gemini's output size, NOT tiny original
  //  Old code resized output BACK to 135×180, destroying all transformation detail.
  //  New code saves at whatever Gemini returned (typically 768–1024px).
  // ─────────────────────────────────────────────────────────────────────

  private function saveImage(string $imageOutput, string $type, $resultId): ?string
  {
    try {
      $img = @imagecreatefromstring($imageOutput);
      if (!$img) throw new \Exception('GD cannot parse output image');

      // Do NOT resize — keep Gemini's full-quality output as-is
      $outW = imagesx($img);
      $outH = imagesy($img);

      ob_start();
      imagewebp($img, null, 90);
      $webp = ob_get_clean();
      imagedestroy($img);

      $filename = "transform_{$resultId}_{$type}_" . time() . '.webp';
      $path     = "simulations/{$filename}";
      Storage::disk('object-storage')->put($path, $webp);

      Log::info("💾 Saved {$type}: {$outW}x{$outH} — {$path} (" . round(strlen($webp) / 1024, 1) . ' KB)');
      return $path;
    } catch (\Exception $e) {
      Log::error('saveImage failed: ' . $e->getMessage(), ['type' => $type]);
      return null;
    }
  }

  // ─────────────────────────────────────────────────────────────────────
  //  BREED PROFILES
  // ─────────────────────────────────────────────────────────────────────

  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    $default = [
      'breed'          => $breed,
      'size_category'  => 'medium',
      'body_shape'     => 'standard',
      'coat_type'      => 'short',
      'gray_pattern'   => 'moderate',
      'brachycephalic' => false,
      'size_note'      => 'Moderate size increase from puppy to adult.',
      'adult_body_note' => 'Well-proportioned adult body. Deeper chest, longer legs, healthy muscle.',
      'adult_face_note' => 'Defined adult muzzle, proportionate head, alert expression.',
      'aging_1yr'      => '◆ +1 YEAR CHANGES (must all be clearly visible):
  ✦ Noticeably more muscular chest and shoulders
  ✦ Face: more defined cheekbones and stronger muzzle
  ✦ Expression: calmer, more settled, confident
  ✦ Coat richer and denser
  ✦ Slightly heavier overall frame',
      'aging_3yr'      => '◆ +3 YEAR CHANGES (must all be clearly visible):
  ✦ MUZZLE GRAYING: clear gray/white hairs on muzzle tip and chin
  ✦ Body noticeably heavier and more substantial
  ✦ Face: deeper wrinkles, more pronounced features
  ✦ Deeper-set, wiser eyes
  ✦ Expression: calm, dignified, experienced',
    ];

    // ── ASPIN / PHILIPPINE MIXED BREED ───────────────────────────────
    if ($this->mb($b, ['aspin', 'askal', 'philippine', 'mixed', 'mongrel', 'mutt', 'crossbreed'])) {
      return array_merge($default, [
        'size_category'  => 'medium',
        'body_shape'     => 'athletic',
        'coat_type'      => 'short',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Aspins grow significantly — typical adult is lean, athletic, medium-sized.',
        'adult_body_note' => 'Lean, athletic, medium body. Long legs. Deep narrow chest. Weight 10–25 kg.',
        'adult_face_note' => 'Medium-length tapered muzzle (much longer than puppy muzzle). Almond eyes. Alert expression.',
        'aging_1yr'      => '◆ ASPIN +1 YEAR — ALL must be clearly visible:
  ✦ LEGS: Dramatically LONGER — the #1 change. Short puppy legs → long lean adult legs.
    The leg length difference must be OBVIOUS and UNMISTAKABLE.
  ✦ BODY: Significantly bigger — lean, athletic young adult body
  ✦ MUZZLE: CLEARLY much longer — adult Aspin muzzle is much longer than baby muzzle
  ✦ HEAD: Proportionate to body — no longer oversized
  ✦ BELLY: Flat and tucked — round puppy belly is GONE
  ✦ CHEST: Narrower and deeper — athletic narrow chest
  ✦ COAT: Sleek, smooth short coat — no more baby fuzz
  ✦ EXPRESSION: Alert, confident adolescent',
        'aging_3yr'      => '◆ ASPIN +3 YEARS — ALL must be clearly visible:
  ✦ FULL ADULT: Completely grown 3-year-old lean athletic Aspin
  ✦ BODY: Full adult size — dramatically larger than puppy
  ✦ LEGS: Fully adult legs — long, lean, strong — nothing like stubby puppy legs
  ✦ MUZZLE: Full adult length — the biggest facial transformation from puppy
  ✦ HEAD: Adult-proportioned with defined cheekbones
  ✦ GRAY: First few gray/silver hairs beginning on muzzle tip
  ✦ COAT: Sleek, smooth, glossy adult coat
  ✦ EXPRESSION: Calm, confident, settled adult Aspin',
      ]);
    }

    // ── FRENCH BULLDOG ────────────────────────────────────────────────
    if ($this->mb($b, ['french bulldog', 'frenchie', 'french bull'])) {
      return array_merge($default, [
        'size_category'  => 'small',
        'body_shape'     => 'stocky',
        'coat_type'      => 'short',
        'brachycephalic' => true,
        'gray_pattern'   => 'minimal',
        'size_note'      => 'French Bulldogs stay small. They get heavier/more muscular but NOT taller.',
        'adult_body_note' => 'Heavy, muscular, compact. Very wide shoulders and chest. Weight 9–13 kg.',
        'adult_face_note' => 'Flat face with deep wrinkles. Massive square head. Bat-like erect ears. Heavy jowls.',
        'aging_1yr'      => '◆ FRENCH BULLDOG +1 YEAR — ALL must be clearly visible:
  ✦ Chest and shoulders NOTICEABLY broader and more muscular
  ✦ Facial wrinkles/folds CLEARLY deeper (nose roll, forehead)
  ✦ Jowls heavier, more pendulous
  ✦ Head appears even broader and more square
  ✦ Coat glossier and shinier',
        'aging_3yr'      => '◆ FRENCH BULLDOG +3 YEARS — ALL must be clearly visible:
  ✦ MUZZLE GRAYING: obvious white/gray hairs on muzzle tip, around nostrils, chin
  ✦ Forehead wrinkles dramatically deeper and more numerous
  ✦ Nose roll deeper and more prominent
  ✦ Jowl folds heavier, more pendulous, deeply creased
  ✦ Body 10–15% more massive
  ✦ Expression: calm, settled, distinguished — mature Frenchie dignity',
      ]);
    }

    // ── LABRADOR ─────────────────────────────────────────────────────
    if ($this->mb($b, ['labrador', 'lab'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'athletic',
        'coat_type'      => 'short',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Labradors grow dramatically — much taller, heavier, broader.',
        'adult_body_note' => 'Broad, powerful, strongly built. Wide head, deep chest. Weight 25–36 kg.',
        'adult_face_note' => 'Broad clean-cut head. Wide powerful muzzle. Kind intelligent eyes. Drop ears.',
        'aging_1yr'      => '◆ LABRADOR +1 YEAR: Much bigger body, broader chest, glossier coat',
        'aging_3yr'      => '◆ LABRADOR +3 YEARS: Clear muzzle/chin graying, heavier settled body, wise mature Lab expression',
      ]);
    }

    // ── GOLDEN RETRIEVER ─────────────────────────────────────────────
    if ($this->mb($b, ['golden retriever'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'athletic',
        'coat_type'      => 'long_silky',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Golden Retrievers grow into large, feathered dogs.',
        'adult_body_note' => 'Large, well-balanced, powerful. Deep chest, flowing golden coat. 25–34 kg.',
        'adult_face_note' => 'Broad skull. Gentle intelligent expression. Drop ears with feathering.',
        'aging_1yr'      => '◆ GOLDEN +1 YEAR: Coat dramatically longer and flowing, body larger/muscular',
        'aging_3yr'      => '◆ GOLDEN +3 YEARS: Clear gray on muzzle/chin/nose, full flowing coat, heavier settled body',
      ]);
    }

    // ── GERMAN SHEPHERD ──────────────────────────────────────────────
    if ($this->mb($b, ['german shepherd', 'alsatian'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'athletic',
        'coat_type'      => 'double_coat',
        'gray_pattern'   => 'prominent',
        'size_note'      => 'German Shepherds grow dramatically.',
        'adult_body_note' => 'Strong, muscular. Deep chest, sloping back. Weight 22–40 kg.',
        'adult_face_note' => 'Strong wedge-shaped head. SIGNATURE: fully erect pointed ears.',
        'aging_1yr'      => '◆ GSD +1 YEAR: Much larger powerful body, fully erect ears, thick double coat',
        'aging_3yr'      => '◆ GSD +3 YEARS: Prominent muzzle/chin gray, very thick double coat, dignified expression',
      ]);
    }

    // ── BEAGLE ───────────────────────────────────────────────────────
    if ($this->mb($b, ['beagle'])) {
      return array_merge($default, [
        'size_category'  => 'small',
        'body_shape'     => 'stocky',
        'coat_type'      => 'short',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Beagles grow into compact, sturdy hounds.',
        'adult_body_note' => 'Solid, muscular, compact. Deep chest. Weight 9–11 kg.',
        'adult_face_note' => 'Long square muzzle, long floppy ears, large brown eyes.',
        'aging_1yr'      => '◆ BEAGLE +1 YEAR: More muscular body, more square defined muzzle, glossier coat',
        'aging_3yr'      => '◆ BEAGLE +3 YEARS: Clear gray/white on muzzle/chin, heavier body, classic hound wisdom',
      ]);
    }

    // ── CORGI ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      return array_merge($default, [
        'size_category'  => 'small',
        'body_shape'     => 'long_low',
        'coat_type'      => 'double_coat',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Corgis are long-and-low. NEVER grow tall. Body grows heavier only.',
        'adult_body_note' => 'Long body, very short legs, deep chest, muscular hindquarters.',
        'adult_face_note' => 'Fox-like face. Large fully erect pointed ears.',
        'aging_1yr'      => '◆ CORGI +1 YEAR: More muscular, thicker double coat, fox face more defined, ears very prominent',
        'aging_3yr'      => '◆ CORGI +3 YEARS: Muzzle gray, denser fuller coat, broader body',
      ]);
    }

    // ── POMERANIAN ────────────────────────────────────────────────────
    if ($this->mb($b, ['pomeranian'])) {
      return array_merge($default, [
        'size_category'  => 'toy',
        'body_shape'     => 'compact',
        'coat_type'      => 'curly/fluffy',
        'gray_pattern'   => 'minimal',
        'size_note'      => 'Pomeranians stay tiny. The puff-ball coat develops fully.',
        'adult_body_note' => 'Tiny compact body under a massive rounded double coat.',
        'adult_face_note' => 'Fox-like face. Small erect triangular ears. Thick lion mane.',
        'aging_1yr'      => '◆ POMERANIAN +1 YEAR: DRAMATICALLY bigger puff coat, thick lion mane, sharper fox face',
        'aging_3yr'      => '◆ POMERANIAN +3 YEARS: Coat at maximum fullness, faint muzzle tip gray, confident settled expression',
      ]);
    }

    // ── SHIBA INU ─────────────────────────────────────────────────────
    if ($this->mb($b, ['shiba inu', 'shiba'])) {
      return array_merge($default, [
        'size_category'  => 'small',
        'body_shape'     => 'compact',
        'coat_type'      => 'double_coat',
        'gray_pattern'   => 'minimal',
        'size_note'      => 'Shiba Inus grow into compact fox-like dogs.',
        'adult_body_note' => 'Compact, well-muscled, agile. Thick double coat. 8–11 kg.',
        'adult_face_note' => 'Fox-like triangular head, small erect ears, small almond eyes.',
        'aging_1yr'      => '◆ SHIBA +1 YEAR: Thicker richer double coat, sharper fox face, more muscular',
        'aging_3yr'      => '◆ SHIBA +3 YEARS: Coat at peak, faint muzzle-tip gray, dignified calm expression',
      ]);
    }

    // ── CHIHUAHUA ─────────────────────────────────────────────────────
    if ($this->mb($b, ['chihuahua'])) {
      return array_merge($default, [
        'size_category'  => 'toy',
        'body_shape'     => 'compact',
        'coat_type'      => 'short',
        'size_note'      => 'Chihuahuas stay tiny. Minimal size change.',
        'adult_body_note' => 'Compact, fine-boned tiny body. 1.5–3 kg.',
        'adult_face_note' => 'Large rounded apple-dome skull. Large erect ears. Large luminous eyes.',
        'aging_1yr'      => '◆ CHIHUAHUA +1 YEAR: More defined bone structure, adult expression, glossier coat',
        'aging_3yr'      => '◆ CHIHUAHUA +3 YEARS: Well-defined adult cheekbones, confident mature expression',
      ]);
    }

    // ── BULLDOG ───────────────────────────────────────────────────────
    if ($this->mb($b, ['bulldog', 'english bulldog'])) {
      return array_merge($default, [
        'size_category'  => 'medium',
        'body_shape'     => 'stocky',
        'coat_type'      => 'short',
        'brachycephalic' => true,
        'gray_pattern'   => 'minimal',
        'size_note'      => 'Bulldogs get heavier/more wrinkled but NOT taller.',
        'adult_body_note' => 'Extremely wide, heavy, low-slung. Massive chest. Weight 22–25 kg.',
        'adult_face_note' => 'Massive wrinkled face, flat nose, underbite, huge jowls.',
        'aging_1yr'      => '◆ BULLDOG +1 YEAR: Chest dramatically broader, wrinkles deeper, jowls heavier',
        'aging_3yr'      => '◆ BULLDOG +3 YEARS: Very heavy wrinkled body, deeply creased face, faint gray on chin',
      ]);
    }

    // ── PUG ──────────────────────────────────────────────────────────
    if ($this->mb($b, ['pug'])) {
      return array_merge($default, [
        'size_category'  => 'small',
        'body_shape'     => 'stocky',
        'coat_type'      => 'short',
        'brachycephalic' => true,
        'gray_pattern'   => 'minimal',
        'size_note'      => 'Pugs stay small and round. They get heavier and rounder.',
        'adult_body_note' => 'Cobby, round, compact. Weight 6–9 kg.',
        'adult_face_note' => 'Massive round head, very flat face, deep wrinkles, bulging eyes.',
        'aging_1yr'      => '◆ PUG +1 YEAR: Body rounder/heavier, face wrinkles deeper, jowls heavier',
        'aging_3yr'      => '◆ PUG +3 YEARS: Substantially heavier round body, very deep wrinkles, faint muzzle/chin gray',
      ]);
    }

    // ── HUSKY ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['siberian husky', 'husky'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'athletic',
        'coat_type'      => 'double_coat',
        'gray_pattern'   => 'none',
        'size_note'      => 'Huskies grow into medium-large athletic dogs.',
        'adult_body_note' => 'Medium-large, athletic, thick double coat. 16–27 kg.',
        'adult_face_note' => 'Chiseled head. Almond eyes. Erect ears.',
        'aging_1yr'      => '◆ HUSKY +1 YEAR: Much bigger, DRAMATICALLY thicker double coat, vivid markings',
        'aging_3yr'      => '◆ HUSKY +3 YEARS: Coat at peak thickness and luster, powerful body, no graying',
      ]);
    }

    // ── ROTTWEILER ────────────────────────────────────────────────────
    if ($this->mb($b, ['rottweiler'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'stocky',
        'coat_type'      => 'short',
        'gray_pattern'   => 'minimal',
        'size_note'      => 'Rottweilers grow dramatically. Very dramatic size increase.',
        'adult_body_note' => 'Massive, powerful, compact. Heavy bone, deep broad chest. 35–60 kg.',
        'adult_face_note' => 'Broad powerful head. Strong wide muzzle. Calm confident expression.',
        'aging_1yr'      => '◆ ROTTWEILER +1 YEAR: Dramatically bigger, massive chest/shoulders, sharp black-and-tan',
        'aging_3yr'      => '◆ ROTTWEILER +3 YEARS: Very massive at peak size, heavier jowls, faint muzzle gray, authoritative expression',
      ]);
    }

    // ── GERMAN SHEPHERD ──────────────────────────────────────────────
    if ($this->mb($b, ['german shepherd', 'german sheperd', 'alsatian', 'gsd'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'athletic',
        'coat_type'      => 'double_coat',
        'gray_pattern'   => 'prominent',
        'size_note'      => 'GSDs grow dramatically — much taller, broader chest.',
        'adult_body_note' => 'Strong, muscular, slightly longer than tall. Deep chest, sloping back. 22–40 kg.',
        'adult_face_note' => 'Strong wedge-shaped head. FULLY ERECT pointed ears. Alert intelligent expression.',
        'aging_1yr'      => '◆ GSD +1 YEAR: Much larger powerful body, fully erect prominent ears, thick double coat',
        'aging_3yr'      => '◆ GSD +3 YEARS: Prominent gray/silver muzzle, very thick double coat, dignified mature expression',
      ]);
    }

    // ── BORDER COLLIE ─────────────────────────────────────────────────
    if ($this->mb($b, ['border collie'])) {
      return array_merge($default, [
        'size_category'  => 'medium',
        'body_shape'     => 'athletic',
        'coat_type'      => 'double_coat',
        'gray_pattern'   => 'prominent',
        'size_note'      => 'Border Collies grow into lean, athletic medium dogs.',
        'adult_body_note' => 'Athletic, lithe, graceful. Lean muscle. 14–20 kg.',
        'adult_face_note' => 'SIGNATURE: intense focused expression. Semi-erect forward-tipping ears.',
        'aging_1yr'      => '◆ BORDER COLLIE +1 YEAR: Lean athletic body, rich thick double coat, intense adult gaze',
        'aging_3yr'      => '◆ BORDER COLLIE +3 YEARS: Noticeable muzzle gray/silver, prime athletic peak, wise experienced expression',
      ]);
    }

    // ── GREAT DANE ────────────────────────────────────────────────────
    if ($this->mb($b, ['great dane'])) {
      return array_merge($default, [
        'size_category'  => 'giant',
        'body_shape'     => 'athletic',
        'coat_type'      => 'short',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Great Danes: EXTREME growth. Among tallest dogs alive.',
        'adult_body_note' => 'Enormous, powerful, elegant. Very long legs, deep massive chest. 50–90 kg.',
        'adult_face_note' => 'Large rectangular head. Strong muzzle. Noble expression.',
        'aging_1yr'      => '◆ GREAT DANE +1 YEAR: DRAMATICALLY taller and larger, massive deep chest, noble bearing',
        'aging_3yr'      => '◆ GREAT DANE +3 YEARS: Full giant size, visible muzzle graying, majestic dignified expression',
      ]);
    }

    // ── BERNESE MOUNTAIN DOG ──────────────────────────────────────────
    if ($this->mb($b, ['bernese mountain dog', 'bernese', 'berner'])) {
      return array_merge($default, [
        'size_category'  => 'giant',
        'body_shape'     => 'stocky',
        'coat_type'      => 'double_coat',
        'size_note'      => 'Bernese grow into large, heavy tri-colored mountain dogs.',
        'adult_body_note' => 'Large, heavy, sturdy. Tricolor coat (black, white, rust). 36–55 kg.',
        'adult_face_note' => 'Broad flat skull. Tricolor markings. Drop ears, dark brown eyes.',
        'aging_1yr'      => '◆ BERNER +1 YEAR: Dramatically bigger, lush tricolor coat, vivid chest blaze',
        'aging_3yr'      => '◆ BERNER +3 YEARS: Full giant size, tricolor at peak beauty, gentle majestic expression',
      ]);
    }

    // ── DALMATIAN ─────────────────────────────────────────────────────
    if ($this->mb($b, ['dalmatian'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'athletic',
        'coat_type'      => 'short',
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Dalmatians grow into large, lean, muscular spotted dogs.',
        'adult_body_note' => 'Large, lean, muscular, elegant. Crisp spots. 23–27 kg.',
        'adult_face_note' => 'Long strong refined head. Alert eyes. Spotted drop ears.',
        'aging_1yr'      => '◆ DALMATIAN +1 YEAR: Much larger lean athletic body, crisp defined spots',
        'aging_3yr'      => '◆ DALMATIAN +3 YEARS: Gray/silver on muzzle/chin, peak athletic body, dignified expression',
      ]);
    }

    // ── BOXER ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['boxer'])) {
      return array_merge($default, [
        'size_category'  => 'large',
        'body_shape'     => 'stocky',
        'coat_type'      => 'short',
        'brachycephalic' => true,
        'gray_pattern'   => 'moderate',
        'size_note'      => 'Boxers grow into muscular, powerful dogs.',
        'adult_body_note' => 'Powerful, medium-large, square body. Deep chest. 25–32 kg.',
        'adult_face_note' => 'Broad blunt squarish muzzle. Wrinkled forehead. Alert expression.',
        'aging_1yr'      => '◆ BOXER +1 YEAR: Dramatically more muscular, very broad chest, prominent wrinkled forehead',
        'aging_3yr'      => '◆ BOXER +3 YEARS: Clear gray/white on muzzle and around eyes (Boxers gray early), barrel-chested',
      ]);
    }

    return $default;
  }

  private function mb(string $b, array $patterns): bool
  {
    foreach ($patterns as $p) {
      if (stripos($b, $p) !== false) return true;
    }
    return false;
  }

  // ─────────────────────────────────────────────────────────────────────
  //  STATUS UPDATE
  // ─────────────────────────────────────────────────────────────────────

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
