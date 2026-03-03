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

      $imageData = $this->prepareHighQualityImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception('Failed to prepare image from path: ' . $this->imagePath);
      }

      $currentAgeStage = $this->detectAgeStage($imageData);
      Log::info("🔍 Detected age stage: {$currentAgeStage}");

      $breedProfile = $this->getBreedProfile($this->breed);
      $breedProfile['detected_age_stage'] = $currentAgeStage;

      $selectedModel = $this->selectBestModel();
      Log::info("🤖 Using model: {$selectedModel}");

      $simulations = $this->generateTransformations($imageData, $breedProfile, $selectedModel);

      $savedPaths = ['1_years' => null, '3_years' => null];

      if (!empty($simulations['1_year'])) {
        $savedPaths['1_years'] = $this->saveImage($simulations['1_year'], '1_year', $this->resultId, $imageData);
        Log::info("✅ 1-year saved: {$savedPaths['1_years']}");
      }

      if (!empty($simulations['3_years'])) {
        $savedPaths['3_years'] = $this->saveImage($simulations['3_years'], '3_years', $this->resultId, $imageData);
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

  // ─────────────────────────────────────────────────────────────────────────
  //  MODEL SELECTION
  // ─────────────────────────────────────────────────────────────────────────

  private function selectBestModel(): string
  {
    $configured = config('services.gemini.image_model') ?? env('GEMINI_IMAGE_MODEL');
    if ($configured) return $configured;
    return self::MODEL_PRIORITY[0];
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  AGE STAGE DETECTION
  // ─────────────────────────────────────────────────────────────────────────

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

    $isThinkingModel = in_array($modelName, [
      'gemini-3-pro-image-preview',
      'gemini-3.1-flash-image-preview',
      'gemini-2.5-flash-image',
    ]);

    $generationConfig = [
      'temperature'        => $isThinkingModel ? 0.15 : 0.25,
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
      Log::error('No candidates from Gemini', ['model' => $modelName, 'preview' => substr($body, 0, 800)]);
      throw new \Exception("No candidates returned by {$modelName}");
    }

    $candidate    = $responseData['candidates'][0];
    $finishReason = $candidate['finishReason'] ?? '';

    if (in_array($finishReason, ['SAFETY', 'RECITATION', 'OTHER', 'PROHIBITED_CONTENT'])) {
      throw new \Exception("Generation blocked by {$modelName}: finishReason={$finishReason}");
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
  //  ★★★ MASTER PROMPT BUILDER — COMPLETELY REWRITTEN FOR MAXIMUM IMPACT ★★★
  // ─────────────────────────────────────────────────────────────────────────

  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed         = $profile['breed'];
    $size          = $profile['size_category'];
    $coat          = $profile['coat_type'];
    $isBrachy      = $profile['brachycephalic'];
    $bodyShape     = $profile['body_shape'] ?? 'standard';
    $sizeNote      = $profile['size_note'] ?? '';
    $adultBody     = $profile['adult_body_note'] ?? '';
    $adultFace     = $profile['adult_face_note'] ?? '';
    $detectedAge   = $profile['detected_age_stage'] ?? 'adult';

    // Per-breed specific aging changes (the most important new addition)
    $aging1yr = $profile['aging_1yr'] ?? '';
    $aging3yr = $profile['aging_3yr'] ?? '';

    $L = [];

    // ══════════════════════════════════════════════════════════════════════
    // ABSOLUTE MISSION STATEMENT
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '╔════════════════════════════════════════════════════════════════╗';
    $L[] = '║  MISSION: PRODUCE A CLEARLY AND OBVIOUSLY AGED VERSION         ║';
    $L[] = '║  OF THE ATTACHED DOG PHOTO. THE AGING MUST BE UNMISTAKABLE.   ║';
    $L[] = '╚════════════════════════════════════════════════════════════════╝';
    $L[] = '';
    $L[] = 'You are a world-class photo retouching AI specializing in';
    $L[] = 'hyper-realistic canine age progression. Your #1 goal is to';
    $L[] = "make this dog look CLEARLY +{$targetYears} year(s) older — so";
    $L[] = 'obvious that anyone who sees both photos immediately says';
    $L[] = '"Yes, that dog is noticeably older."';
    $L[] = '';
    $L[] = "BREED: {$breed}";
    $L[] = "CURRENT AGE STAGE (system-detected): {$detectedAge}";
    $L[] = "TARGET: Make the dog look +{$targetYears} year(s) older than NOW.";
    $L[] = '';

    // ══════════════════════════════════════════════════════════════════════
    // THE #1 FAILURE MODE — MUST AVOID
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '🚨 THE #1 FAILURE MODE YOU MUST AVOID 🚨';
    $L[] = '═══════════════════════════════════════════';
    $L[] = 'The most common mistake is producing an output that looks';
    $L[] = 'NEARLY IDENTICAL to the input. This is a TOTAL FAILURE.';
    $L[] = '';
    $L[] = 'If a viewer cannot immediately tell which photo is older,';
    $L[] = 'you have FAILED. The transformation MUST be dramatic enough';
    $L[] = 'to be obvious at a glance.';
    $L[] = '';
    $L[] = '═══════════════════════════════════════════';
    $L[] = '';

    // ══════════════════════════════════════════════════════════════════════
    // PHASE 1 — SCENE ANCHOR (unchanged from input)
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 1 ▶ SCENE ANCHOR — memorize and FREEZE these elements';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = 'The following MUST be pixel-perfect preserved. These NEVER change:';
    $L[] = '  • Background (every element, color, blur, texture)';
    $L[] = '  • Camera angle, zoom level, framing, crop';
    $L[] = '  • Lighting direction, shadow positions, highlight placement';
    $L[] = '  • Dog pose: all 4 paw positions, body orientation, head angle';
    $L[] = '  • Ear position, tail position, sitting/standing/lying state';
    $L[] = '';
    $L[] = '🚫 NEVER: move any limb, change background, zoom in/out,';
    $L[] = '   add/remove elements, change lighting, resize the dog.';
    $L[] = '';

    // ══════════════════════════════════════════════════════════════════════
    // PHASE 2 — BREED-SPECIFIC MANDATORY AGING CHANGES
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "PHASE 2 ▶ MANDATORY VISIBLE CHANGES FOR {$breed} — +{$targetYears} YEAR(S)";
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = '⚠️  EVERY item below is MANDATORY. Each change must be clearly';
    $L[] = '    visible in the output. "Subtle" is NOT acceptable here.';
    $L[] = '';

    if ($targetYears === 1) {

      // ── 1-YEAR CHANGES ─────────────────────────────────────────────────
      $L[] = "◆ BREED-SPECIFIC 1-YEAR AGING CHANGES FOR {$breed}:";
      $L[] = '  (These are the specific biological changes this breed undergoes in 1 year)';
      $L[] = '';
      if ($aging1yr) {
        $L[] = $aging1yr;
      }
      $L[] = '';

      $L[] = '◆ UNIVERSAL 1-YEAR CHANGES (apply to ALL dogs):';
      $L[] = '';

      switch ($detectedAge) {
        case 'puppy':
          $L[] = '  Current stage: PUPPY → Target: TEENAGER/YOUNG ADULT';
          $L[] = '  DRAMATIC changes required (this is the biggest growth phase):';
          $L[] = '';
          $L[] = '  BODY:';
          $L[] = '    ✦ Legs DRAMATICALLY longer — the stubby puppy legs grow significantly';
          $L[] = '    ✦ Body SIGNIFICANTLY larger — noticeably bigger overall';
          $L[] = '    ✦ Chest deeper and wider — no longer a round barrel puppy chest';
          $L[] = '    ✦ Neck longer and more defined';
          $L[] = '    ✦ Gangly/adolescent proportions (legs slightly long for body)';
          $L[] = '    ✦ Remove the round puppy potbelly completely';
          $L[] = '';
          $L[] = '  FACE:';
          $L[] = '    ✦ Head SMALLER relative to body — no longer oversized';
          $L[] = '    ✦ Muzzle CLEARLY longer and more defined';
          $L[] = '    ✦ Cheekbones beginning to define';
          $L[] = '    ✦ Paws now proportionate (remove oversized puppy paws)';
          $L[] = '    ✦ Eyes smaller relative to head';
          $L[] = '';
          $L[] = '  COAT:';
          $L[] = "    ✦ Replace thin puppy coat with developing adult coat ({$coat})";
          $L[] = '    ✦ Noticeably thicker and more defined texture';
          break;

        case 'teenager':
          $L[] = '  Current stage: TEENAGER → Target: YOUNG ADULT';
          $L[] = '';
          $L[] = '  BODY:';
          $L[] = '    ✦ Fill out the gangly proportions — body now broader and more balanced';
          $L[] = '    ✦ Chest noticeably deeper and wider';
          $L[] = '    ✦ Muscles clearly more developed — especially shoulders and hindquarters';
          $L[] = '    ✦ Legs proportionate now — no longer too long for body';
          $L[] = '';
          $L[] = '  FACE:';
          $L[] = '    ✦ Face CLEARLY more adult and defined';
          $L[] = '    ✦ Muzzle more square and strong';
          $L[] = '    ✦ Ears now fully upright/set (if erect ear breed)';
          $L[] = '    ✦ Adult expression — more confident, less puppyish';
          $L[] = '';
          $L[] = '  COAT:';
          $L[] = "    ✦ Coat reaches adult density and texture ({$coat})";
          break;

        case 'young_adult':
          $L[] = '  Current stage: YOUNG ADULT → Target: PRIME ADULT';
          $L[] = '';
          $L[] = '  BODY:';
          $L[] = '    ✦ Chest CLEARLY deeper and more barrel-shaped';
          $L[] = '    ✦ Shoulders VISIBLY broader and more muscular';
          $L[] = '    ✦ Hindquarters CLEARLY more powerful and defined';
          $L[] = '    ✦ Neck thicker and more powerful';
          $L[] = '    ✦ Overall: this dog is NOW at its physical PEAK — noticeable';
          $L[] = '';
          $L[] = '  FACE:';
          $L[] = '    ✦ Bone structure CLEARLY more chiseled and defined';
          $L[] = '    ✦ Muzzle stronger, more square';
          $L[] = '    ✦ Expression: confident, settled, prime of life';
          $L[] = '';
          $L[] = '  COAT:';
          $L[] = "    ✦ Coat at absolute peak condition — densest, richest, most lustrous";
          break;

        default: // adult
          $L[] = '  Current stage: ADULT → Target: PRIME/MATURE ADULT (+1yr)';
          $L[] = '';
          $L[] = '  ⚠️  ADULT DOGS STILL SHOW CLEAR AGING AT 1 YEAR. Apply these:';
          $L[] = '';
          $L[] = '  BODY (MANDATORY — must be clearly visible):';
          $L[] = '    ✦ MUSCLE DEFINITION: Noticeably more filled-out and powerful.';
          $L[] = '      The chest is CLEARLY broader. The shoulders are VISIBLY more';
          $L[] = '      muscular. The hindquarters are MORE developed. This is not subtle.';
          $L[] = '    ✦ BODY WEIGHT: Dog appears 5-10% heavier and more substantial';
          $L[] = '    ✦ NECK: Thicker and more powerful';
          $L[] = '';
          $L[] = '  FACE (MANDATORY — must be clearly visible):';
          $L[] = '    ✦ BONE STRUCTURE: More chiseled and pronounced cheekbones';
          $L[] = '    ✦ MUZZLE: More square, more defined, more powerful-looking';
          $L[] = '    ✦ EXPRESSION: Settled, confident — noticeably more mature';
          $L[] = '    ✦ EYES: More depth and maturity in the gaze';
          $L[] = '    ✦ JOWLS/LIPS: Slightly heavier and more defined (breed-dependent)';
          $L[] = '';
          $L[] = '  COAT (MANDATORY — must be clearly visible):';
          $L[] = "    ✦ TEXTURE: Noticeably richer, denser, more lustrous ({$coat})";
          $L[] = '    ✦ COLOR: Slightly richer/deeper tone — coat at absolute peak';
          $L[] = '';
          $L[] = '  OVERALL: A viewer side-by-side MUST immediately notice this dog';
          $L[] = '  is older. Make the changes BIG ENOUGH to be obvious.';
          break;
      }
    } else {

      // ── 3-YEAR CHANGES ─────────────────────────────────────────────────
      $L[] = "◆ BREED-SPECIFIC 3-YEAR AGING CHANGES FOR {$breed}:";
      $L[] = '  (These are the specific biological changes this breed undergoes in 3 years)';
      $L[] = '';
      if ($aging3yr) {
        $L[] = $aging3yr;
      }
      $L[] = '';

      $L[] = '◆ UNIVERSAL 3-YEAR CHANGES (apply to ALL dogs):';
      $L[] = '';

      switch ($detectedAge) {
        case 'puppy':
        case 'teenager':
          $L[] = '  Current stage: YOUNG → Target: FULL PRIME ADULT';
          $L[] = '  TRANSFORMATION must be DRAMATIC — this is a huge physical change:';
          $L[] = '';
          $L[] = '  BODY:';
          $L[] = '    ✦ Completely adult body — ZERO puppy/teen traits remain';
          $L[] = '    ✦ Full adult size achieved (see breed profile)';
          $L[] = '    ✦ MAXIMUM muscle development — visibly powerful';
          $L[] = '    ✦ Deep, full, broad chest';
          $L[] = '    ✦ Strong, defined hindquarters and shoulders';
          $L[] = '';
          $L[] = '  FACE:';
          $L[] = '    ✦ Complete adult facial structure — totally defined';
          $L[] = '    ✦ Strong, masculine/feminine bone structure';
          $L[] = '    ✦ Confident, mature adult expression';
          $L[] = '';
          $L[] = '  COAT:';
          $L[] = "    ✦ Full adult coat at MAXIMUM beauty ({$coat})";
          break;

        case 'young_adult':
          $L[] = '  Current stage: YOUNG ADULT → Target: PRIME MATURE ADULT';
          $L[] = '';
          $L[] = '  BODY:';
          $L[] = '    ✦ CLEARLY more massive and powerful — noticeably bigger';
          $L[] = '    ✦ Maximum muscle development — peak physical condition';
          $L[] = '    ✦ Body appears 10-15% more substantial overall';
          $L[] = '';
          $L[] = '  FACE:';
          $L[] = '    ✦ Face CLEARLY more chiseled, defined, and weathered';
          $L[] = '    ✦ More mature, experienced expression — wisdom in the eyes';
          $L[] = '    ✦ GRAYING: First visible gray/silver hairs on muzzle tip REQUIRED';
          $L[] = '';
          $L[] = '  COAT:';
          $L[] = "    ✦ Coat at ABSOLUTE PEAK — densest, richest version ({$coat})";
          break;

        default: // adult or senior
          $L[] = '  Current stage: ADULT → Target: MATURE/DISTINGUISHED ADULT (+3yrs)';
          $L[] = '';
          $L[] = '  ⚠️  THIS IS THE MOST IMPORTANT TRANSFORMATION. +3 years on';
          $L[] = '  an adult dog = CLEAR and SUBSTANTIAL visible aging.';
          $L[] = '';
          $L[] = '  MUZZLE GRAYING — THE #1 MANDATORY CHANGE:';
          $L[] = '  ┌─────────────────────────────────────────────────────────┐';
          $L[] = '  │  The muzzle/chin MUST show CLEAR gray/white/silver hair │';
          $L[] = '  │  coverage. This is NON-NEGOTIABLE. If this is missing,  │';
          $L[] = '  │  the transformation has FAILED. The graying must be so  │';
          $L[] = '  │  obvious that it is the FIRST thing a viewer notices.   │';
          $L[] = '  └─────────────────────────────────────────────────────────┘';
          $L[] = '';
          $L[] = '  ' . $this->grayChange3Years($profile);
          $L[] = '';
          $L[] = '  BODY (MANDATORY — clearly visible):';
          $L[] = '    ✦ MASS: Noticeably heavier and more substantial overall';
          $L[] = '    ✦ MUSCLE: Still strong but transitioning — slightly less taut,';
          $L[] = '      more "settled" look. Not loose, but more mature and thick.';
          $L[] = '    ✦ CHEST: Broader, deeper, more barrel-like';
          $L[] = '    ✦ NECK: Thicker, more powerful';
          $L[] = '    ✦ JOWLS: Slightly heavier and more pronounced';
          $L[] = '';
          $L[] = '  FACE (MANDATORY — clearly visible):';
          $L[] = '    ✦ WRINKLES/FOLDS: Deeper and more pronounced';
          $L[] = '    ✦ BONE STRUCTURE: More defined stop, stronger cheekbones';
          $L[] = '    ✦ EYES: Deeper-set, more experienced, slightly less bright';
          $L[] = '    ✦ EXPRESSION: Calm, wise, settled, dignified — this dog has';
          $L[] = '      lived life. A completely different energy from the input.';
          $L[] = '    ✦ MUZZLE: Slightly more square and heavier';
          $L[] = '';
          $L[] = '  COAT (MANDATORY — clearly visible):';
          $L[] = "    ✦ Settled, mature texture — slightly coarser than peak ({$coat})";
          $L[] = '    ✦ Color slightly deeper/more saturated';
          $L[] = '';
          $L[] = '  SKIN/TEXTURE:';
          $L[] = '    ✦ Slightly more weathered — skin texture more defined under coat';
          $L[] = '';
          $L[] = '  OVERALL: The output MUST look like a CLEARLY OLDER version.';
          $L[] = '  If someone covers the labels, they should instantly see which is older.';
          break;
      }
    }

    $L[] = '';

    // ══════════════════════════════════════════════════════════════════════
    // PHASE 3 — BREED BIOLOGY CONSTRAINTS
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "PHASE 3 ▶ {$breed} BREED-SPECIFIC BIOLOGY (NEVER violate these)";
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = "SIZE CLASS: " . strtoupper($size);
    $L[] = "BODY SHAPE: " . strtoupper($bodyShape);
    $L[] = $sizeNote;
    $L[] = '';
    $L[] = "ADULT BODY: {$adultBody}";
    $L[] = '';
    $L[] = "ADULT FACE: {$adultFace}";
    $L[] = '';

    if ($bodyShape === 'long_low') {
      $L[] = '🔴 LONG-AND-LOW: Legs NEVER grow taller. Body grows heavier/longer. DO NOT add height.';
      $L[] = '';
    }
    if ($bodyShape === 'sighthound') {
      $L[] = '🔴 SIGHTHOUND: DO NOT add bulk. Stays slender, elegant, racing-athlete build.';
      $L[] = '';
    }
    if ($isBrachy) {
      $L[] = '🔴 BRACHYCEPHALIC BREED: NEVER elongate the muzzle. Flat face is PERMANENT.';
      $L[] = '   Aging adds wrinkle DEPTH, darker mask, heavier jowls — NOT muzzle length.';
      $L[] = '';
    }

    // Coat preservation
    $L[] = '🔴 COAT PRESERVATION: Coat type = ' . strtoupper($coat);
    switch ($coat) {
      case 'curly/fluffy':
        $L[] = '   Curls/fluffiness MUST remain. Aging = DENSER curls. NEVER straighten.';
        break;
      case 'double_coat':
        $L[] = '   Double coat becomes THICKER and FULLER. Never reduce to single layer.';
        break;
      case 'long_silky':
        $L[] = '   Silky coat grows LONGER and LUSHER. Never make it shorter or curlier.';
        break;
      case 'wire':
        $L[] = '   Wiry texture becomes MORE pronounced and bristly. Never soften it.';
        break;
      case 'short':
        $L[] = '   Short coat becomes GLOSSIER and DENSER. Never grow it out.';
        break;
    }
    $L[] = '';

    // ══════════════════════════════════════════════════════════════════════
    // PHASE 4 — BIOLOGICAL REALISM
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 4 ▶ REALISM & PRESERVATION RULES';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  • Coat COLOR preserved — same hue, same markings, same patches';
    $L[] = '  • Eye color preserved exactly';
    $L[] = '  • Nose color preserved exactly';
    $L[] = '  • Fur direction consistent with original';
    $L[] = '  • Result is a REAL PHOTOGRAPH — no illustration/art/cartoon style';
    $L[] = '  • Dog is healthy, well-groomed, happy/calm — never sick or neglected';
    $L[] = '';

    // ══════════════════════════════════════════════════════════════════════
    // PHASE 5 — FINAL VERIFICATION (THE GATE)
    // ══════════════════════════════════════════════════════════════════════
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 5 ▶ FINAL VERIFICATION — DO THIS BEFORE OUTPUTTING';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = '🚨 THE MOST CRITICAL QUESTION: Imagine showing both photos to';
    $L[] = "   someone who has never seen this dog. Would they IMMEDIATELY";
    $L[] = "   and CONFIDENTLY say the output looks {$targetYears} year(s) older?";
    $L[] = '';
    $L[] = '   If YES → Output the image.';
    $L[] = '   If NO or MAYBE → You MUST regenerate with STRONGER, more';
    $L[] = '   DRAMATIC aging effects. "Subtle" is a FAILURE here.';
    $L[] = '';

    if ($targetYears === 3 && in_array($detectedAge, ['adult', 'young_adult'])) {
      $L[] = '🚨 SPECIAL CHECK FOR 3-YEAR ADULT AGING:';
      $L[] = '   Is the muzzle/chin CLEARLY showing gray/white/silver hair?';
      $L[] = '   If you cannot immediately see the graying — REGENERATE.';
      $L[] = '   The graying is the #1 visual signature of 3-year aging and';
      $L[] = '   MUST be prominent enough to see at a normal viewing distance.';
      $L[] = '';
    }

    $L[] = 'POSE/SCENE CHECKLIST:';
    $L[] = '  □ Paws and body pose unchanged?';
    $L[] = '  □ Background identical?';
    $L[] = '  □ Lighting/shadows unchanged?';
    $L[] = '  □ Camera angle unchanged?';
    $L[] = '';
    $L[] = 'AGING CHECKLIST:';
    $L[] = "  □ Is the aging OBVIOUSLY visible at a glance? (+{$targetYears}yr)";

    if (!empty($aging1yr) && $targetYears === 1) {
      $L[] = "  □ Do the breed-specific 1-year changes appear clearly?";
    }
    if (!empty($aging3yr) && $targetYears === 3) {
      $L[] = "  □ Do the breed-specific 3-year changes appear clearly?";
      $L[] = "  □ Is the muzzle graying CLEARLY visible?";
    }

    $L[] = '  □ Coat type preserved (' . $coat . ')?';
    $L[] = '  □ Color and markings preserved?';
    $L[] = '  □ Dog looks healthy and dignified?';
    $L[] = '';
    $L[] = '╔════════════════════════════════════════════════════════════════╗';
    $L[] = '║  OUTPUT: Same dog. Same scene. Same pose. OBVIOUSLY older.     ║';
    $L[] = '║  The aging transformation must be BOLD, CLEAR, and IMMEDIATE.  ║';
    $L[] = '╚════════════════════════════════════════════════════════════════╝';

    return implode("\n", $L);
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  COAT & GRAY HELPERS
  // ─────────────────────────────────────────────────────────────────────────

  private function coatChange1Year(string $coat): string
  {
    return match ($coat) {
      'curly/fluffy'  => 'Curls more defined and denser — COAT REMAINS CURLY/FLUFFY.',
      'double_coat'   => 'Adult double coat developing — thicker, denser, lush.',
      'long_silky'    => 'Coat growing toward adult length — silky, flowing.',
      'wire'          => 'Wiry texture becoming defined — rough, bristly, beard more prominent.',
      'short'         => 'Short adult coat fully developed — smooth, glossy sheen.',
      default         => 'Adult coat developing — healthier, denser, more defined.',
    };
  }

  private function coatChange3Years(string $coat): string
  {
    return match ($coat) {
      'curly/fluffy'  => 'Coat at full adult glory — richly textured. COAT REMAINS CURLY/FLUFFY.',
      'double_coat'   => 'Dense, full double coat at peak — rich color, thick undercoat.',
      'long_silky'    => 'Coat at full adult length — flowing, silky, beautiful.',
      'wire'          => 'Wiry coat fully expressed — rough and dense at its best.',
      'short'         => 'Short coat glossy, dense, sleek — fits the mature body perfectly.',
      default         => 'Mature adult coat — full, healthy, clean.',
    };
  }

  private function grayChange3Years(array $profile): string
  {
    return match ($profile['gray_pattern'] ?? 'moderate') {
      'none'      => '🔴 NO graying — this breed does not gray noticeably at 3 years.',
      'minimal'   => '🔴 MINIMAL graying: a few clearly visible silver/gray hairs on the muzzle tip only.',
      'moderate'  => '🔴 MODERATE GRAYING (MANDATORY): Clear gray/silver on muzzle tip, chin,
         around nostrils, and faint silver around eyes. This MUST be obviously visible.
         Gray enough that any viewer immediately notices it. Coat color preserved elsewhere.',
      'prominent' => '🔴 PROMINENT GRAYING (MANDATORY): Strong silver/gray covering the entire
         muzzle, chin, and around the eyes — unmistakable, distinguished sign of maturity.
         A viewer must immediately notice it. Coat color preserved elsewhere.',
      default     => '🔴 Visible muzzle-tip graying — clearly noticeable, not subtle.',
    };
  }

  // ─────────────────────────────────────────────────────────────────────────
  //  ★★★ BREED PROFILE DATABASE — NOW WITH PER-BREED AGING NOTES ★★★
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
      'aging_1yr'           => '  • Noticeably more muscular chest and shoulders
  • Coat richer and denser — shinier, more defined
  • Face: more defined cheekbones and muzzle structure
  • Expression: calmer, more settled and confident
  • Slightly heavier overall body frame',
      'aging_3yr'           => '  • Clearly visible muzzle graying (gray/white hairs on muzzle tip and chin)
  • More prominent facial wrinkles and skin texture
  • Heavier, more substantial body — noticeably more mass
  • Deeper-set, wiser eyes with more depth
  • Coat slightly coarser and more weathered
  • Expression: calm, dignified, experienced — the look of maturity',
    ];

    // ── FRENCH BULLDOG (our primary test case — full detail) ──────────────
    if ($this->mb($b, ['french bulldog', 'frenchie', 'french bull'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'stocky',
        'coat_type'           => 'short',
        'grows_significantly' => false,
        'brachycephalic'      => true,
        'gray_pattern'        => 'minimal',
        'size_note'           => 'French Bulldogs stay small and stocky. They get heavier and more muscular but NOT taller.',
        'adult_body_note'     => 'Heavy, muscular, compact. Very wide shoulders and chest, narrow hindquarters. Short stocky legs. Weight 9–13 kg.',
        'adult_face_note'     => 'Flat face with deep wrinkles/folds. Massive square head. Bat-like erect ears — breed signature. Very short pushed-in nose. Heavy jowls.',
        'aging_1yr'           => '  FRENCH BULLDOG — 1 YEAR LATER. These changes MUST ALL be visible:
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  BODY:
    ✦ MUSCLE: Chest and shoulders NOTICEABLY broader and more muscular.
      The pectoral muscles on the chest are clearly more defined and powerful.
      This is the #1 body change for a Frenchie at this age.
    ✦ WEIGHT: Dog appears 8-12% heavier and more substantial
    ✦ BACK: Broader and more defined — the classic Frenchie cobby silhouette
      is more pronounced
    ✦ NECK: Thicker and more powerful
  FACE:
    ✦ WRINKLES: The facial wrinkles/folds are NOTICEABLY deeper and more
      pronounced — especially the nose roll and forehead wrinkles
    ✦ JOWLS: Heavier, more pendulous, more defined
    ✦ HEAD: Appears even broader and more square — the signature Frenchie
      blockhead is more pronounced
    ✦ MUZZLE: Darker, more defined black mask around nose and lips
    ✦ EYES: More deep-set, rounder, with confident maturity
    ✦ EXPRESSION: Settled, proud, confident — no longer puppyish
  COAT:
    ✦ Short coat glossier, shinier, better condition — noticeably richer
    ✦ Color slightly more vivid and defined',
        'aging_3yr'           => '  FRENCH BULLDOG — 3 YEARS LATER. These changes MUST ALL be visible:
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  FACE (the most important changes — must be unmistakable):
    ✦ MUZZLE GRAYING: Clearly visible white/gray/silver hairs on the muzzle
      tip, around the nostrils, and on the chin. This must be OBVIOUS.
      Anyone looking at the photo should immediately notice the gray.
    ✦ FOREHEAD WRINKLES: Dramatically deeper — the folds on the forehead
      are MUCH more pronounced and numerous
    ✦ NOSE ROLL: The skin fold over the nose is deeper and more prominent
    ✦ JOWL FOLDS: The skin folds around the cheeks and jowls are clearly
      heavier, more pendulous, and more deeply creased
    ✦ EYES: More deep-set, with slightly more skin drooping around them
    ✦ OVERALL FACE: A weathered, experienced, distinguished Frenchie.
      The face tells a story of maturity and life experience.
  BODY:
    ✦ MASS: Noticeably heavier — 10-15% more substantial than input
    ✦ CHEST: Very broad and barrel-shaped — even more than 1-year version
    ✦ MUSCLE: Still powerful but slightly less taut — settled, mature bulk
    ✦ NECK: Heavy and powerful
  COAT:
    ✦ Slightly coarser short coat — still healthy but more textured/matte
    ✦ Color slightly more muted and weathered
  EXPRESSION:
    ✦ Completely calm, settled, dignified — the look of an experienced,
      mature Frenchie. Eyes carry depth and wisdom.',
      ]);
    }

    // ── BULLDOG / ENGLISH BULLDOG ──────────────────────────────────────────
    if ($this->mb($b, ['bulldog', 'english bulldog'])) {
      return array_merge($default, [
        'size_category'       => 'medium',
        'body_shape'          => 'stocky',
        'coat_type'           => 'short',
        'grows_significantly' => false,
        'brachycephalic'      => true,
        'gray_pattern'        => 'minimal',
        'size_note'           => 'Bulldogs get heavier and more wrinkled but NOT taller.',
        'adult_body_note'     => 'Extremely wide, heavy, low-slung. Massive chest, short bowed legs. Weight 22–25 kg.',
        'adult_face_note'     => 'Massive wrinkled face with deep skin folds, flat nose, pronounced underbite, huge jowls.',
        'aging_1yr'           => '  BULLDOG 1-year changes (must all be clearly visible):
  ✦ Chest DRAMATICALLY broader — wider than puppy/teen
  ✦ Wrinkles CLEARLY deeper and more numerous
  ✦ Jowls heavier, more pendulous
  ✦ Shoulders and body noticeably more massive',
        'aging_3yr'           => '  BULLDOG 3-year changes (must all be clearly visible):
  ✦ Very heavy, broad, extremely wrinkled mature body
  ✦ Face: deeply wrinkled, heavy jowls, more pronounced skin folds
  ✦ Muzzle: faint gray hairs on chin and lip edges
  ✦ Expression: calm, settled, dignified — classic mature Bulldog look
  ✦ Body appears significantly more massive and settled',
      ]);
    }

    // ── PUG ───────────────────────────────────────────────────────────────
    if ($this->mb($b, ['pug'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'stocky',
        'coat_type'           => 'short',
        'grows_significantly' => false,
        'brachycephalic'      => true,
        'gray_pattern'        => 'minimal',
        'size_note'           => 'Pugs stay small and round. They get heavier and rounder but NOT taller.',
        'adult_body_note'     => 'Cobby, round, compact. Heavy for size. Weight 6–9 kg. Tightly curled tail.',
        'adult_face_note'     => 'Massive round head, very flat face, deep wrinkles, large bulging eyes, very short nose, heavy jowls.',
        'aging_1yr'           => '  PUG 1-year changes:
  ✦ Body rounder and heavier — more cobby
  ✦ Face wrinkles deeper and more defined
  ✦ Jowls noticeably heavier
  ✦ More defined, settled expression',
        'aging_3yr'           => '  PUG 3-year changes:
  ✦ Substantially heavier, rounder mature body
  ✦ Very deep facial wrinkles — more numerous
  ✦ Faint gray hairs visible on muzzle/chin
  ✦ Heavier jowls, deeper eye socket wrinkles
  ✦ Calm, wise, settled expression',
      ]);
    }

    // ── MUDI ─────────────────────────────────────────────────────────────
    if ($this->mb($b, ['mudi'])) {
      return array_merge($default, [
        'size_category'       => 'medium',
        'body_shape'          => 'athletic',
        'coat_type'           => 'curly/fluffy',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Mudis grow into medium-sized athletic herding dogs.',
        'adult_body_note'     => 'Medium, muscular, athletic body. Weight 8–13 kg. Strong hindquarters.',
        'adult_face_note'     => 'Wedge-shaped head. Erect, pointed ears (fully upright in adults). Almond-shaped eyes. Intelligent expression.',
        'aging_1yr'           => '  MUDI 1-year changes:
  ✦ Curly coat dramatically thicker and more defined — lush adult curls
  ✦ Body more muscular and athletic — stronger hindquarters
  ✦ Ears fully erect and prominent if not already
  ✦ Face: more defined, intelligent adult expression',
        'aging_3yr'           => '  MUDI 3-year changes:
  ✦ Rich, fully developed curly coat at absolute peak
  ✦ Very athletic, powerful, muscular adult body
  ✦ Muzzle: noticeable gray/silver hairs beginning on chin and muzzle tip
  ✦ Expression: calm, focused, wise herding dog maturity',
      ]);
    }

    // ── LABRADOR ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['labrador', 'lab'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Labradors grow dramatically — much taller, heavier, and broader than puppy.',
        'adult_body_note'     => 'Broad, powerful, strongly built. Wide head, deep chest. Weight 25–36 kg.',
        'adult_face_note'     => 'Broad, clean-cut head. Wide powerful muzzle. Kind, intelligent eyes. Drop ears.',
        'aging_1yr'           => '  LABRADOR 1-year changes:
  ✦ MUCH bigger, broader, heavier body — dramatic size increase if from puppy
  ✦ Chest clearly wider and deeper
  ✦ Face broader and more defined
  ✦ Thick otter tail fully developed
  ✦ Coat glossier, denser, more defined',
        'aging_3yr'           => '  LABRADOR 3-year changes:
  ✦ Muzzle: clear gray/white hairs spreading from tip across chin — obvious
  ✦ Heavier, more settled body — still powerful but more substantial
  ✦ Face more weathered: deeper labial folds, more defined jowls
  ✦ Eyes: calm, deep, experienced — the classic mature Lab look
  ✦ Expression: serene, wise, gentle — very recognizably older',
      ]);
    }

    // ── GOLDEN RETRIEVER ─────────────────────────────────────────────────
    if ($this->mb($b, ['golden retriever'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'long_silky',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Golden Retrievers grow into large, beautiful, feathered dogs.',
        'adult_body_note'     => 'Large, well-balanced, powerful. Deep chest, flowing golden coat. Weight 25–34 kg.',
        'adult_face_note'     => 'Broad, slightly arched skull. Gentle, intelligent expression. Drop ears.',
        'aging_1yr'           => '  GOLDEN RETRIEVER 1-year changes:
  ✦ Coat noticeably longer and more flowing — feathering on ears/legs/tail
  ✦ Body larger, deeper chest, more muscular
  ✦ Face more defined — broader, more adult structure',
        'aging_3yr'           => '  GOLDEN RETRIEVER 3-year changes:
  ✦ Coat at full adult glory — long, silky, beautifully feathered
  ✦ Clear gray/white hairs on muzzle, around nose, spreading to chin
  ✦ Face: more weathered, slightly droopier jowls, deeper expression
  ✦ Broader, heavier body
  ✦ Eyes: deep, wise, warm — unmistakably older Golden',
      ]);
    }

    // ── GERMAN SHEPHERD ──────────────────────────────────────────────────
    if ($this->mb($b, ['german shepherd', 'alsatian'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern'        => 'prominent',
        'size_note'           => 'German Shepherds grow dramatically — much taller, broader chest.',
        'adult_body_note'     => 'Strong, agile, muscular. Slightly longer than tall, deep chest, sloping back. Weight 22–40 kg.',
        'adult_face_note'     => 'Strong wedge-shaped head. SIGNATURE: fully erect, pointed ears. Alert expression.',
        'aging_1yr'           => '  GSD 1-year changes:
  ✦ Much larger, more powerful body — dramatic if from puppy
  ✦ Ears FULLY erect and prominent
  ✦ Coat thicker, double coat fully developed
  ✦ Face: strong, defined adult wedge structure',
        'aging_3yr'           => '  GSD 3-year changes:
  ✦ Muzzle: prominent gray/silver hair from tip to chin — very visible
  ✦ Around eyes: faint silver hairs framing the face
  ✦ Coat: rich, thick double coat at peak condition
  ✦ Face: more defined, experienced, dignified expression
  ✦ Body: powerful, filled-out prime adult',
      ]);
    }

    // ── CORGI ────────────────────────────────────────────────────────────
    if ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'long_low',
        'coat_type'           => 'double_coat',
        'grows_significantly' => false,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Corgis are long-and-low. THEY DO NOT GROW TALL. Short legs are genetic.',
        'adult_body_note'     => 'Long body, very short legs, deep chest, muscular hindquarters. Always stays low to ground.',
        'adult_face_note'     => 'Fox-like face. Large upright pointed ears — fully erect and prominent.',
        'aging_1yr'           => '  CORGI 1-year changes:
  ✦ Body more muscular — broader chest, stronger hindquarters
  ✦ Coat thicker, double coat denser
  ✦ Fox-like face more defined and adult
  ✦ Ears: prominent, fully upright, large (if not already)',
        'aging_3yr'           => '  CORGI 3-year changes:
  ✦ Muzzle: gray/white hairs on tip and chin — visible
  ✦ Denser, fuller double coat at peak
  ✦ Body broader, more muscular
  ✦ Expression: calm, fox-like wisdom',
      ]);
    }

    // ── DACHSHUND ────────────────────────────────────────────────────────
    if ($this->mb($b, ['dachshund', 'doxie', 'sausage', 'wiener'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'long_low',
        'coat_type'           => 'short',
        'grows_significantly' => false,
        'size_note'           => 'Dachshunds: LEGS DO NOT GROW TALLER. Body grows longer and heavier.',
        'adult_body_note'     => 'Extremely elongated body, very short stubby legs. The iconic sausage silhouette. Weight 7–14 kg.',
        'adult_face_note'     => 'Long, tapered muzzle. Long, floppy ears. Confident, alert expression.',
        'aging_1yr'           => '  DACHSHUND 1-year changes:
  ✦ Body slightly longer and heavier — more barrel-chested
  ✦ Chest keel more prominent
  ✦ Face more defined adult muzzle
  ✦ Coat glossier and denser',
        'aging_3yr'           => '  DACHSHUND 3-year changes:
  ✦ Noticeably heavier body — the classic sausage shape is more pronounced
  ✦ Muzzle: gray hairs on chin and muzzle tip
  ✦ Face: more defined jowls, more settled expression
  ✦ Expression: calm, settled, adult Dachshund dignity',
      ]);
    }

    // ── BEAGLE ────────────────────────────────────────────────────────────
    if ($this->mb($b, ['beagle'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'stocky',
        'coat_type'           => 'short',
        'grows_significantly' => false,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Beagles grow moderately into a compact, sturdy hound.',
        'adult_body_note'     => 'Solid, muscular, compact. Deep chest, strong back. Weight 9–11 kg.',
        'adult_face_note'     => 'Classic hound face — long square muzzle, long floppy ears, large brown eyes.',
        'aging_1yr'           => '  BEAGLE 1-year changes:
  ✦ Body clearly more muscular and filled-out
  ✦ Chest deeper, stronger
  ✦ Face: muzzle more square and defined
  ✦ Coat glossier and denser',
        'aging_3yr'           => '  BEAGLE 3-year changes:
  ✦ Muzzle: gray/white hairs clearly visible on muzzle and chin
  ✦ Body heavier and more substantial
  ✦ Expression: classic hound wisdom — calm and experienced',
      ]);
    }

    // ── CHIHUAHUA ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['chihuahua'])) {
      return array_merge($default, [
        'size_category'       => 'toy',
        'body_shape'          => 'compact',
        'coat_type'           => 'short',
        'grows_significantly' => false,
        'size_note'           => 'Chihuahuas stay tiny. No dramatic size change.',
        'adult_body_note'     => 'Compact, fine-boned tiny body. Weight 1.5–3 kg.',
        'adult_face_note'     => 'Large rounded apple-dome skull. Large, fully erect ears. Large, round, luminous eyes.',
        'aging_1yr'           => '  CHIHUAHUA 1-year changes:
  ✦ Body: slightly more defined and compact — less puppy roundness
  ✦ Face: more defined bone structure, more adult expression
  ✦ Coat: smoother, glossier, more defined
  ✦ Eyes: still large but more mature and alert look',
        'aging_3yr'           => '  CHIHUAHUA 3-year changes:
  ✦ Face: slightly more defined cheekbones, adult bone structure very clear
  ✦ Expression: calm, confident, tiny but mighty — mature Chihuahua look
  ✦ A few gray hairs possible on chin if dark colored
  ✦ Coat: glossy and well-defined',
      ]);
    }

    // ── POMERANIAN ────────────────────────────────────────────────────────
    if ($this->mb($b, ['pomeranian'])) {
      return array_merge($default, [
        'size_category'       => 'toy',
        'body_shape'          => 'compact',
        'coat_type'           => 'curly/fluffy',
        'grows_significantly' => false,
        'gray_pattern'        => 'minimal',
        'size_note'           => 'Pomeranians stay very small. The characteristic rounded puff-ball shape develops more fully.',
        'adult_body_note'     => 'Tiny compact body completely hidden beneath a massive, rounded, double coat.',
        'adult_face_note'     => 'Fox-like face with sharp, pointed muzzle. Small, erect, triangular ears. Thick lion-like mane.',
        'aging_1yr'           => '  POMERANIAN 1-year changes:
  ✦ Coat: DRAMATICALLY bigger, fuller, rounder puff — the signature pom-pom coat fully developed
  ✦ Thick lion mane around neck VERY prominent
  ✦ Fox face more sharp and defined under the coat
  ✦ Expression: more confident and adult',
        'aging_3yr'           => '  POMERANIAN 3-year changes:
  ✦ Coat at absolute maximum fullness — the roundest, fluffiest version
  ✦ Face: more defined fox-like features
  ✦ Faint gray hairs on muzzle tip (minimal — Poms grey slowly)
  ✦ Expression: confident, settled adult',
      ]);
    }

    // ── SHIBA INU ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['shiba inu', 'shiba'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'compact',
        'coat_type'           => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern'        => 'minimal',
        'size_note'           => 'Shiba Inus grow into compact, fox-like small dogs. Moderate size increase.',
        'adult_body_note'     => 'Compact, well-muscled, agile. Thick double coat. Tightly curled tail. Weight 8–11 kg.',
        'adult_face_note'     => 'Fox-like — triangular head, small erect triangular ears, small almond eyes with distinctive markings.',
        'aging_1yr'           => '  SHIBA 1-year changes:
  ✦ Body more muscular and defined — prime Shiba physique
  ✦ Double coat thicker, richer, fuller
  ✦ Fox face more sharp and defined
  ✦ Tightly curled tail more prominent',
        'aging_3yr'           => '  SHIBA 3-year changes:
  ✦ Coat at peak — very thick, plush double coat
  ✦ Face: more defined, slightly more weathered
  ✦ Faint gray/white hairs on muzzle tip
  ✦ Expression: classic Shiba dignified aloofness — calmer and more settled',
      ]);
    }

    // ── YORKSHIRE TERRIER ─────────────────────────────────────────────────
    if ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
      return array_merge($default, [
        'size_category'       => 'toy',
        'body_shape'          => 'compact',
        'coat_type'           => 'long_silky',
        'grows_significantly' => false,
        'gray_pattern'        => 'prominent',
        'size_note'           => 'Yorkshire Terriers stay tiny. Long silky coat develops fully.',
        'adult_body_note'     => 'Very small, fine-boned, compact body hidden under long, silky, floor-length coat.',
        'adult_face_note'     => 'Small flat face. V-shaped, fully erect ears. Classic steel-blue and tan coloring.',
        'aging_1yr'           => '  YORKIE 1-year changes:
  ✦ Coat dramatically longer, silkier — steel-blue and tan more vivid
  ✦ More defined facial features visible through coat
  ✦ Expression: more confident and adult',
        'aging_3yr'           => '  YORKIE 3-year changes:
  ✦ Coat at full floor-length glory — very long, silky
  ✦ Steel-blue color well-established — richer, more defined
  ✦ Some silver/gray hairs visible around muzzle (Yorkies do gray)
  ✦ Expression: dignified adult terrier',
      ]);
    }

    // ── BORDER COLLIE ─────────────────────────────────────────────────────
    if ($this->mb($b, ['border collie'])) {
      return array_merge($default, [
        'size_category'       => 'medium',
        'body_shape'          => 'athletic',
        'coat_type'           => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern'        => 'prominent',
        'size_note'           => 'Border Collies grow into a lean, athletic medium dog.',
        'adult_body_note'     => 'Athletic, lithe, graceful. Lean muscle, not bulky. Weight 14–20 kg.',
        'adult_face_note'     => 'SIGNATURE: intense, intelligent, focused expression. Semi-erect forward-tipping ears.',
        'aging_1yr'           => '  BORDER COLLIE 1-year changes:
  ✦ Body lean and athletic — clearly muscular and fit
  ✦ Coat: rich, thick double coat fully developed
  ✦ The signature INTENSE, focused border collie gaze is fully present
  ✦ Overall: lean, elegant, athletic — peak working dog condition',
        'aging_3yr'           => '  BORDER COLLIE 3-year changes:
  ✦ Muzzle: noticeable gray/silver hairs — quite prominent for this breed
  ✦ Body at prime athletic peak — defined, lean, powerful
  ✦ Face: still the iconic intense gaze but with more depth and experience
  ✦ Coat richest and most lustrous version',
      ]);
    }

    // ── AUSTRALIAN SHEPHERD ───────────────────────────────────────────────
    if ($this->mb($b, ['australian shepherd', 'aussie'])) {
      return array_merge($default, [
        'size_category'       => 'medium',
        'body_shape'          => 'athletic',
        'coat_type'           => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern'        => 'prominent',
        'size_note'           => 'Australian Shepherds grow into a well-muscled medium dog.',
        'adult_body_note'     => 'Medium, muscular, agile, slightly longer than tall.',
        'adult_face_note'     => 'Balanced head. Striking eye colors possible. Semi-erect or rose ears.',
        'aging_1yr'           => '  AUSSIE 1-year changes:
  ✦ Body clearly muscular — strong chest, powerful hindquarters
  ✦ Double coat thicker and more lush
  ✦ More defined merle or tri-color patterns if applicable
  ✦ Alert, intelligent adult expression',
        'aging_3yr'           => '  AUSSIE 3-year changes:
  ✦ Muzzle gray/silver hairs clearly visible
  ✦ Body at physical prime — powerful and muscular
  ✦ Coat at peak beauty and density
  ✦ Expression: focused, experienced, mature herding dog wisdom',
      ]);
    }

    // ── ROTTWEILER ────────────────────────────────────────────────────────
    if ($this->mb($b, ['rottweiler'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'stocky',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'gray_pattern'        => 'minimal',
        'size_note'           => 'Rottweilers grow dramatically. Very dramatic size increase.',
        'adult_body_note'     => 'Massive, powerful, compact. Heavy bone, deep broad chest. Weight 35–60 kg.',
        'adult_face_note'     => 'Broad, powerful head. Strong wide muzzle. Drop ears. Calm, confident expression.',
        'aging_1yr'           => '  ROTTWEILER 1-year changes:
  ✦ Dramatically bigger — one of the most striking 1-year growths
  ✦ Massive chest and shoulders — very powerful
  ✦ Black and tan markings sharply defined
  ✦ Head broader and more square
  ✦ Expression: calm, powerful confidence',
        'aging_3yr'           => '  ROTTWEILER 3-year changes:
  ✦ Very massive, powerful, at maximum size
  ✦ Jowls heavier — slight droop visible
  ✦ Faint gray hairs on muzzle (minimal for this breed)
  ✦ Body: very thick-necked, barrel-chested
  ✦ Expression: calm, authoritative, experienced',
      ]);
    }

    // ── SIBERIAN HUSKY ────────────────────────────────────────────────────
    if ($this->mb($b, ['siberian husky', 'husky'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'double_coat',
        'grows_significantly' => true,
        'gray_pattern'        => 'none',
        'size_note'           => 'Huskies grow into medium-large dogs with a dense, lush double coat.',
        'adult_body_note'     => 'Medium-large, athletic, well-muscled. Thick double coat. Weight 16–27 kg.',
        'adult_face_note'     => 'Finely chiseled head. Almond eyes (blue, brown, or heterochromatic). Erect ears.',
        'aging_1yr'           => '  HUSKY 1-year changes:
  ✦ Body clearly larger and more athletic — powerful working dog physique
  ✦ Double coat DRAMATICALLY thicker and plushier
  ✦ Facial markings (if any) more vivid and defined
  ✦ Erect ears fully prominent',
        'aging_3yr'           => '  HUSKY 3-year changes:
  ✦ Double coat at absolute peak — thick, lustrous, richly colored
  ✦ Body powerful and athletic — prime sled dog condition
  ✦ Face: more defined, chiseled — striking, confident expression
  ✦ No graying (Huskies retain coat color very well)',
      ]);
    }

    // ── GREAT DANE ────────────────────────────────────────────────────────
    if ($this->mb($b, ['great dane'])) {
      return array_merge($default, [
        'size_category'       => 'giant',
        'body_shape'          => 'athletic',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Great Danes are the tallest breed. EXTREME growth. At 3yr: one of the largest dogs on Earth.',
        'adult_body_note'     => 'Enormous, powerful, elegant. Very long legs, deep massive chest. Weight 50–90 kg.',
        'adult_face_note'     => 'Large rectangular head. Strong muzzle. Noble expression.',
        'aging_1yr'           => '  GREAT DANE 1-year changes:
  ✦ DRAMATICALLY larger — legs very long, towering height, massive body
  ✦ Chest deeply developed
  ✦ Head larger and more powerful
  ✦ Noble, majestic bearing',
        'aging_3yr'           => '  GREAT DANE 3-year changes:
  ✦ Full giant size — one of the largest dogs alive
  ✦ Muzzle gray hairs visible — starts early in giant breeds
  ✦ Body fully filled out — massive, powerful, elegant
  ✦ Expression: noble, dignified, experienced',
      ]);
    }

    // ── BERNESE MOUNTAIN DOG ──────────────────────────────────────────────
    if ($this->mb($b, ['bernese mountain dog', 'bernese', 'berner'])) {
      return array_merge($default, [
        'size_category'       => 'giant',
        'body_shape'          => 'stocky',
        'coat_type'           => 'double_coat',
        'grows_significantly' => true,
        'size_note'           => 'Bernese Mountain Dogs grow into large, heavy, tri-colored mountain dogs.',
        'adult_body_note'     => 'Large, heavy, sturdy. Tricolor coat (black, white, rust). Weight 36–55 kg.',
        'adult_face_note'     => 'Broad flat skull. Tricolor face markings. Drop ears, dark brown eyes.',
        'aging_1yr'           => '  BERNER 1-year changes:
  ✦ Dramatically bigger — very large, heavy body
  ✦ Tricolor coat LUSH and fully developed
  ✦ White chest blaze and rust markings vivid and sharp
  ✦ Gentle, warm expression',
        'aging_3yr'           => '  BERNER 3-year changes:
  ✦ Full giant size — imposing, beautiful mountain dog
  ✦ Tricolor coat at peak richness and beauty
  ✦ Some gray possible on muzzle (giant breed aging)
  ✦ Expression: calm, gentle, majestic — settled mountain dog wisdom',
      ]);
    }

    // ── DALMATIAN ─────────────────────────────────────────────────────────
    if ($this->mb($b, ['dalmatian'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Dalmatians grow into a large, lean, muscular spotted dog — 23–27 kg.',
        'adult_body_note'     => 'Large, lean, muscular, elegant athletic body. Spots are crisp. Weight 23–27 kg.',
        'adult_face_note'     => 'Long, strong, refined head. Alert brown or blue eyes. Spotted drop ears.',
        'aging_1yr'           => '  DALMATIAN 1-year changes:
  ✦ Body much larger — lean, athletic, muscular
  ✦ Spots more crisp and clearly defined
  ✦ Coat glossier and shinier
  ✦ More confident, athletic bearing',
        'aging_3yr'           => '  DALMATIAN 3-year changes:
  ✦ Muzzle: gray/silver hairs visible around nose bridge and chin — clear
  ✦ Body at peak — lean, powerful, muscular
  ✦ Spots: vivid and crisp
  ✦ Expression: dignified, athletic, experienced',
      ]);
    }

    // ── BOXER ─────────────────────────────────────────────────────────────
    if ($this->mb($b, ['boxer'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'stocky',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'brachycephalic'      => true,
        'gray_pattern'        => 'moderate',
        'size_note'           => 'Boxers grow into muscular, powerful dogs.',
        'adult_body_note'     => 'Powerful, medium-large, square body. Well-muscled, deep chest. Weight 25–32 kg.',
        'adult_face_note'     => 'Broad, blunt, squarish muzzle. Strong underjaw. Wrinkled forehead. Alert expression.',
        'aging_1yr'           => '  BOXER 1-year changes:
  ✦ Body dramatically more muscular — classic Boxer powerful physique
  ✦ Chest very broad and defined
  ✦ Wrinkled forehead more prominent
  ✦ Expression: energetic, alert, powerful',
        'aging_3yr'           => '  BOXER 3-year changes:
  ✦ Muzzle: gray/white hairs clearly visible — Boxers are known to gray early
  ✦ Around eyes: silver framing hairs visible
  ✦ Body: very powerful, thick-necked, barrel-chested
  ✦ Wrinkles deeper on forehead
  ✦ Expression: settled, confident, distinguished',
      ]);
    }

    // ── MINIATURE SCHNAUZER ───────────────────────────────────────────────
    if ($this->mb($b, ['miniature schnauzer'])) {
      return array_merge($default, [
        'size_category'       => 'small',
        'body_shape'          => 'square',
        'coat_type'           => 'wire',
        'grows_significantly' => false,
        'gray_pattern'        => 'prominent',
        'size_note'           => 'Miniature Schnauzers stay small and square.',
        'adult_body_note'     => 'Square build. Compact, muscular, wiry-coated.',
        'adult_face_note'     => 'Rectangular strong head. SIGNATURE: long bushy eyebrows and very thick beard.',
        'aging_1yr'           => '  MINI SCHNAUZER 1-year changes:
  ✦ Beard and eyebrows DRAMATICALLY more prominent and bushy
  ✦ Wiry coat texture more defined and bristly
  ✦ Body compact and square — well-defined
  ✦ Expression: alert, terrier confidence',
        'aging_3yr'           => '  MINI SCHNAUZER 3-year changes:
  ✦ Beard and eyebrows even longer and more magnificent
  ✦ Pepper-and-salt pattern (if applicable) very vivid
  ✦ Face: classic Schnauzer wisdom — experienced, dignified expression
  ✦ More gray in the salt-and-pepper coloring (if applicable)',
      ]);
    }

    // ── STANDARD POODLE ───────────────────────────────────────────────────
    if ($this->mb($b, ['standard poodle'])) {
      return array_merge($default, [
        'size_category'       => 'large',
        'body_shape'          => 'athletic',
        'coat_type'           => 'curly/fluffy',
        'grows_significantly' => true,
        'gray_pattern'        => 'none',
        'size_note'           => 'Standard Poodles grow into elegant, tall, curly-coated dogs.',
        'adult_body_note'     => 'Elegant, well-proportioned, athletic. Squarely built, long neck. Weight 20–32 kg.',
        'adult_face_note'     => 'Long, straight, fine muzzle. Almond eyes, long flat ears. Refined, intelligent expression.',
        'aging_1yr'           => '  STANDARD POODLE 1-year changes:
  ✦ Curly coat dramatically denser and more lush
  ✦ Body taller and more elegant — refined athletic build
  ✦ Face: longer, more refined muzzle
  ✦ Expression: intelligent, elegant',
        'aging_3yr'           => '  STANDARD POODLE 3-year changes:
  ✦ Coat at absolute peak — richest, densest curls possible
  ✦ Elegant body at prime — graceful and powerful
  ✦ Refined, aristocratic adult face
  ✦ No graying (Poodles keep coat color)',
      ]);
    }

    // ── MIXED / ASPIN ─────────────────────────────────────────────────────
    if ($this->mb($b, ['aspin', 'askal', 'philippine', 'mixed', 'mongrel', 'mutt', 'crossbreed'])) {
      return array_merge($default, [
        'size_category'       => 'medium',
        'body_shape'          => 'athletic',
        'coat_type'           => 'short',
        'grows_significantly' => true,
        'size_note'           => 'Mixed breed dogs vary. Expect moderate growth into a lean, athletic adult.',
        'adult_body_note'     => 'Lean, athletic, well-proportioned medium body. Weight 10–25 kg.',
        'adult_face_note'     => 'Defined adult muzzle, alert, intelligent expression.',
      ]);
    }

    // ── FALLBACK ──────────────────────────────────────────────────────────
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
