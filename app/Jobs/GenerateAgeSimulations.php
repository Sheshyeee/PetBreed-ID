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

  private const SEND_SIZE = 768;
  private const MAX_SIZE  = 1024;

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
  //  IMPROVED AGE STAGE DETECTION
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
              'Study this dog photo carefully. Determine the PRECISE age stage based ONLY on physical anatomy.\n\n' .
                'PUPPY SIGNALS — if you see ANY TWO OR MORE of these → this is a puppy or teenager:\n' .
                '- Head appears disproportionately large relative to body length\n' .
                '- Paws appear oversized relative to legs and body\n' .
                '- Short or stumpy legs relative to torso height\n' .
                '- Rounded, barrel-shaped or potbelly abdomen\n' .
                '- Round, soft, chubby, baby-like facial structure\n' .
                '- Thin, wispy, fluffy or soft puppy coat (not thick adult coat)\n' .
                '- Short muzzle proportionally relative to skull\n' .
                '- Ears not yet fully settled or slightly oversized\n' .
                '- Lacks defined muscle mass on limbs and hindquarters\n' .
                '- White/no gray on muzzle or face\n\n' .
                'ADULT SIGNALS: Proportionate head to body, defined musculature, fully developed coat, settled ears, balanced muzzle length.\n' .
                'SENIOR SIGNALS: Gray/white on muzzle, around eyes, cloudy eyes, visible aging in skin/coat.\n\n' .
                'If this dog is a clear newborn/very young puppy (under 3 months) → reply: newborn_puppy\n' .
                'If a clear baby/puppy (3-5 months) → reply: puppy\n' .
                'If adolescent/growing fast (5-12 months) → reply: teenager\n' .
                'If young but mostly grown (1-2 years) → reply: young_adult\n' .
                'If fully mature prime (2-6 years) → reply: adult\n' .
                'If older/aging (7+ years, gray muzzle visible) → reply: senior\n\n' .
                'Reply with EXACTLY ONE PHRASE from the list above. No explanation whatsoever.',
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

      $valid = ['newborn_puppy', 'puppy', 'teenager', 'young_adult', 'adult', 'senior'];
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
  //  ENHANCED PROMPT BUILDER — PUPPY-AWARE + BREED-SPECIFIC + FORCEFUL
  // ─────────────────────────────────────────────────────────────────────

  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed        = $profile['breed'];
    $size         = $profile['size_category'] ?? 'medium';
    $coat         = $profile['coat_type'] ?? 'short';
    $isBrachy     = $profile['brachycephalic'] ?? false;
    $bodyShape    = $profile['body_shape'] ?? 'standard';
    $growthRate   = $profile['growth_rate'] ?? 'standard';   // fast / standard / slow
    $adultSizeDesc = $profile['adult_size_description'] ?? '';
    $ageStage     = $profile['detected_age_stage'] ?? 'adult';

    // Determine if this is a growing animal (major structural changes expected)
    $isGrowing = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager', 'young_adult']);
    $isPuppy   = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager']);
    $isSenior  = $ageStage === 'senior';

    // Pull breed-specific aging instructions from profile
    $body1yr  = $profile['aging_1yr_body'] ?? '';
    $face1yr  = $profile['aging_1yr_face'] ?? '';
    $coat1yr  = $profile['aging_1yr_coat'] ?? '';
    $body3yr  = $profile['aging_3yr_body'] ?? '';
    $face3yr  = $profile['aging_3yr_face'] ?? '';
    $coat3yr  = $profile['aging_3yr_coat'] ?? '';
    $heightChange = $profile['height_change'] ?? 'moderate_increase';

    // Puppy-specific dramatic changes
    $puppyTo1yr  = $profile['puppy_to_1yr'] ?? '';
    $puppyTo3yr  = $profile['puppy_to_3yr'] ?? '';

    // Use puppy-specific instructions if this is a young dog
    if ($isPuppy && !empty($puppyTo1yr) && $targetYears === 1) {
      $body1yr = $puppyTo1yr;
    }
    if ($isPuppy && !empty($puppyTo3yr) && $targetYears === 3) {
      $body3yr = $puppyTo3yr;
    }

    $L = [];
    $L[] = '╔══════════════════════════════════════════════════════════════════════╗';
    $L[] = '║       MANDATORY IMAGE TRANSFORMATION — BREED-ACCURATE AGING         ║';
    $L[] = '╚══════════════════════════════════════════════════════════════════════╝';
    $L[] = '';
    $L[] = "TARGET BREED: {$breed}";
    $L[] = "CURRENT AGE STAGE DETECTED: {$ageStage}";
    $L[] = "TRANSFORMATION TARGET: +{$targetYears} year(s) from the dog's CURRENT age";
    $L[] = "SIZE CLASS: {$size} | BODY: {$bodyShape} | COAT: {$coat}";
    $L[] = '';
    $L[] = '⚠️  CRITICAL INSTRUCTION: You MUST produce a VISUALLY STRIKING, UNDENIABLE ';
    $L[] = '    physical transformation. A subtle filter or minor color shift is a FAILURE.';
    $L[] = '    Every viewer must immediately see "this dog is clearly older."';
    $L[] = '';

    // ── PHASE 1: WHAT TO NEVER CHANGE ────────────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 1 — LOCKS (these MUST stay 100% identical):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  ✦ Background, environment, lighting, and all non-dog elements — IDENTICAL';
    $L[] = '  ✦ Coat base color and unique markings pattern — IDENTICAL';
    $L[] = '  ✦ Eye color — IDENTICAL';
    $L[] = '  ✦ General pose and orientation — IDENTICAL';
    $L[] = '  ✦ Camera angle and framing — IDENTICAL';
    $L[] = '';

    // ── PHASE 2: PUPPY OR ADULT — different logic ─────────────────────
    if ($isPuppy) {
      $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
      $L[] = "PHASE 2 — PUPPY DETECTED (current stage: {$ageStage}) — GROWTH PRIORITY";
      $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
      $L[] = "THIS IS A PUPPY. Puppies undergo the MOST DRAMATIC transformations.";
      $L[] = "The output MUST look like a COMPLETELY DIFFERENT SIZED ANIMAL.";
      $L[] = '';

      if ($targetYears === 1) {
        $L[] = "🔴🔴🔴 PUPPY → YOUNG ADULT (+1 year): EXTREME TRANSFORMATION REQUIRED 🔴🔴🔴";
        $L[] = '';
        // Height/size changes based on breed
        switch ($heightChange) {
          case 'dramatic_increase':
            $L[] = "HEIGHT: This breed grows DRAMATICALLY. The dog in the output MUST look 2-3× taller";
            $L[] = "        and much longer than the puppy. Legs must be long and fully extended.";
            break;
          case 'large_increase':
            $L[] = "HEIGHT: SIGNIFICANT height increase — the dog should look clearly 50-80% taller.";
            $L[] = "        Legs must be elongated and muscled compared to the stubby puppy legs.";
            break;
          case 'moderate_increase':
            $L[] = "HEIGHT: Moderate but very visible height increase — about 30-50% taller than the puppy.";
            $L[] = "        Body proportions shift from baby-round to leaner adolescent.";
            break;
          case 'minimal_increase':
            $L[] = "HEIGHT: This breed stays low/small — minimal height change. BUT body MUST become";
            $L[] = "        visibly longer, more muscular, and heavier. Eliminate all baby roundness.";
            break;
          case 'none':
            $L[] = "HEIGHT: This is a TOY/tiny breed — height barely changes. Instead:";
            $L[] = "        Body becomes compact and solid. Face sharpens. Coat becomes full adult texture.";
            break;
        }
        $L[] = '';
        $L[] = "STRUCTURAL CHANGES (MANDATORY — each one MUST be visible):";
        $L[] = "  • LEGS: Replace short stubby puppy legs with longer, muscled adult legs";
        $L[] = "  • BODY: Eliminate barrel/potbelly. Tuck the abdomen. Develop chest depth";
        $L[] = "  • FACE: Remove baby roundness. Elongate muzzle toward adult proportions";
        $L[] = "  • HEAD: Proportionally smaller relative to body (puppies have oversized heads)";
        $L[] = "  • PAWS: No longer oversized — must look proportionate to grown legs";
        $L[] = "  • COAT: Replace soft wispy puppy fuzz with denser, harsher adult coat texture";
        $L[] = '';
        if (!empty($body1yr)) {
          $L[] = "BREED-SPECIFIC ({$breed}) 1-YEAR CHANGES:";
          $L[] = "  {$body1yr}";
        }
        if (!empty($face1yr)) {
          $L[] = "  {$face1yr}";
        }
        if (!empty($coat1yr)) {
          $L[] = "  {$coat1yr}";
        }
      } else { // 3 years
        $L[] = "🔴🔴🔴 PUPPY → FULL ADULT (+3 years): MAXIMUM TRANSFORMATION REQUIRED 🔴🔴🔴";
        $L[] = '';
        $L[] = "THREE YEARS FROM NOW this puppy is a FULLY GROWN ADULT. The transformation must be";
        $L[] = "so dramatic that it looks like a completely adult dog of this breed.";
        $L[] = '';

        if (!empty($adultSizeDesc)) {
          $L[] = "WHAT THIS BREED LOOKS LIKE AS FULL ADULT: {$adultSizeDesc}";
          $L[] = "The output MUST match this adult description precisely.";
          $L[] = '';
        }

        switch ($heightChange) {
          case 'dramatic_increase':
            $L[] = "HEIGHT: Must be at FULL ADULT HEIGHT — 3-4× taller than the puppy shown.";
            $L[] = "        This is one of the biggest/tallest breeds. Make it look truly massive.";
            break;
          case 'large_increase':
            $L[] = "HEIGHT: FULL ADULT HEIGHT — clearly 2-3× taller than puppy. Large powerful dog.";
            break;
          case 'moderate_increase':
            $L[] = "HEIGHT: Full adult proportions — 1.5-2× taller than puppy. Lean adult physique.";
            break;
          case 'minimal_increase':
            $L[] = "HEIGHT: Low-profile breed at full length — heavy, wide, and dense adult body.";
            break;
          case 'none':
            $L[] = "HEIGHT: Tiny adult — same small height but FULLY defined adult coat and face.";
            break;
        }
        $L[] = '';
        $L[] = "MANDATORY ADULT FEATURES (ALL must be clearly present):";
        $L[] = "  • FULL ADULT MUSCULATURE: Defined muscles on shoulders, haunches, and chest";
        $L[] = "  • COMPLETE COAT: Full adult coat density and texture — no puppy fuzz remains";
        $L[] = "  • ADULT HEAD: Proportionate skull, fully developed muzzle, defined stop";
        $L[] = "  • ADULT BODY: Deep chest, tucked abdomen (or breed-appropriate belly), settled topline";
        $L[] = "  • ADULT LEGS: Fully elongated, muscled, proportionate — no stubby puppy legs";
        if (!empty($body3yr)) {
          $L[] = '';
          $L[] = "BREED-SPECIFIC ({$breed}) 3-YEAR CHANGES:";
          $L[] = "  {$body3yr}";
        }
        if (!empty($face3yr)) {
          $L[] = "  {$face3yr}";
        }
        if (!empty($coat3yr)) {
          $L[] = "  {$coat3yr}";
        }
      }
    } elseif ($isSenior) {
      // Already senior — subtle but realistic changes
      $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
      $L[] = "PHASE 2 — SENIOR DOG (current stage: senior) — ADVANCED AGING";
      $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

      if ($targetYears === 1) {
        $L[] = "CHANGES FOR +1 YEAR ON A SENIOR DOG:";
        $L[] = "  • Expand the gray/white fur on muzzle, chin, and around eyes noticeably";
        $L[] = "  • Eyes should appear slightly cloudier or more tired-looking";
        $L[] = "  • Skin may show slightly more sagging under jowls and neck";
        $L[] = "  • Coat may appear coarser and slightly thinner in spots";
        $L[] = "  • Posture may reflect slight stiffness — subtle forward lean";
        if (!empty($body1yr)) $L[] = "  • {$body1yr}";
        if (!empty($face1yr)) $L[] = "  • {$face1yr}";
      } else {
        $L[] = "CHANGES FOR +3 YEARS ON A SENIOR DOG:";
        $L[] = "  • HEAVY GRAYING: White/silver fur must cover the ENTIRE muzzle, full chin, and eye areas";
        $L[] = "  • Eyes visibly cloudier with age-related opacity";
        $L[] = "  • Jowls and neck skin significantly sagged and wrinkled";
        $L[] = "  • Coat noticeably thinner and duller — less vibrant than current";
        $L[] = "  • Visible body condition change — either slightly thinner or less muscled";
        if (!empty($body3yr)) $L[] = "  • {$body3yr}";
        if (!empty($face3yr)) $L[] = "  • {$face3yr}";
      }
    } else {
      // Adult or young adult — mature aging
      $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
      $L[] = "PHASE 2 — ADULT DOG (current stage: {$ageStage}) — MATURITY PROGRESSION";
      $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

      if ($targetYears === 1) {
        $L[] = "TARGET: This dog is +1 year older. Changes should be CLEARLY VISIBLE but realistic.";
        $L[] = '';
        if (!empty($body1yr)) {
          $L[] = "🔴 BODY/STRUCTURE: {$body1yr}";
        }
        if (!empty($face1yr)) {
          $L[] = "🔴 FACE/HEAD: {$face1yr}";
        }
        if (!empty($coat1yr)) {
          $L[] = "🔴 COAT: {$coat1yr}";
        }
        $L[] = '';
        $L[] = "If current age is young_adult: develop more muscle mass, thicken neck and chest.";
        $L[] = "If current age is adult: begin muzzle silvering, slight body settling.";
      } else {
        $L[] = "TARGET: This dog is +3 years older. Changes must be DRAMATICALLY OBVIOUS.";
        $L[] = '';
        if (!empty($body3yr)) {
          $L[] = "🔴🔴 BODY/STRUCTURE: {$body3yr}";
        }
        if (!empty($face3yr)) {
          $L[] = "🔴🔴 FACE/HEAD: {$face3yr}";
        }
        if (!empty($coat3yr)) {
          $L[] = "🔴🔴 COAT: {$coat3yr}";
        }
      }
    }

    $L[] = '';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 3 — BREED BIOLOGY GUARDRAILS (breed-specific anatomy rules):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

    if ($bodyShape === 'long_low') {
      $L[] = "  LONG-AND-LOW BREED RULE: Legs NEVER grow taller regardless of age.";
      $L[] = "  Body grows LONGER and HEAVIER, chest drops lower, torso widens — never taller.";
    }
    if ($isBrachy) {
      $L[] = "  BRACHYCEPHALIC BREED RULE: Flat/pushed-in face is PERMANENT and NEVER elongates.";
      $L[] = "  Age shows through: deeper facial wrinkles, sagging jowls/flews, widened skull.";
    }
    if ($bodyShape === 'sighthound') {
      $L[] = "  SIGHTHOUND BREED RULE: Always retains lean, aerodynamic physique. Never becomes fat.";
      $L[] = "  Adult aging shows through: deeper chest, more defined muscle striations, slight coat change.";
    }
    if ($size === 'giant') {
      $L[] = "  GIANT BREED RULE: Full adult size is massive. Puppy transformation to adult must be enormous.";
      $L[] = "  Adult aging shows early: gray muzzle can appear by 5-6 years.";
    }
    if ($size === 'toy' || $size === 'small') {
      $L[] = "  SMALL/TOY BREED RULE: Height changes minimally after 6 months.";
      $L[] = "  Aging is shown through: coat texture change, face sharpening, muzzle graying late in life.";
    }

    // Coat type specific aging
    switch ($coat) {
      case 'double_coat':
        $L[] = "  DOUBLE COAT BREED: Adult coat must be visibly denser and more voluminous than puppy coat.";
        $L[] = "  Develop the 'ruff' or mane if breed has one. Guard hairs must appear coarser.";
        break;
      case 'wire':
      case 'wire_harsh':
        $L[] = "  WIRE COAT BREED: Puppy coat is softer. Adult wire coat must look NOTICEABLY harsher,";
        $L[] = "  coarser, and bristly. Face furnishings (beard/eyebrows) should appear more prominent.";
        break;
      case 'long_silky':
        $L[] = "  LONG SILKY COAT BREED: Adult coat must be noticeably longer, fully flowing and settled.";
        break;
      case 'curly':
      case 'wavy_curly':
        $L[] = "  CURLY/WAVY COAT BREED: Adult curl must be tighter and more defined than puppy fluff.";
        $L[] = "  Volume increases with age. Texture becomes more consistent.";
        break;
    }

    $L[] = '';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 4 — QUALITY GATE (check this before finishing):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "  ✓ Does the output dog look UNDENIABLY {$targetYears} year(s) older than the input?";
    $L[] = "  ✓ Would a dog owner immediately recognize this as their dog, grown up?";
    $L[] = "  ✓ Are all the breed-specific changes from Phase 2 and 3 visibly present?";
    $L[] = "  ✓ Is the background/environment completely unchanged?";
    $L[] = "  ✗ If the output looks almost the same as the input — YOU HAVE FAILED. Redo.";
    $L[] = "  ✗ If you only changed colors without structural changes — YOU HAVE FAILED. Redo.";
    $L[] = '';
    $L[] = "EXECUTE THE TRANSFORMATION NOW. Output ONLY the transformed image.";

    return implode("\n", $L);
  }

  // ─────────────────────────────────────────────────────────────────────
  //  IMAGE PREPARATION — UPSCALE SMALL IMAGES
  // ─────────────────────────────────────────────────────────────────────

  private function prepareImage(string $fullPath): ?array
  {
    try {
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

        $origW   = $info[0];
        $origH   = $info[1];
        $longest = max($origW, $origH);

        if ($longest < self::SEND_SIZE) {
          $targetSize    = self::SEND_SIZE;
          $imageContents = $this->scaleImage($imageContents, $targetSize);
          Log::info("📐 Upscaled {$origW}x{$origH} → longest side {$targetSize}px");
        } elseif ($longest > self::MAX_SIZE) {
          $targetSize    = self::MAX_SIZE;
          $imageContents = $this->scaleImage($imageContents, $targetSize);
          Log::info("📐 Downscaled {$origW}x{$origH} → longest side {$targetSize}px");
        }

        $scaledInfo = @getimagesizefromstring($imageContents);
        $sendW      = $scaledInfo ? $scaledInfo[0] : $origW;
        $sendH      = $scaledInfo ? $scaledInfo[1] : $origH;

        $img = @imagecreatefromstring($imageContents);
        if (!$img) throw new \Exception('GD cannot parse image');
        ob_start();
        imagejpeg($img, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        Log::info("✅ Image ready: {$sendW}x{$sendH} (orig {$origW}x{$origH}) — " . round(strlen($jpeg) / 1024, 1) . ' KB');

        return [
          'base64'     => base64_encode($jpeg),
          'mimeType'   => 'image/jpeg',
          'sendWidth'  => $sendW,
          'sendHeight' => $sendH,
          'origWidth'  => $origW,
          'origHeight' => $origH,
          'saveWidth'  => $sendW,
          'saveHeight' => $sendH,
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

  private function saveImage(string $imageOutput, string $type, $resultId): ?string
  {
    try {
      $img = @imagecreatefromstring($imageOutput);
      if (!$img) throw new \Exception('GD cannot parse output image');

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
  //  COMPREHENSIVE BREED PROFILES
  //  Each breed now has:
  //  - height_change: dramatic_increase / large_increase / moderate_increase / minimal_increase / none
  //  - adult_size_description: what the fully grown dog looks like
  //  - puppy_to_1yr / puppy_to_3yr: special puppy-specific instructions
  //  - aging_1yr_body/face/coat: adult-to-older changes
  //  - aging_3yr_body/face/coat: adult-to-much-older changes
  // ─────────────────────────────────────────────────────────────────────

  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    // ── DEFAULT BASE ──────────────────────────────────────────────────
    $profile = [
      'breed'                => $breed,
      'size_category'        => 'medium',
      'body_shape'           => 'standard',
      'coat_type'            => 'short',
      'brachycephalic'       => false,
      'growth_rate'          => 'standard',
      'height_change'        => 'moderate_increase',
      'adult_size_description' => 'A medium-sized adult dog with well-developed musculature and a fully settled adult coat.',
      // Puppy-specific
      'puppy_to_1yr'         => 'DRAMATIC SIZE INCREASE: Lengthen legs by 50-80%, widen and deepen chest, tuck abdomen to eliminate potbelly, sharpen facial structure significantly. Coat transitions from soft puppy fuzz to denser adult coat.',
      'puppy_to_3yr'         => 'FULL ADULT TRANSFORMATION: Full adult height and muscle mass. Deep chest, tucked waist, fully developed head and muzzle. Coat is completely adult in texture and density.',
      // Adult aging
      'aging_1yr_body'       => 'Slightly increase muscle density on chest and hindquarters. Body looks marginally more settled.',
      'aging_1yr_face'       => 'Very slight sharpening of facial features. Muzzle tip may show 1-2 silver hairs.',
      'aging_1yr_coat'       => 'Coat becomes marginally denser and more defined in texture.',
      'aging_3yr_body'       => 'Noticeably thicker neck and chest. Hindquarters more developed. Body looks heavier and settled.',
      'aging_3yr_face'       => 'VISIBLE GRAYING: Distinct silver/white hairs covering muzzle tip, chin, and sparse around eyes.',
      'aging_3yr_coat'       => 'Coat is fully matured — denser and coarser. Slightly less glossy than in youth.',
    ];

    // ══════════════════════════════════════════════════════════════════
    // GIANT BREEDS
    // ══════════════════════════════════════════════════════════════════
    if ($this->mb($b, ['great dane', 'irish wolfhound', 'saint bernard', 'newfoundland', 'leonberger', 'mastiff', 'great pyrenees', 'anatolian'])) {
      $profile['size_category']          = 'giant';
      $profile['height_change']          = 'dramatic_increase';
      $profile['growth_rate']            = 'fast';
      $profile['adult_size_description'] = 'One of the largest dog breeds — a towering, massively built adult standing 28-35 inches tall with enormous bone structure, broad skull, and imposing physical presence.';
      $profile['puppy_to_1yr']           = 'ENORMOUS SIZE EXPLOSION: This giant breed grows faster than any other. Legs must be DRAMATICALLY longer — at least 2-3× the puppy leg length. Chest must be wide and deep. Head skull broadens massively. Total body size should look 3-4× larger than the puppy. This is one of the most dramatic puppy-to-adult transformations in the animal kingdom.';
      $profile['puppy_to_3yr']           = 'COLOSSAL ADULT: Full adult giant size — enormous skeleton, massive head, deep barrel chest, powerful haunches. The dog should look IMPOSING and gigantic compared to the tiny puppy. This is an apex of canine size.';
      $profile['aging_1yr_body']         = 'HEAVY MASS INCREASE: Thicken the neck significantly, broaden the chest into a massive barrel. Shoulders widen visibly.';
      $profile['aging_1yr_face']         = 'Skull broadens slightly. Jowls/flews become more pronounced. Wrinkles deepen.';
      $profile['aging_3yr_body']         = 'PEAK POWER: Neck is now enormously thick, chest is a barrel, hindquarters are massive. The dog looks like an immovable force.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING: Clear silver/white on entire muzzle, chin, and around eyes. Giant breeds gray early. Deep facial creases.';
      $profile['brachycephalic']         = $this->mb($b, ['mastiff', 'saint bernard', 'leonberger']);
    }

    // ══════════════════════════════════════════════════════════════════
    // LARGE WORKING / SHEPHERD BREEDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['german shepherd', 'belgian malinois', 'dutch shepherd', 'belgian tervuren', 'belgian laekenois'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A powerful, athletic dog standing 22-26 inches tall — wolf-like, lean-muscled, with a dense double coat, perfectly erect ears, and a long confident stride. Alert intelligent expression.';
      $profile['puppy_to_1yr']           = 'WOLF-LIKE EMERGENCE: Legs must lengthen dramatically to athletic adult proportions. Erect ears — if flopped as puppy, they must now stand PERFECTLY upright and rigid. Coat transitions from puppy fluff to sleek, dense double coat with visible saddle/blanket pattern. Face elongates to wolf-like adult snout. Body becomes lean and muscular with visible hindquarter development.';
      $profile['puppy_to_3yr']           = 'FULL WORKING DOG: Peak physical condition. Dense adult double coat with clear black saddle/blanket. Long wolf-like muzzle. Perfectly erect pointed ears. Deep chest, athletic waist tuck, powerful haunches. Looks like a military working dog in prime condition.';
      $profile['aging_1yr_body']         = 'Hindquarters muscle definition increases. Chest drops slightly deeper. Back becomes more defined.';
      $profile['aging_1yr_face']         = 'Face sharpens. Mask pattern intensifies. Slight muzzle tip silvering begins.';
      $profile['aging_3yr_body']         = 'Full muscle development — especially rear angulation. Chest is deep and prominent. Coat is at full density.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING: Silver/white hairs clearly visible on muzzle tip, chin, and above eyes. Mask slightly fades at edges.';
      $profile['aging_3yr_coat']         = 'Double coat at maximum density — thick undercoat visible at neck and chest. Guard hairs coarser.';
    } elseif ($this->mb($b, ['siberian husky', 'alaskan malamute', 'samoyed', 'akita', 'shiba inu', 'chow chow', 'keeshond', 'spitz'])) {
      $profile['size_category']          = $this->mb($b, ['malamute', 'akita']) ? 'large' : 'medium';
      $profile['coat_type']              = 'double_coat';
      $profile['body_shape']             = 'spitz';
      $profile['height_change']          = $this->mb($b, ['malamute', 'akita']) ? 'large_increase' : 'moderate_increase';
      $profile['adult_size_description'] = 'A Nordic-type dog with a thick plush double coat, erect pointed ears, curled tail carried over the back, and a compact powerful build. Regal, wolf-like appearance with striking mask or facial markings.';
      $profile['puppy_to_1yr']           = 'ARCTIC WOLF TRANSFORMATION: Replace wispy puppy fluff with an ENORMOUS thick double coat — visibly denser, plush, and stand-off. Erect ears become rigid and pointed. Tail curls firmly over back. Face develops adult mask pattern more intensely. Body becomes compact and muscular.';
      $profile['puppy_to_3yr']           = 'FULL ARCTIC ADULT: Peak double coat — massive ruff/mane, thick undercoat visible at neck and flanks, guard hairs coarse and dense. Face has full adult mask with dramatic eye markings. Curled tail perfectly formed. Powerful compact build.';
      $profile['aging_1yr_body']         = 'Coat reaches full adult volume — ruff develops prominently at neck. Body becomes more muscular and compact.';
      $profile['aging_1yr_face']         = 'Mask pattern sharpens. Eye markings become more defined. Face structure becomes more angular.';
      $profile['aging_3yr_body']         = 'At peak physical condition. Ruff/mane is full and impressive. Coat at maximum density.';
      $profile['aging_3yr_face']         = 'SUBTLE SILVERING on muzzle edges. Mask pattern may slightly fade at borders. Eyes remain piercing.';
    } elseif ($this->mb($b, ['border collie', 'australian shepherd', 'aussie'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A lithe, athletic herding dog with a medium-length double coat, alert eyes (often merle or blue), and an intense focused expression. Lean muscle, tucked waist, and agile build.';
      $profile['puppy_to_1yr']           = 'HERDING DOG ATHLETE: Body lengthens and legginess increases significantly. Coat transitions from puppy fluff to flowing adult coat with feathering on legs and chest. Merle/color pattern intensifies. Face sharpens to the intense herding expression. Ears settle (rose or semi-erect). Body lean and athletic.';
      $profile['puppy_to_3yr']           = 'PEAK HERDING ATHLETE: Full adult coat with flowing mane, feathering on legs/tail. Alert intelligent expression. Lean, agile body with clear waist tuck. Eyes fully bright and intense (possible blue/merle eyes prominent).';
      $profile['aging_1yr_body']         = 'Body develops lean herding muscle. Coat fills out with mane and feathering. Waist tuck becomes defined.';
      $profile['aging_3yr_body']         = 'Athletic prime. Full coat length and feathering. Body looks agile and capable.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING — these breeds gray slowly. Slight silver at muzzle tip only. Expression remains intensely alert.';
    } elseif ($this->mb($b, ['collie', 'rough collie', 'smooth collie', 'sheltie', 'shetland sheepdog'])) {
      $profile['size_category']          = $this->mb($b, ['sheltie', 'shetland']) ? 'small' : 'large';
      $profile['coat_type']              = $this->mb($b, ['smooth']) ? 'short' : 'long_silky';
      $profile['height_change']          = $this->mb($b, ['sheltie', 'shetland']) ? 'moderate_increase' : 'large_increase';
      $profile['adult_size_description'] = 'A strikingly elegant dog with a long flowing mane and frill, narrow aristocratic head with a long pointed snout, and rich sable/tricolor/merle coat flowing down the entire body.';
      $profile['puppy_to_1yr']           = 'REGAL EMERGENCE: The most dramatic change is the coat — replace puppy fluff with a growing FLOWING MANE around neck and chest, flowing saddle coat on body. Long muzzle elongates significantly toward the adult narrow aristocratic shape. Body becomes taller and more elegant.';
      $profile['puppy_to_3yr']           = 'FULL LASSIE ADULT: Enormous flowing mane and frill, long silky coat down flanks and tail, aristocratic narrow head fully developed. Rich sable or tricolor pattern at full adult intensity.';
      $profile['aging_3yr_body']         = 'Mane and frill at maximum volume. Body lean and elegant.';
      $profile['aging_3yr_face']         = 'SLIGHT GRAYING around muzzle tip. Narrow elegant face maintains youth well.';
    }

    // ══════════════════════════════════════════════════════════════════
    // GOLDEN / LABRADOR / RETRIEVERS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['golden retriever'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large, well-proportioned dog with a thick golden flowing coat, broad head, soft intelligent eyes, deep chest, and feathering on legs, chest, and tail. Coat is rich golden color from cream to dark gold.';
      $profile['puppy_to_1yr']           = 'GOLDEN TRANSFORMATION: Legs lengthen significantly (very leggy adolescent phase). Coat transitions from puppy fluff to developing GOLDEN WAVES — feathering begins on chest, legs, and tail. Head broadens. Body becomes tall and slightly gangly before muscling up. Rich golden color intensifies.';
      $profile['puppy_to_3yr']           = 'FULL GOLDEN ADULT: Lush, flowing golden coat at full length with prominent feathering on chest, belly, legs, and tail. Broad adult head with soft eyes. Deep chest, well-muscled body. Rich golden coat — one of the most recognizable dogs in the world.';
      $profile['aging_1yr_body']         = 'Coat developing feathering on chest and legs. Body muscling up. Chest deepens.';
      $profile['aging_1yr_face']         = 'Head broadens. Soft, warm expression deepens. Coat around face and ears lengthens.';
      $profile['aging_3yr_body']         = 'Full flowing coat at mature length. Chest is broad and deep. Body well-muscled and balanced.';
      $profile['aging_3yr_face']         = 'EARLY MUZZLE GRAYING: Golden retrievers gray early. Clear silver/white on muzzle tip and chin. Facial coat still golden but may lighten slightly.';
      $profile['aging_3yr_coat']         = 'Coat at peak length and lustre — richly golden with full feathering everywhere.';
    } elseif ($this->mb($b, ['labrador retriever', 'labrador', 'lab'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large, athletic dog with a broad otter-like tail, dense short water-resistant coat (black, yellow, or chocolate), broad head, deep chest, and powerful build. One of the most recognizable breeds.';
      $profile['puppy_to_1yr']           = 'LABRADOR GROWTH BURST: Massive increase in size. Legs become long and powerful. Otter tail (thick at base, tapering) must be clearly visible and prominent. Chest broadens and deepens dramatically. Short coat becomes denser and water-repellent. Head broadens with adult Lab expression.';
      $profile['puppy_to_3yr']           = 'FULL LAB ADULT: Classic Labrador build — broad head, short dense coat, prominent otter tail, powerful stocky body, deep chest. Well-muscled. The archetypal family dog physique.';
      $profile['aging_1yr_body']         = 'Body fills out to classic Lab stockiness. Otter tail fully developed. Chest drops deeper.';
      $profile['aging_3yr_body']         = 'Full Lab bulk — neck thick, chest barrel-like, body powerful and heavy. Some may begin to show slight weight gain around abdomen.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING: Clear silver/gray on muzzle tip and chin. Broad lab face slightly more jowled.';
    }

    // ══════════════════════════════════════════════════════════════════
    // COCKER SPANIELS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['cocker spaniel', 'english cocker', 'american cocker'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A compact, sturdy spaniel with long luxurious silky coat and feathering, long pendulous ears framing a domed head, soft melting expression, and deep rich coat coloring (black, golden, parti-color, etc.).';
      $profile['puppy_to_1yr']           = 'SPANIEL BLOSSOMING: The most dramatic change is COAT GROWTH. Long silky feathering begins flowing from ears, chest, belly, and legs. Ears become longer and more pendulous with silky fringe. Head domes more. Body becomes compact but not taller — grow LONGER flowing coat everywhere.';
      $profile['puppy_to_3yr']           = 'FULL SHOW COCKER: Enormous flowing silky coat — full feathering covering chest, belly, all four legs, and tail. Long pendulous ear leather with flowing fringe nearly to ground. Domed head, soft intelligent expression. Rich coat color at full depth.';
      $profile['aging_1yr_body']         = 'Coat develops full adult feathering — ears, chest, legs all showing silky flow. Body compact and sturdy.';
      $profile['aging_1yr_coat']         = 'Silky coat developing full adult waves and feathering everywhere.';
      $profile['aging_3yr_body']         = 'Body at peak spaniel form — compact, balanced. Coat at maximum length.';
      $profile['aging_3yr_face']         = 'SLIGHT GRAYING on muzzle. Long ear leather maintains color. Soft expression deepens.';
      $profile['aging_3yr_coat']         = 'Maximum feathering length — flowing silky coat everywhere. Rich deep color.';
    }

    // ══════════════════════════════════════════════════════════════════
    // POODLES (standard / miniature / toy)
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['standard poodle', 'poodle'])) {
      $isStandard  = $this->mb($b, ['standard']);
      $isMini      = $this->mb($b, ['miniature', 'mini']);
      $profile['size_category']   = $isStandard ? 'large' : ($isMini ? 'small' : 'medium');
      $profile['coat_type']       = 'curly';
      $profile['height_change']   = $isStandard ? 'large_increase' : ($isMini ? 'moderate_increase' : 'none');
      $profile['adult_size_description'] = $isStandard
        ? 'A tall, elegant dog with a dense curly coat that stands 21-27 inches. Athletic and graceful with a long refined head, pendant ears, and tight curly coat of solid color.'
        : 'A smaller compact poodle with the same dense curly coat, refined head, and elegant build of the standard, in a smaller package.';
      $profile['puppy_to_1yr']    = 'POODLE ADULT COAT EXPLOSION: The single most dramatic change is the COAT. Replace wispy puppy fluff with TIGHT, DENSE, UNIFORM CURLS covering the entire body. ' . ($isStandard ? 'Body grows significantly taller and more leggy. ' : '') . 'Head develops refined long angular shape. Ears with tight curls. The coat must look distinctly "poodle" — dense and sculptable.';
      $profile['puppy_to_3yr']    = 'FULL POODLE ADULT: Entire body covered in tight dense curls at full adult length. Refined elegant head. ' . ($isStandard ? 'Tall athletic body. ' : 'Compact elegant body. ') . 'Coat is single solid color throughout — no puppy softness remaining anywhere.';
      $profile['aging_3yr_face']  = 'Refined head maintained with minimal graying. Slight silvering possible at muzzle in darker colors.';
      $profile['aging_3yr_coat']  = 'Curls reach maximum density and uniformity. Coat texture is at its finest.';
    }

    // ══════════════════════════════════════════════════════════════════
    // DOODLE HYBRIDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['goldendoodle', 'labradoodle', 'bernedoodle', 'aussiedoodle', 'sheepadoodle', 'saint berdoodle', 'newfypoo', 'pyredoodle'])) {
      $isLarge = $this->mb($b, ['standard', 'large', 'bernedoodle', 'sheepadoodle', 'saint berdoodle', 'newfypoo']);
      $profile['size_category']   = $isLarge ? 'large' : 'medium';
      $profile['coat_type']       = 'wavy_curly';
      $profile['height_change']   = $isLarge ? 'large_increase' : 'moderate_increase';
      $profile['adult_size_description'] = 'A large fluffy doodle with a thick wavy or curly coat, broad retriever/poodle hybrid head, and teddy bear appearance. Coat is voluminous with soft waves or loose curls throughout.';
      $profile['puppy_to_1yr']    = 'DOODLE COAT EMERGENCE: Replace puppy fluff with THICK WAVY/CURLY adult coat — dramatically more voluminous, wavy, and full. Body grows significantly ' . ($isLarge ? 'taller and heavier. ' : 'taller. ') . 'Teddy bear facial appearance sharpens — muzzle becomes more defined under the fluffy facial hair. Beard and eyebrows become more prominent.';
      $profile['puppy_to_3yr']    = 'FULL DOODLE ADULT: Enormous fluffy wavy coat everywhere. ' . ($isLarge ? 'Large powerful body hidden under waves of fur. ' : 'Compact body under full coat. ') . 'Classic teddy bear doodle look at full expression — full beard, expressive eyes framed by fur, tail of flowing waves.';
      $profile['aging_3yr_coat']  = 'Coat at maximum volume — rich waves covering entire dog. Dense and fluffy throughout.';
      $profile['aging_3yr_face']  = 'Slight graying possible on muzzle through the facial fur. Otherwise well-maintained youthful appearance.';
    } elseif ($this->mb($b, ['cockapoo', 'cavapoo', 'maltipoo', 'schnoodle', 'yorkipoo', 'chipoo', 'havapoo', 'pomapoo', 'shihpoo', 'shi-poo', 'poogle', 'jackapoo', 'corgipoo', 'westiepoo', 'cairnoodle'])) {
      $profile['size_category']   = 'small';
      $profile['coat_type']       = 'wavy_curly';
      $profile['height_change']   = 'none';
      $profile['adult_size_description'] = 'A small teddy bear doodle with a dense wavy or curly coat, expressive face with beard/eyebrows, and compact body. Height barely changes but coat transforms dramatically.';
      $profile['puppy_to_1yr']    = 'SMALL DOODLE TRANSFORMATION — HEIGHT BARELY CHANGES. Instead: COAT EXPLODES into adult waves/curls — much denser and more voluminous. Beard and facial furnishings grow prominently. Body becomes compact and solid. Face sharpens under adult coat.';
      $profile['puppy_to_3yr']    = 'FULL SMALL DOODLE: Dense curly/wavy coat at full adult length. Prominent beard. Compact solid body. Expressive face framed by adult furnishings. Classic small teddy bear doodle look.';
      $profile['aging_3yr_coat']  = 'Dense wavy coat at maximum adult length and volume.';
      $profile['aging_3yr_face']  = 'Possible slight graying in facial furnishings. Remains teddy-bear cute.';
    }

    // ══════════════════════════════════════════════════════════════════
    // ROTTWEILER / DOBERMANN / BOXERS / LARGE MOLOSSER
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['rottweiler', 'rottie'])) {
      $profile['size_category']          = 'large';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A powerful, blocky dog — massive square head with broad flat skull, prominent tan/mahogany points on black coat, thick heavily muscled neck, broad chest, and overall impression of raw power and solidity.';
      $profile['puppy_to_1yr']           = 'ROTTWEILER MASS EXPLOSION: This breed develops extreme mass. Neck becomes thick and powerful. Head SQUARES OFF — loses all puppy roundness and becomes a massive broad block. Tan/mahogany points intensify dramatically. Chest broadens to barrel-like. Legs become thick-boned columns of muscle. Overall impression is of a very powerful, intimidating dog emerging.';
      $profile['puppy_to_3yr']           = 'FULL ROTTWEILER POWER: Enormous blocky square head, thick powerful neck, massive chest and shoulders, columnar legs. Clear defined tan/mahogany points. This is one of the most muscular and powerful-looking of all breeds at full development.';
      $profile['aging_3yr_body']         = 'PEAK POWER: Maximum muscle development. Neck enormous. Chest barrel-like. Completely solid and imposing.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING: Clear gray/white on muzzle and chin. Square head has deepened wrinkles. Jowls more prominent.';
    } elseif ($this->mb($b, ['doberman', 'dobermann', 'doberman pinscher'])) {
      $profile['size_category']          = 'large';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A sleek, elegant, powerful dog — long elegant neck, square body, sleek short coat showing every muscle, elegant pointed head with rust markings, either cropped erect ears or natural folded ears. The definition of athletic elegance.';
      $profile['puppy_to_1yr']           = 'DOBERMAN SLEEK EMERGENCE: Body elongates and becomes HIGHLY ATHLETIC and lean — show every muscle line under the short coat. Neck lengthens elegantly. Rust/tan markings intensify sharply. Head narrows to elegant adult shape. Legs become long and lean. Overall impression of a sleek, chiseled athlete emerges dramatically.';
      $profile['puppy_to_3yr']           = 'FULL DOBERMAN ATHLETE: Sleek, chiseled, every muscle visible under the short tight coat. Long elegant neck. Narrow refined head. Clean rust markings. Squared body proportions. This is the most athletic-appearing breed at full development.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING on muzzle. Refined face maintained with slight aging around eyes. Markings stable.';
    } elseif ($this->mb($b, ['boxer'])) {
      $profile['size_category']          = 'large';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A muscular, square-built dog with a broad brachycephalic head, undershot jaw with prominent flews, fawn or brindle short coat with white flash markings, and powerful athletic build.';
      $profile['puppy_to_1yr']           = 'BOXER POWERHOUSE DEVELOPMENT: Body masses up dramatically — chest broadens to a square barrel, neck thickens powerfully, legs become thick and muscular. FLAT FACE remains flat — do NOT elongate. Wrinkles deepen on broad forehead. Undershot jaw and flews become more pronounced. White flash markings sharpen.';
      $profile['puppy_to_3yr']           = 'FULL BOXER ADULT: Classic square boxer — wide flat head, undershot jaw with full flews, deep wrinkled forehead, massive square barrel chest, white flash markings fully defined. Powerful, bouncy physique. Alert and expressive.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING: White/gray heavily salted across entire muzzle, chin, and forehead. Wrinkles deepen. Flat face maintained.';
    } elseif ($this->mb($b, ['pit bull', 'pitbull', 'american pit bull', 'american staffordshire', 'staffordshire bull terrier', 'staffy', 'amstaff'])) {
      $profile['size_category']          = 'medium';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A medium-sized, incredibly muscular dog — broad blocky head, powerful neck and chest, extremely well-defined muscle striations throughout the body, smooth short coat.';
      $profile['puppy_to_1yr']           = 'MUSCLE MACHINE DEVELOPMENT: Even at 1 year, this breed is already showing extraordinary muscle definition. Head SQUARES OFF and broadens dramatically. Neck thickens. Chest broadens to be wider than the head. Muscle striations become visible through the short coat on shoulders, chest, and hindquarters.';
      $profile['puppy_to_3yr']           = 'FULL POWER PHYSIQUE: The most visibly muscular of all breeds at maturity. Blocky broad head, thick neck, barrel chest visibly wider than the waist. Extreme muscle definition visible everywhere — deltoids, pectorals, quadriceps. Short coat shows every muscle.';
      $profile['aging_3yr_body']         = 'PEAK MUSCLE: Maximum visible muscle definition. Shoulders, chest, and hindquarters rippled with muscle.';
      $profile['aging_3yr_face']         = 'LIGHT GRAYING: Slight silver on muzzle tip. Broad face with deepened jaw muscles.';
    }

    // ══════════════════════════════════════════════════════════════════
    // SIGHTHOUNDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['whippet', 'greyhound', 'italian greyhound', 'saluki', 'afghan hound', 'borzoi', 'azawakh', 'sloughi'])) {
      $profile['size_category']          = $this->mb($b, ['italian greyhound']) ? 'small' : 'medium';
      $profile['body_shape']             = 'sighthound';
      $profile['coat_type']              = $this->mb($b, ['afghan hound', 'borzoi', 'saluki']) ? 'long_silky' : 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'The ultimate athletic dog — an aerodynamic silhouette with extreme deep chest tuck, visible ribs and spine (normal for breed), long neck, narrow refined head, and extraordinary lean muscled physique.';
      $profile['puppy_to_1yr']           = 'SIGHTHOUND METAMORPHOSIS: One of the most dramatic puppy-to-adult transformations. The round puppy completely disappears. Replace with an EXTREMELY lean, angular, almost greyhound-like silhouette. Deep chest tuck creates a dramatic waist. Legs become impossibly long and lean. Visible rib cage outline (completely normal). Long narrow head fully elongated. Rose ears flatten. Overall aerodynamic silhouette.';
      $profile['puppy_to_3yr']           = 'FULL SIGHTHOUND ADULT: Peak aerodynamic physique — the deepest chest tuck of any breed, visible ribs/waist, extremely long lean legs, narrow elongated head, rose or folded ears. ' . ($this->mb($b, ['afghan hound', 'borzoi', 'saluki']) ? 'Long flowing silky coat at full length.' : 'Sleek tight coat showing every lean muscle.') . ' Breathtakingly elegant and athletic.';
      $profile['aging_3yr_body']         = 'Peak athletic form maintained — this breed ages gracefully. Deep tuck, long lean lines.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING — sighthounds maintain youth well. Perhaps 1-2 silver hairs at muzzle tip. Eyes remain bright and keen.';
    }

    // ══════════════════════════════════════════════════════════════════
    // FRENCH BULLDOG / ENGLISH BULLDOG / PUG / BRACHYCEPHALIC SMALL
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['french bulldog', 'frenchie'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A compact, muscular small dog with an extremely flat face, large bat-like ears, stocky barrel body, screw tail, and smooth coat. Broad square head with deep forehead wrinkles.';
      $profile['puppy_to_1yr']           = 'FRENCHIE ADULT EMERGENCE — HEIGHT DOES NOT CHANGE. Instead: Bat ears if not already erect MUST now stand PERFECTLY upright and rigid. Head BROADENS significantly. Body becomes STOCKY and muscular — thick neck, barrel chest, no waist tuck. Deep facial wrinkles develop. Screw tail more defined. Flat face remains flat — wrinkles around nose deepen.';
      $profile['puppy_to_3yr']           = 'FULL FRENCH BULLDOG: Classic Frenchie physique — perfectly erect bat ears, extremely broad flat head with deep forehead wrinkles, compact square stocky barrel body, no neck. Short smooth coat over muscular body. Quintessential bulldog look.';
      $profile['aging_3yr_body']         = 'Stockier and more muscular. Barrel chest even more prominent.';
      $profile['aging_3yr_face']         = 'Deep facial wrinkles — especially across forehead and around flat nose. Slight gray possible on muzzle wrinkles. Bat ears remain erect.';
    } elseif ($this->mb($b, ['english bulldog', 'british bulldog', 'bulldog'])) {
      $profile['size_category']          = 'medium';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'A massively built, extremely heavy dog for its size — enormous head wider than it is tall, skin that hangs in heavy folds and wrinkles especially around face and neck, massive chest and short bowed legs, rope nose wrinkle.';
      $profile['puppy_to_1yr']           = 'BULLDOG MASS EXPANSION: Body becomes MASSIVELY heavy for its height. Enormous head develops — broad, square, with hanging flews and heavy facial wrinkles and jowls. Neck develops DEEP skin folds (dewlap). Chest widens to extreme width. Rose ears flatten. Short sturdy legs. Rope wrinkle above flat nose.';
      $profile['puppy_to_3yr']           = 'FULL BULLDOG: One of the most distinctive adult physiques — enormous wrinkled head with heavy jowls, deep dewlap neck folds, massive barrel body on short bowed legs. Heavy flews draped over lower jaw. Full rope wrinkle. Unmistakably bulldog.';
      $profile['aging_3yr_body']         = 'Even heavier and more settled. Deeper wrinkles everywhere. Dewlap more pronounced.';
      $profile['aging_3yr_face']         = 'Deep rope wrinkle above nose, heavy jowls. HEAVY GRAYING across entire muzzle and chin. Deep wrinkled forehead.';
    } elseif ($this->mb($b, ['pug'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A small compact dog with an extremely wrinkled flat face, large round eyes, deep facial creases/forehead wrinkles, curly tail, and cobby square body with fine fawn or black coat.';
      $profile['puppy_to_1yr']           = 'PUG ADULT — HEIGHT BARELY CHANGES. FACE transforms most: deep forehead WRINKLES multiply and deepen, facial folds around flat nose become pronounced, large round eyes become more prominent, curly tail tightens. Body becomes COBBY and compact — square, solid, no waist tuck. Deep black mask intensifies.';
      $profile['puppy_to_3yr']           = 'FULL PUG ADULT: Deeply wrinkled flat face with multiple forehead folds, prominent large round eyes, deep nose rope, black mask stark and defined, compact square cobby body, tight double-curl tail. Classic pug perfection.';
      $profile['aging_3yr_face']         = 'GRAYING: Clear gray/white hairs appearing in the black facial mask area. Wrinkles deepen further. Eyes maintain prominence.';
    }

    // ══════════════════════════════════════════════════════════════════
    // YORKSHIRE TERRIER / SMALL TERRIERS / MALTESE
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A tiny dog with an impossibly long, fine, silky steel-blue and tan coat that parts down the middle and flows to the floor, perfectly erect small V-shaped ears, and a confident terrier personality.';
      $profile['puppy_to_1yr']           = 'YORKIE COAT TRANSFORMATION — HEIGHT BARELY CHANGES. The ENTIRE visual transformation is the COAT. Replace puppy fluff with GROWING LONG SILKY STRAIGHT COAT — blue/steel grey on body, bright tan on face and legs. Hair must be growing visibly longer and silkier. Erect ears must be firmly upright. Face becomes more terrier-like.';
      $profile['puppy_to_3yr']           = 'FULL YORKIE: Long flowing silky coat parted down the middle reaching toward the floor — steel blue on body, rich tan/gold on head and legs. Perfectly erect tiny pointed ears. Confident expression. One of the most beautiful toy breed coats at maturity.';
      $profile['aging_3yr_coat']         = 'Coat at full adult length — silky, flowing, perfectly straight. Blue and tan pattern at full intensity.';
      $profile['aging_3yr_face']         = 'Slight lightening of tan/gold on face is possible. Minimal graying for this breed at 3 years.';
    } elseif ($this->mb($b, ['maltese'])) {
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A tiny all-white dog completely covered in a flowing, silky, pure white coat that reaches the ground — like a living cloud with a black button nose and dark eyes peeking through flowing white hair.';
      $profile['puppy_to_1yr']           = 'MALTESE COAT EXPLOSION — HEIGHT STAYS SAME. Pure white coat grows dramatically longer and silkier — flowing EVERYWHERE. Eyes peek through longer facial hair. Body completely hidden under flowing white silk. Face remains compact. Tiny but magnificently coated.';
      $profile['puppy_to_3yr']           = 'FULL MALTESE: Entire body hidden under floor-length flowing pure white silk coat. Only the black nose and dark eyes visible under flowing facial hair. The coat IS the breed — maximize it.';
      $profile['aging_3yr_coat']         = 'Floor-length flowing pure white silk — at maximum adult length and luster.';
    } elseif ($this->mb($b, ['shih tzu', 'shih-tzu'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A small chrysanthemum-faced dog with a long luxurious flowing coat in any color, flat pushed-in face with long facial hair that grows upward, large dark eyes, and flowing silky coat covering the entire body.';
      $profile['puppy_to_1yr']           = 'SHIH TZU COAT DEVELOPMENT — HEIGHT STAYS SAME. Coat grows MUCH LONGER and SILKIER — flowing body coat, long facial hair that grows upward from the flat nose area. Flat face remains. Round large eyes more prominent. Top-knot area hair grows long. Colors intensify.';
      $profile['puppy_to_3yr']           = 'FULL SHIH TZU: Magnificent long flowing coat covering entire body. Facial hair long and flowing outward from flat face. Large round eyes. Full chrysanthemum face expression. Classic show Shih Tzu look.';
      $profile['aging_3yr_coat']         = 'Coat at maximum flowing length — silky and rich in whatever color pattern.';
    } elseif ($this->mb($b, ['chihuahua'])) {
      $isLongCoat = $this->mb($b, ['long coat', 'long-coat', 'longhaired']);
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = $isLongCoat ? 'long_silky' : 'short';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'The world\'s smallest breed — tiny body with an apple-domed skull, large prominent dark eyes, large erect ears, and either a smooth short coat or a soft flowing longer coat. Despite tiny size, has a confident, bold expression.';
      $profile['puppy_to_1yr']           = 'CHIHUAHUA ADULT — HEIGHT BARELY CHANGES. Head ROUNDS into classic apple-dome skull shape. Large erect ears must stand perfectly upright and rigid — no longer flopped or semi-erect. Eyes become proportionally very large and round. Body becomes compact. ' . ($isLongCoat ? 'Coat grows into longer flowing feathering.' : 'Short coat becomes sleek and tight.');
      $profile['puppy_to_3yr']           = 'FULL CHIHUAHUA: Classic apple-head — perfectly round domed skull, enormous erect pointed ears, very large round dark eyes, compact tiny body. Bold alert expression. Coat fully developed.';
      $profile['aging_3yr_face']         = 'Slight deepening of expressions. Ears remain fully erect. Minimal graying for this long-lived breed.';
    } elseif ($this->mb($b, ['pomeranian'])) {
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A tiny fluffy lion-like dog with an enormous stand-off double coat that creates a ball of fur, foxy pointed face, tiny erect ears, thick full mane/ruff, heavily plumed tail curled over back.';
      $profile['puppy_to_1yr']           = 'POMERANIAN FLOOF EXPLOSION — HEIGHT STAYS SAME. The ENTIRE transformation is the COAT. Replace puppy fluff with an ENORMOUS stand-off double coat — massive ruff/mane around neck, thick coat standing away from body. Foxy pointed face becomes more angular and fox-like. Tail becomes heavily plumed and curled over back. Tiny erect ears.';
      $profile['puppy_to_3yr']           = 'FULL POMERANIAN: Ball of fluff — enormous stand-off double coat twice the body size in volume. Full lion-like mane, heavily plumed tail, foxy pointed face, tiny erect ears. Looks like a miniature lion/fox.';
      $profile['aging_3yr_coat']         = 'Coat at maximum volume and stand-off — the most voluminous coat relative to body size of any breed.';
    }

    // ══════════════════════════════════════════════════════════════════
    // CORGI / DACHSHUND / BASSET — LONG AND LOW
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      $profile['size_category']          = 'small';
      $profile['body_shape']             = 'long_low';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'A long low dog with short powerful legs, a very long muscular torso, large upright bat-like ears that must stand erect, foxy face, and a dense double coat with a prominent mane/ruff.';
      $profile['puppy_to_1yr']           = 'CORGI LOW-RIDER ADULT: LEGS DO NOT GROW TALL — they stay SHORT and POWERFUL. Body LENGTHENS significantly. Large pointed ears must stand PERFECTLY ERECT and rigid (not flopped). Dense double coat develops with ruff at neck. Foxy face becomes more defined. Body becomes long, muscular, and stocky on those characteristic short legs.';
      $profile['puppy_to_3yr']           = 'FULL CORGI: Classic long-and-low silhouette — very long muscular body on short stumpy legs, large upright pointed ears, dense double coat with ruff, foxy face. The signature Corgi silhouette unmistakable.';
      $profile['aging_3yr_body']         = 'Body heavier and more settled on those short legs. Ruff at full volume.';
      $profile['aging_3yr_face']         = 'SLIGHT GRAYING on muzzle. Foxy face maintained. Ears remain fully erect.';
    } elseif ($this->mb($b, ['dachshund', 'doxie', 'sausage dog'])) {
      $isLong     = $this->mb($b, ['long', 'longhaired', 'long-haired']);
      $isWire     = $this->mb($b, ['wire', 'wirehaired', 'wire-haired']);
      $isMini     = $this->mb($b, ['mini', 'miniature']);
      $profile['size_category']          = $isMini ? 'toy' : 'small';
      $profile['body_shape']             = 'long_low';
      $profile['coat_type']              = $isLong ? 'long_silky' : ($isWire ? 'wire_harsh' : 'short');
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'The ultimate long-and-low dog — a dramatically elongated sausage-shaped body on tiny short legs. Deep chest, very long torso.';
      $profile['puppy_to_1yr']           = 'DACHSHUND SAUSAGE EMERGENCE — HEIGHT NEVER INCREASES. Body ELONGATES dramatically into the signature sausage shape. Deep chest develops. Very short stubby legs remain short. ' . ($isLong ? 'Long silky coat develops with feathering on ears and underside.' : ($isWire ? 'Wire coat becomes harsh and bristly with beard.' : 'Short coat tightens.')) . ' Deep chest becomes very prominent.';
      $profile['puppy_to_3yr']           = 'FULL DACHSHUND: Maximum sausage — very long body, tiny legs, deep prominent chest, long low silhouette. ' . ($isLong ? 'Full long silky coat with feathering.' : ($isWire ? 'Full harsh wire coat with beard/eyebrows.' : 'Tight smooth coat.'));
      $profile['aging_3yr_face']         = 'SLIGHT GRAYING on muzzle. Face maintains characteristic hound expression.';
    } elseif ($this->mb($b, ['basset hound'])) {
      $profile['size_category']          = 'medium';
      $profile['body_shape']             = 'long_low';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'An extremely heavy, low-slung dog — enormously long velvety ears reaching past the nose, deeply wrinkled skin, large soulful eyes with prominent haw/red showing, deep chest almost touching ground, heavy bone.';
      $profile['puppy_to_1yr']           = 'BASSET HEAVY DEVELOPMENT: Ears GROW DRAMATICALLY LONGER — they should now reach or nearly touch the ground when lowered. Skin develops DEEP WRINKLES and folds around face, neck, and legs. Eyes show more haw (red inner eyelid). Body becomes very low and heavy. Dewlap neck folds appear.';
      $profile['puppy_to_3yr']           = 'FULL BASSET HOUND: Enormously long velvet ears, deeply wrinkled skin everywhere, very heavy low body, soulful drooping eyes. One of the most distinctive adult silhouettes.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING on muzzle and around eyes. Deeper wrinkles. More prominent haw.';
    }

    // ══════════════════════════════════════════════════════════════════
    // SCHNAUZER
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['giant schnauzer', 'standard schnauzer', 'miniature schnauzer', 'schnauzer'])) {
      $isGiant = $this->mb($b, ['giant']);
      $isMini  = $this->mb($b, ['miniature', 'mini']);
      $profile['size_category']   = $isGiant ? 'large' : ($isMini ? 'small' : 'medium');
      $profile['coat_type']       = 'wire_harsh';
      $profile['height_change']   = $isGiant ? 'large_increase' : ($isMini ? 'none' : 'moderate_increase');
      $profile['adult_size_description'] = 'A distinctive square-built dog with a harsh wire coat, prominent beard and eyebrows, rectangular head, erect ears' . ($isGiant ? ', and large powerful build.' : '.');
      $profile['puppy_to_1yr']    = 'SCHNAUZER WIRE COAT EMERGENCE: Replace soft puppy coat with HARSH BRISTLY WIRE COAT. BEARD and EYEBROWS become very prominent — this is the breed\'s signature. Rectangular head develops. Body becomes square and muscular. Salt-and-pepper pattern sharpens.';
      $profile['puppy_to_3yr']    = 'FULL SCHNAUZER: Full harsh wire coat with PROMINENT BEARD and BUSHY EYEBROWS at maximum development. Square body, rectangular head. Salt-and-pepper or solid color at full adult intensity.';
      $profile['aging_3yr_coat']  = 'Wire coat at full harsh texture. Beard and eyebrows at maximum volume.';
      $profile['aging_3yr_face']  = 'Salt-and-pepper pattern may intensify with more gray. Beard may develop more gray/white. Distinguished appearance.';
    }

    // ══════════════════════════════════════════════════════════════════
    // BORDER TERRIER / JACK RUSSELL / TERRIERS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['jack russell', 'parson russell', 'russell terrier'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = $this->mb($b, ['wire', 'rough']) ? 'wire_harsh' : 'short';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'A compact, athletic small terrier — mostly white with tan/black patches, strong square-jawed head, almond eyes with intense expression, sturdy compact body.';
      $profile['puppy_to_1yr']           = 'JACK RUSSELL ADULT: Body becomes compact, muscular, and solid. Distinct white with tan/black patches intensify. Head SQUARES OFF into adult terrier shape. Eyes develop intense, bold expression. ' . ($this->mb($b, ['wire', 'rough']) ? 'Wire coat becomes harsh and bristly with facial furnishings.' : 'Short coat becomes tight and defined.');
      $profile['puppy_to_3yr']           = 'FULL JACK RUSSELL: Classic compact athletic terrier — squared head, bold eyes, white/tan/black markings fully defined, strong muscular body.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING — terriers maintain youth well. Perhaps slight silver on muzzle tip.';
    } elseif ($this->mb($b, ['west highland', 'westie', 'cairn terrier', 'scottish terrier', 'scottie', 'border terrier', 'norfolk terrier', 'norwich terrier'])) {
      $profile['size_category']   = 'small';
      $profile['coat_type']       = 'wire_harsh';
      $profile['height_change']   = 'minimal_increase';
      $profile['puppy_to_1yr']    = 'TERRIER WIRE COAT DEVELOPMENT: Most dramatic change is COAT. Replace puppy softness with HARSH WIRE COAT — rough, bristly texture. Characteristic BEARD and EYEBROWS (if breed has them) become prominent. Face sharpens to distinctive terrier square jaw.';
      $profile['puppy_to_3yr']    = 'FULL WIRE TERRIER: Complete harsh wire coat, prominent facial furnishings, square terrier head. Bold confident expression.';
    } elseif ($this->mb($b, ['airedale terrier', 'airedale'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'wire_harsh';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'The largest terrier — a tall, athletic dog with a harsh black-and-tan wire coat, long flat rectangular head, V-shaped drop ears, distinctive beard, and athletic upright carriage.';
      $profile['puppy_to_1yr']           = 'KING OF TERRIERS EMERGES: Grows into a LARGE ATHLETIC DOG. Harsh wire coat replaces puppy coat — black saddle/blanket with rich tan. BEARD and EYEBROWS develop prominently. Long rectangular head fully forms. Body becomes tall and athletic. V-shaped ears drop properly.';
      $profile['puppy_to_3yr']           = 'FULL AIREDALE: Large, athletic, elegant terrier with full harsh wire coat. Black saddle over rich tan body. Full beard. Tall confident stance.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING on beard and muzzle. Tan may lighten slightly. Dignified mature expression.';
    }

    // ══════════════════════════════════════════════════════════════════
    // BEAGLE / HOUNDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['beagle'])) {
      $profile['size_category']          = 'small';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A compact, sturdy scent hound — tricolor (black/tan/white) or two-color with a square hound head, long pendulous soft ears, deep chest, and friendly hound expression.';
      $profile['puppy_to_1yr']           = 'BEAGLE ADULT FORM: Body becomes sturdy and compact. Tricolor markings INTENSIFY dramatically — black saddle deepens, tan points sharpen, white brightens. Ears lengthen and become more pendulous. Head develops square hound proportions. Deep chest develops.';
      $profile['puppy_to_3yr']           = 'FULL BEAGLE: Classic scent hound — deep tricolor, long pendulous ears, square compact body, deep chest, friendly hound expression.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING on muzzle. Hound expression deepens. Ears maintain pendulous hang.';
    } elseif ($this->mb($b, ['bloodhound', 'coonhound', 'redbone', 'bluetick', 'black and tan'])) {
      $profile['size_category']          = 'large';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large, heavy scent hound with deeply wrinkled skin especially on face and forehead, enormously long pendulous ears, prominent dewlap, large drooping eyes with red haw visible, and heavy bone.';
      $profile['puppy_to_1yr']           = 'HOUND BULK DEVELOPMENT: Body becomes LARGE and heavy. Skin develops DEEP WRINKLES on forehead and around face. Ears grow VERY LONG and pendulous. Dewlap neck folds appear. Large drooping hound eyes with visible red haw. Color patterns (black/tan, red, etc.) intensify.';
      $profile['puppy_to_3yr']           = 'FULL HOUND ADULT: Deeply wrinkled face, enormously long ears, heavy bone, powerful large body. Classic working hound appearance.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING on muzzle. Deep wrinkles deepen further. Even more soulful expression.';
    }

    // ══════════════════════════════════════════════════════════════════
    // HUSKY / MALAMUTE specific (already in spitz above but more specific)
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['husky', 'siberian husky'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A medium-large athletic working dog with a thick plush double coat, dramatic facial mask markings, possible blue or heterochromatic eyes, and a wolf-like appearance.';
      $profile['puppy_to_1yr']           = 'HUSKY PACK DOG EMERGES: Thick double coat develops fully — dense undercoat with guard hairs. Dramatic FACIAL MASK intensifies and becomes crisp and defined. If blue eyes — they must become even more striking and vivid. Body becomes athletic and moderately muscled. Erect ears.';
      $profile['puppy_to_3yr']           = 'FULL HUSKY ADULT: Dense plush double coat at full volume. Dramatic face mask at full intensity. Vivid eye color. Athletic lean-muscled body. Wolf-like appearance.';
      $profile['aging_3yr_coat']         = 'Double coat at full density and plushness. Mask remains striking.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING — Huskies maintain striking appearance well. Mask crisp, eyes vivid.';
    }

    // ══════════════════════════════════════════════════════════════════
    // ASPIN / NATIVE DOGS / MIXED BREEDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['aspin', 'asong pinoy', 'philippine native', 'village dog', 'street dog', 'mixed breed', 'mutt', 'mixed'])) {
      $profile['size_category']   = 'medium';
      $profile['height_change']   = 'moderate_increase';
      $profile['adult_size_description'] = 'A lean, athletic medium dog with smooth short coat, semi-erect or erect ears, sickle tail, and the lithe build of a primitive pariah dog.';
      $profile['puppy_to_1yr']    = 'NATIVE DOG MATURATION: Body becomes lean and athletic — visible tuck-up, moderate musculature. Ears settle (semi-erect or erect). Short coat tightens and becomes sleeker. Legs lengthen to lean adult proportions. Sickle/upright tail more defined. Adult primitive dog silhouette.';
      $profile['puppy_to_3yr']    = 'FULL ADULT NATIVE DOG: Lean athletic medium dog with tight short coat, well-defined lean musculature, settled ears, sickle tail. Primitive/pariah dog appearance.';
      $profile['aging_3yr_face']  = 'SLIGHT GRAYING on muzzle. Lean athletic appearance maintained.';
    }

    return $profile;
  }

  // ─────────────────────────────────────────────────────────────────────
  //  HELPER
  // ─────────────────────────────────────────────────────────────────────

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
