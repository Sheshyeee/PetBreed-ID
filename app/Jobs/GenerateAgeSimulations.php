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

  private const SEND_SIZE = 896;
  private const MAX_SIZE  = 1280;

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

      $breedProfile                          = $this->getBreedProfile($this->breed);
      $breedProfile['detected_age_stage']    = $currentAgeStage;

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
              'Examine this dog photo with forensic precision. Determine the EXACT developmental age stage.\n\n' .
                'PUPPY SIGNALS — count how many you see:\n' .
                '- Head disproportionately large relative to body length\n' .
                '- Paws appear oversized relative to legs and body\n' .
                '- Short or stumpy legs relative to torso height\n' .
                '- Rounded, barrel-shaped or potbelly abdomen\n' .
                '- Round, soft, chubby, baby-like facial structure\n' .
                '- Thin, wispy, fluffy or soft puppy coat (not thick adult coat)\n' .
                '- Short muzzle proportionally relative to skull\n' .
                '- Ears partially or fully flopped (not yet settled or erect)\n' .
                '- Lack of defined muscle mass on limbs and hindquarters\n' .
                '- No gray/white anywhere on muzzle or face\n' .
                '- Overall "baby animal" appearance with soft curves\n\n' .
                'If 3+ puppy signals present → this is a puppy or teenager.\n\n' .
                'ADULT SIGNALS: Proportionate head-to-body ratio, defined adult musculature, fully developed breed-standard coat, settled ears, balanced muzzle.\n' .
                'SENIOR SIGNALS: Gray/white muzzle, cloudy eyes, skin sagging, thinner/duller coat.\n\n' .
                'CLASSIFICATIONS:\n' .
                'newborn_puppy = under 3 months (tiny, very round, barely mobile)\n' .
                'puppy = 3-6 months (clear puppy proportions, playful)\n' .
                'teenager = 6-12 months (leggy, awkward proportions, adolescent)\n' .
                'young_adult = 1-2 years (mostly grown but still maturing)\n' .
                'adult = 2-7 years (fully mature, prime condition)\n' .
                'senior = 7+ years (visible aging signs)\n\n' .
                'Reply with EXACTLY ONE of these words, nothing else:\n' .
                'newborn_puppy | puppy | teenager | young_adult | adult | senior',
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
        'temperature'        => $isThinkingModel ? 0.20 : 0.30,
        'topK'               => $isThinkingModel ? 32 : 40,
        'topP'               => $isThinkingModel ? 0.80 : 0.85,
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
  //  ★★★ COMPLETELY REDESIGNED PROMPT BUILDER ★★★
  //  Uses ACTION-DIRECTIVE structure instead of descriptive paragraphs.
  //  Much more specific, forceful, and breed-accurate.
  // ─────────────────────────────────────────────────────────────────────

  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed      = $profile['breed'];
    $size       = $profile['size_category']     ?? 'medium';
    $coat       = $profile['coat_type']          ?? 'short';
    $isBrachy   = $profile['brachycephalic']     ?? false;
    $bodyShape  = $profile['body_shape']         ?? 'standard';
    $ageStage   = $profile['detected_age_stage'] ?? 'adult';
    $heightChg  = $profile['height_change']      ?? 'moderate_increase';
    $adultDesc  = $profile['adult_size_description'] ?? '';

    $isPuppy     = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager']);
    $isYoung     = $ageStage === 'young_adult';
    $isSenior    = $ageStage === 'senior';
    $isGrowing   = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager', 'young_adult']);

    // Pull breed-specific aging instructions
    $body1yr  = $profile['aging_1yr_body']  ?? '';
    $face1yr  = $profile['aging_1yr_face']  ?? '';
    $coat1yr  = $profile['aging_1yr_coat']  ?? '';
    $body3yr  = $profile['aging_3yr_body']  ?? '';
    $face3yr  = $profile['aging_3yr_face']  ?? '';
    $coat3yr  = $profile['aging_3yr_coat']  ?? '';
    $puppy1yr = $profile['puppy_to_1yr']    ?? '';
    $puppy3yr = $profile['puppy_to_3yr']    ?? '';

    // Determine transformation magnitude label
    if ($isPuppy && $targetYears === 3)      $magnitude = 'MAXIMUM (puppy → full adult)';
    elseif ($isPuppy && $targetYears === 1)  $magnitude = 'VERY HIGH (puppy → young adult)';
    elseif ($isYoung)                        $magnitude = 'HIGH (young adult → mature adult)';
    elseif ($isSenior)                       $magnitude = 'MODERATE (senior → older senior)';
    else                                     $magnitude = $targetYears === 3 ? 'HIGH (adult aging)' : 'MODERATE (1 year maturity)';

    $L = [];

    // ── HEADER ──────────────────────────────────────────────────────
    $L[] = '╔══════════════════════════════════════════════════════════════════════════╗';
    $L[] = '║          PHOTO TRANSFORMATION: DOG AGE PROGRESSION                       ║';
    $L[] = '╚══════════════════════════════════════════════════════════════════════════╝';
    $L[] = '';
    $L[] = "BREED: {$breed}";
    $L[] = "CURRENT STATE: {$ageStage}";
    $L[] = "TARGET: Age this dog by +{$targetYears} year(s)";
    $L[] = "TRANSFORMATION MAGNITUDE: {$magnitude}";
    $L[] = '';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  SECTION A — LOCKED ELEMENTS (these MUST remain 100% identical):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  • Background, floor, furniture, environment — PIXEL PERFECT, DO NOT ALTER';
    $L[] = '  • Coat base color and unique markings pattern — DO NOT ALTER';
    $L[] = '  • Eye color — DO NOT ALTER';
    $L[] = '  • Pose and body orientation — DO NOT ALTER';
    $L[] = '  • Camera framing and angle — DO NOT ALTER';
    $L[] = '';

    // ── SECTION B: TRANSFORMATION ACTIONS ───────────────────────────
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  SECTION B — TRANSFORMATION DIRECTIVES (EXECUTE EVERY SINGLE ONE):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';

    // ── PUPPY TRANSFORMATIONS ────────────────────────────────────────
    if ($isPuppy) {
      $L[] = "  ⚡ PUPPY DETECTED — This requires the MOST DRAMATIC transformation possible.";
      $L[] = "     A subtle change is a FAILURE. Every viewer must immediately see a different-aged dog.";
      $L[] = '';

      if ($targetYears === 1) {
        $L[] = "  📋 PUPPY → +1 YEAR: EXECUTE THESE CHANGES:";
        $L[] = '';

        // Height/size changes
        switch ($heightChg) {
          case 'dramatic_increase':
            $L[] = "  ① BODY SIZE: MASSIVELY ENLARGE the dog. Legs must be 2.5–3× longer than now.";
            $L[] = "               Body must be 3–4× larger overall. This breed becomes GIANT.";
            break;
          case 'large_increase':
            $L[] = "  ① BODY SIZE: Significantly enlarge — legs must be 1.5–2× longer. Body 50–80% bigger.";
            break;
          case 'moderate_increase':
            $L[] = "  ① BODY SIZE: Visibly grow — legs 30–50% longer. Body noticeably bigger and taller.";
            break;
          case 'minimal_increase':
            $L[] = "  ① BODY SIZE: Minimal height increase. Instead: body becomes heavier, wider, denser.";
            $L[] = "               Eliminate all baby roundness. Low-and-wide adult form.";
            break;
          case 'none':
            $L[] = "  ① BODY SIZE: Height stays the same. Instead: face sharpens, coat transforms completely.";
            break;
        }
        $L[] = '';
        $L[] = "  ② LEGS: Replace short stubby puppy legs → longer, fully muscled adult legs.";
        $L[] = "  ③ HEAD: Shrink the head-to-body ratio (puppies have oversized heads). Elongate muzzle.";
        $L[] = "  ④ BODY: Remove potbelly/barrel abdomen. Develop chest depth. Tuck the waist.";
        $L[] = "  ⑤ PAWS: Resize paws to be proportionate to the now-longer legs (not oversized).";
        $L[] = "  ⑥ EARS: Settle ears to adult position — erect, folded, or rose as breed requires.";

        // Coat transformation for this breed type
        $this->addCoatTransformAction($L, $coat, 7, $breed, 'adolescent');

        if (!empty($puppy1yr)) {
          $L[] = '';
          $L[] = "  ⑧ BREED-SPECIFIC ({$breed}): {$puppy1yr}";
        }
        if (!empty($face1yr)) {
          $L[] = "  ⑨ FACE: {$face1yr}";
        }
      } else { // 3 years
        $L[] = "  📋 PUPPY → +3 YEARS: FULL ADULT TRANSFORMATION — EXECUTE THESE CHANGES:";
        $L[] = '';
        $L[] = "  ⚠️  OUTPUT MUST LOOK LIKE A COMPLETELY DIFFERENT AGE — A FULL-GROWN ADULT.";
        $L[] = '';

        if (!empty($adultDesc)) {
          $L[] = "  🎯 WHAT THIS BREED LOOKS LIKE AS A FULL ADULT:";
          $L[] = "     \"{$adultDesc}\"";
          $L[] = "     YOUR OUTPUT MUST MATCH THIS DESCRIPTION PRECISELY.";
          $L[] = '';
        }

        switch ($heightChg) {
          case 'dramatic_increase':
            $L[] = "  ① BODY SIZE: This dog is now at FULL ADULT GIANT SIZE. Transform to 3–4× larger.";
            $L[] = "               Legs must be dramatically long and powerful. Massive overall presence.";
            break;
          case 'large_increase':
            $L[] = "  ① BODY SIZE: FULL ADULT SIZE — 2–3× larger than puppy. Large powerful dog.";
            break;
          case 'moderate_increase':
            $L[] = "  ① BODY SIZE: Full adult proportions — 1.5–2× taller and heavier than puppy.";
            break;
          case 'minimal_increase':
            $L[] = "  ① BODY SIZE: Low-profile breed at FULL ADULT LENGTH — very heavy and dense body.";
            break;
          case 'none':
            $L[] = "  ① BODY SIZE: Same tiny height — but FULLY ADULT face, coat, and proportions.";
            break;
        }
        $L[] = '';
        $L[] = "  ② MUSCULATURE: Add FULL ADULT MUSCLE MASS — defined shoulders, chest, haunches.";
        $L[] = "  ③ HEAD: Adult skull fully developed — proportionate, defined, breed-standard face.";
        $L[] = "  ④ BODY: Deep chest, adult topline, proper waist tuck (or breed-standard belly).";
        $L[] = "  ⑤ LEGS: Fully elongated, muscled adult legs — no trace of stubby puppy proportions.";

        $this->addCoatTransformAction($L, $coat, 6, $breed, 'full_adult');

        if (!empty($puppy3yr)) {
          $L[] = '';
          $L[] = "  ⑦ BREED-SPECIFIC ({$breed}): {$puppy3yr}";
        }
        if (!empty($face3yr)) {
          $L[] = "  ⑧ FACE: {$face3yr}";
        }
        if (!empty($coat3yr)) {
          $L[] = "  ⑨ COAT DETAIL: {$coat3yr}";
        }
      }

      // ── YOUNG ADULT TRANSFORMATIONS ──────────────────────────────────
    } elseif ($isYoung) {
      $L[] = "  📋 YOUNG ADULT → MATURE ADULT (+{$targetYears} year(s)): EXECUTE THESE CHANGES:";
      $L[] = '';

      if ($targetYears === 1) {
        $L[] = "  ① MUSCLE: Visibly increase muscle mass — thicker neck, deeper chest, broader shoulders.";
        $L[] = "  ② FACE: Sharpen and mature facial features. Square off any remaining youthful softness.";
        if (!empty($body1yr)) $L[] = "  ③ BODY: {$body1yr}";
        if (!empty($face1yr)) $L[] = "  ④ FACE DETAIL: {$face1yr}";
        if (!empty($coat1yr)) $L[] = "  ⑤ COAT: {$coat1yr}";
      } else {
        $L[] = "  ① MUSCLE: Full prime adult musculature — peak physical development for this breed.";
        $L[] = "  ② FACE: Fully mature adult face. Breed-standard adult expression. Minor muzzle silvering.";
        if (!empty($body3yr)) $L[] = "  ③ BODY: {$body3yr}";
        if (!empty($face3yr)) $L[] = "  ④ FACE DETAIL: {$face3yr}";
        if (!empty($coat3yr)) $L[] = "  ⑤ COAT: {$coat3yr}";
      }

      // ── SENIOR TRANSFORMATIONS ────────────────────────────────────────
    } elseif ($isSenior) {
      $L[] = "  📋 SENIOR DOG → OLDER SENIOR (+{$targetYears} year(s)): EXECUTE THESE CHANGES:";
      $L[] = '';

      if ($targetYears === 1) {
        $L[] = "  ① MUZZLE GRAYING: Expand white/gray fur coverage on muzzle, chin, and around eyes.";
        $L[] = "  ② EYES: Slightly cloudier, more tired-looking, deeper-set.";
        $L[] = "  ③ SKIN: More sagging under jowls and neck area.";
        $L[] = "  ④ COAT: Coarser, slightly thinner in spots, less vibrant sheen.";
        if (!empty($body1yr)) $L[] = "  ⑤ BODY: {$body1yr}";
        if (!empty($face1yr)) $L[] = "  ⑥ FACE DETAIL: {$face1yr}";
      } else {
        $L[] = "  ① MUZZLE GRAYING: White/silver fur must cover the ENTIRE muzzle, full chin, and eye area.";
        $L[] = "  ② EYES: Visibly cloudier with age-related opacity. Deeper set.";
        $L[] = "  ③ JOWLS/NECK: Significantly sagged skin folds on jowls and neck.";
        $L[] = "  ④ COAT: Noticeably thinner and duller — reduced vibrancy and shine.";
        $L[] = "  ⑤ BODY: Slightly reduced muscle mass — less defined than prime adult.";
        if (!empty($body3yr)) $L[] = "  ⑥ BODY DETAIL: {$body3yr}";
        if (!empty($face3yr)) $L[] = "  ⑦ FACE DETAIL: {$face3yr}";
      }

      // ── ADULT TRANSFORMATIONS ─────────────────────────────────────────
    } else {
      $L[] = "  📋 ADULT DOG → +{$targetYears} YEAR(S): EXECUTE THESE CHANGES:";
      $L[] = '';

      if ($targetYears === 1) {
        $L[] = "  ① MUSCLE: Slight increase in chest and neck density. Body looks more settled.";

        if (!empty($body1yr)) $L[] = "  ② BODY: {$body1yr}";
        if (!empty($face1yr)) $L[] = "  ③ FACE: {$face1yr}";
        if (!empty($coat1yr)) $L[] = "  ④ COAT: {$coat1yr}";

        if (empty($body1yr) && empty($face1yr)) {
          $L[] = "  ② FACE: Add 3–5 silver/gray hairs at muzzle tip and chin. Subtle aging.";
          $L[] = "  ③ COAT: Marginally denser, slightly less glossy than before.";
        }
      } else {
        $L[] = "  ① GRAYING: Clear silver/white hairs covering entire muzzle tip, chin, and sparse around eyes.";
        $L[] = "  ② BODY: Thicker neck and chest. Body looks heavier and more settled. Less lean.";

        if (!empty($body3yr)) $L[] = "  ③ BODY DETAIL: {$body3yr}";
        if (!empty($face3yr)) $L[] = "  ④ FACE: {$face3yr}";
        if (!empty($coat3yr)) $L[] = "  ⑤ COAT: {$coat3yr}";

        if (empty($body3yr) && empty($face3yr)) {
          $L[] = "  ③ COAT: Denser, slightly coarser, marginally less vibrant.";
          $L[] = "  ④ FACE: Jowls slightly more developed. Face looks more experienced and settled.";
        }
      }
    }

    // ── SECTION C: BREED BIOLOGY GUARDRAILS ──────────────────────────
    $L[] = '';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  SECTION C — BREED ANATOMY RULES (breed-specific constraints):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';

    if ($bodyShape === 'long_low') {
      $L[] = "  🔒 LONG-AND-LOW BREED: Legs NEVER grow taller. Body grows LONGER and HEAVIER.";
      $L[] = "       Chest drops lower, torso widens. Never taller. Always longer.";
    }
    if ($isBrachy) {
      $L[] = "  🔒 FLAT-FACE BREED: The pushed-in face is PERMANENT — do NOT elongate the muzzle.";
      $L[] = "       Age shows through: deeper wrinkles, sagging jowls, more prominent nose fold/rope.";
    }
    if ($bodyShape === 'sighthound') {
      $L[] = "  🔒 SIGHTHOUND BREED: Always retains lean aerodynamic physique. Never becomes fat.";
      $L[] = "       Deep chest tuck is always present. Ribcage outline visible (normal for breed).";
    }
    if ($size === 'giant') {
      $L[] = "  🔒 GIANT BREED: Full adult size is MASSIVE and IMPOSING. Puppy→adult is enormous.";
    }
    if ($size === 'toy' || $size === 'small') {
      $L[] = "  🔒 SMALL BREED: Height changes minimally. Aging shown through coat/face changes.";
    }
    if ($bodyShape === 'spitz') {
      $L[] = "  🔒 SPITZ BREED: Erect pointed ears always present. Curled tail over back always present.";
      $L[] = "       Plush stand-off double coat becomes denser and more voluminous with age.";
    }

    // Coat type guardrails
    switch ($coat) {
      case 'long_silky':
        $L[] = "  🔒 SILKY COAT BREED: Adult coat MUST be visibly longer and more flowing than puppy coat.";
        if ($this->mb(strtolower($breed), ['yorkshire', 'yorkie'])) {
          $L[] = "  🔒 YORKIE SPECIFICALLY: Body coat MUST be steel-blue/silver. Head/legs MUST be rich golden-tan.";
          $L[] = "       Coat must be STRAIGHT, SILKY, and LONG — NOT fluffy or wavy.";
        }
        break;
      case 'double_coat':
        $L[] = "  🔒 DOUBLE COAT: Adult coat must be denser, more voluminous, and have visible undercoat.";
        break;
      case 'wire':
      case 'wire_harsh':
        $L[] = "  🔒 WIRE COAT: Adult wire coat must look NOTICEABLY harsher and more bristly.";
        $L[] = "       Beard and eyebrows (if breed has them) must be prominent in adult.";
        break;
      case 'curly':
      case 'wavy_curly':
        $L[] = "  🔒 CURLY/WAVY COAT: Adult curls must be tighter, denser, and more voluminous.";
        $L[] = "       Puppy fuzz must be replaced by defined, adult curl pattern.";
        break;
    }

    // ── SECTION D: QUALITY GATE ───────────────────────────────────────
    $L[] = '';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  SECTION D — QUALITY GATE (verify before finalizing):';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '';
    $L[] = "  ✅ Does the output dog look UNDENIABLY {$targetYears} year(s) older? (YES required)";
    $L[] = "  ✅ Are ALL Section B transformation directives visibly applied? (YES required)";
    $L[] = "  ✅ Are ALL Section A locked elements unchanged? (YES required)";
    $L[] = "  ✅ Does the dog still look like the SAME individual dog, just older? (YES required)";
    $L[] = '';
    $L[] = "  ❌ If output looks almost the same as input → FAILED. This is unacceptable.";
    $L[] = "  ❌ If only color/contrast changed without structural changes → FAILED.";
    $L[] = "  ❌ If the background changed → FAILED.";
    $L[] = "  ❌ If breed characteristics are wrong → FAILED.";
    $L[] = '';
    $L[] = '══════════════════════════════════════════════════════════════════════════════';
    $L[] = "  EXECUTE THE TRANSFORMATION NOW. Output ONLY the aged dog image. Nothing else.";
    $L[] = '══════════════════════════════════════════════════════════════════════════════';

    return implode("\n", $L);
  }

  /**
   * Helper: adds a numbered coat transformation action to the prompt lines array.
   */
  private function addCoatTransformAction(array &$L, string $coat, int $num, string $breed, string $targetStage): void
  {
    $b = strtolower($breed);

    switch ($coat) {
      case 'long_silky':
        if ($this->mb($b, ['yorkshire', 'yorkie'])) {
          $L[] = "  ⑧ COAT (CRITICAL for Yorkie): Replace short fluffy puppy coat with LONG FLOWING SILKY STRAIGHT coat.";
          $L[] = "        Body: transform to STEEL-BLUE / silver color (NOT black).";
          $L[] = "        Head/face/legs: rich GOLDEN-TAN (NOT dark).";
          $L[] = "        Length: " . ($targetStage === 'full_adult' ? 'reaching toward the floor' : 'visibly longer, mid-body length') . ".";
          $L[] = "        Texture: SILKY and STRAIGHT (not fluffy, not wavy).";
        } elseif ($this->mb($b, ['maltese'])) {
          $L[] = "  ⑦ COAT: Replace puppy fluff with LONG PURE WHITE SILKY coat.";
          $L[] = "        " . ($targetStage === 'full_adult' ? 'Floor-length flowing white silk everywhere.' : 'Noticeably longer, flowing white coat growing.');
        } elseif ($this->mb($b, ['shih tzu'])) {
          $L[] = "  ⑦ COAT: Grow coat DRAMATICALLY LONGER and SILKIER everywhere.";
          $L[] = "        Long facial hair flowing outward from flat face. Body coat fully flowing.";
        } elseif ($this->mb($b, ['cocker'])) {
          $L[] = "  ⑦ COAT: Grow full SILKY FEATHERING on ears, chest, belly, and all four legs.";
          $L[] = "        Ears develop long flowing fringe. Coat becomes luxurious and silky everywhere.";
        } elseif ($this->mb($b, ['lhasa', 'afghan', 'borzoi', 'saluki'])) {
          $L[] = "  ⑦ COAT: Grow coat to FULL ADULT LENGTH — long, flowing, and silky throughout.";
        } else {
          $L[] = "  ⑦ COAT: Grow coat VISIBLY LONGER and SILKIER than current puppy length.";
        }
        break;

      case 'double_coat':
        if ($this->mb($b, ['pomeranian'])) {
          $L[] = "  ⑦ COAT: Grow to ENORMOUS STAND-OFF DOUBLE COAT — massive ruff/mane around neck,";
          $L[] = "        thick coat standing away from body. Heavily plumed tail. Looks like a fluffy ball.";
        } elseif ($this->mb($b, ['husky', 'malamute', 'samoyed'])) {
          $L[] = "  ⑦ COAT: Develop THICK PLUSH DOUBLE COAT — dense undercoat, coarse guard hairs.";
          $L[] = "        Ruff/mane thickens prominently. Coat volume increases significantly.";
        } else {
          $L[] = "  ⑦ COAT: Develop DENSER DOUBLE COAT with visible undercoat and coarser guard hairs.";
        }
        break;

      case 'curly':
      case 'wavy_curly':
        $L[] = "  ⑦ COAT: Replace soft puppy fluff with DEFINED ADULT CURLS/WAVES.";
        $L[] = "        Tighter, denser curl pattern. Much more voluminous than puppy coat.";
        break;

      case 'wire':
      case 'wire_harsh':
        $L[] = "  ⑦ COAT: Replace soft puppy coat with HARSH BRISTLY WIRE COAT.";
        $L[] = "        Beard and eyebrows (if breed has them) must become prominent.";
        break;

      default: // short coat
        $L[] = "  ⑦ COAT: Replace puppy fuzz with tight, sleek adult short coat. Denser and defined.";
        break;
    }
  }

  // ─────────────────────────────────────────────────────────────────────
  //  IMAGE PREPARATION — UPSCALE SMALL IMAGES
  // ─────────────────────────────────────────────────────────────────────

  private function prepareImage(string $fullPath): ?array
  {
    try {
      $cacheKey = 'hq_img_v4_' . md5($fullPath);

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
        imagejpeg($img, null, 93);
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
    imagejpeg($dst, null, 93);
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
  //  ★★★ COMPREHENSIVE BREED PROFILES — GREATLY EXPANDED ★★★
  // ─────────────────────────────────────────────────────────────────────

  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    // ── DEFAULT BASE ──────────────────────────────────────────────────
    $profile = [
      'breed'                    => $breed,
      'size_category'            => 'medium',
      'body_shape'               => 'standard',
      'coat_type'                => 'short',
      'brachycephalic'           => false,
      'growth_rate'              => 'standard',
      'height_change'            => 'moderate_increase',
      'adult_size_description'   => 'A medium-sized adult dog with well-developed musculature and a fully settled adult coat.',
      'puppy_to_1yr'             => 'DRAMATIC SIZE INCREASE: Lengthen legs by 50–80%, widen and deepen chest, tuck abdomen to eliminate potbelly, sharpen facial structure significantly. Coat transitions from soft puppy fuzz to denser adult coat.',
      'puppy_to_3yr'             => 'FULL ADULT TRANSFORMATION: Full adult height and muscle mass. Deep chest, tucked waist, fully developed head and muzzle. Coat is completely adult in texture and density.',
      'aging_1yr_body'           => 'Slightly increase muscle density on chest and hindquarters. Body looks marginally more settled.',
      'aging_1yr_face'           => 'Very slight sharpening of facial features. Muzzle tip may show 1–2 silver hairs.',
      'aging_1yr_coat'           => 'Coat becomes marginally denser and more defined.',
      'aging_3yr_body'           => 'Noticeably thicker neck and chest. Hindquarters more developed. Body looks heavier.',
      'aging_3yr_face'           => 'VISIBLE GRAYING: Distinct silver/white hairs covering muzzle tip, chin, and sparse around eyes.',
      'aging_3yr_coat'           => 'Coat fully matured — denser and slightly coarser. Less glossy than in youth.',
    ];

    // ══════════════════════════════════════════════════════════════════
    // GIANT BREEDS
    // ══════════════════════════════════════════════════════════════════
    if ($this->mb($b, ['great dane', 'irish wolfhound', 'saint bernard', 'newfoundland', 'leonberger', 'mastiff', 'great pyrenees', 'anatolian', 'kangal', 'caucasian', 'tibetan mastiff', 'boerboel', 'cane corso', 'dogue de bordeaux', 'french mastiff', 'neapolitan mastiff', 'broholmer', 'moscow watchdog'])) {
      $profile['size_category']          = 'giant';
      $profile['height_change']          = 'dramatic_increase';
      $profile['growth_rate']            = 'fast';
      $profile['adult_size_description'] = 'One of the largest dog breeds — a towering, massively built adult standing 28–35 inches tall with enormous bone structure, broad skull, and imposing physical presence.';
      $profile['puppy_to_1yr']           = 'ENORMOUS SIZE EXPLOSION: This giant breed grows faster than any other. Legs must be DRAMATICALLY longer — at least 2–3× the puppy leg length. Chest must be wide and deep. Head skull broadens massively. Total body looks 3–4× larger. This is one of the most dramatic puppy-to-adolescent transformations in the animal kingdom.';
      $profile['puppy_to_3yr']           = 'COLOSSAL ADULT: Full adult giant size — enormous skeleton, massive head, deep barrel chest, powerful haunches. The dog should look IMPOSING and gigantic compared to the puppy. Apex of canine size.';
      $profile['aging_1yr_body']         = 'HEAVY MASS: Thicken the neck significantly, broaden the chest into a massive barrel. Shoulders widen visibly.';
      $profile['aging_1yr_face']         = 'Skull broadens. Jowls/flews more pronounced. Wrinkles deepen.';
      $profile['aging_3yr_body']         = 'PEAK POWER: Enormous thick neck, barrel chest, massive hindquarters. The dog looks like an immovable force.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING: Silver/white on entire muzzle, chin, and around eyes. Giant breeds gray early. Deep facial creases.';
      $profile['brachycephalic']         = $this->mb($b, ['mastiff', 'saint bernard', 'leonberger', 'cane corso', 'dogue', 'neapolitan', 'broholmer']);
    }

    // ══════════════════════════════════════════════════════════════════
    // POINTING / HUNTING DOGS (Bracco Italiano, Vizsla, Weimaraner, etc.)
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['bracco italiano', 'italian pointer', 'catalburun', 'turkish pointer', 'pointing dog'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large, noble hunting dog standing 22–26 inches tall — pendulous long ears, slightly loose skin around the jowl/throat area, strong athletic build, short smooth coat, deep-chested with visible musculature. Expressive hound-like face with amber/brown eyes.';
      $profile['puppy_to_1yr']           = 'HUNTING DOG GROWTH: Legs lengthen dramatically to athletic adult proportions. Long pendulous ears develop fully and hang lower. Throat/jowl area develops characteristic loose skin. Short smooth coat tightens and densifies. Deep chest develops. Body becomes powerful and athletic. Face becomes more noble and hound-like.';
      $profile['puppy_to_3yr']           = 'FULL HUNTING DOG ADULT: Powerful athletic build with deep chest, lean abdomen, well-muscled limbs. Long pendulous ears. Slightly loose jowl skin. Noble hunting dog expression with alert amber eyes. Smooth tight coat showing defined musculature.';
      $profile['aging_1yr_body']         = 'Chest deepens and broadens. Muscle definition increases on shoulders and hindquarters.';
      $profile['aging_1yr_face']         = 'Face becomes more noble and experienced. Jowl skin slightly more pronounced.';
      $profile['aging_3yr_body']         = 'PRIME HUNTING CONDITION: Deep chest, powerful shoulders, lean muscled hindquarters. Peak athletic physique.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING: Silver/white clearly visible on muzzle tip, around the nose, and chin. Amber eyes maintain brightness. Jowls slightly more pronounced.';
    } elseif ($this->mb($b, ['vizsla', 'hungarian vizsla', 'wirehaired vizsla'])) {
      $isWire = $this->mb($b, ['wire', 'wirehaired']);
      $profile['size_category']          = 'large';
      $profile['coat_type']              = $isWire ? 'wire_harsh' : 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A lean, muscular golden-rust colored hunting dog — long aristocratic head, amber eyes, floppy ears, tucked abdomen, and defined musculature under a sleek short coat. Elegantly athletic.';
      $profile['puppy_to_1yr']           = 'GOLDEN HUNTING DOG EMERGENCE: Body lengthens and becomes sleek and athletic. Deep chest develops with visible tuck-up at abdomen. Legs elongate to lean adult proportions. Golden-rust color intensifies to rich adult hue. Noble aristocratic head shape develops. Floppy ears settle at correct angle.';
      $profile['puppy_to_3yr']           = 'FULL VIZSLA ADULT: Lean muscular golden-rust dog. Long aristocratic head with amber eyes. Deep chest with dramatic tuck-up. Defined musculature. The image of elegant athletic efficiency.';
      $profile['aging_3yr_face']         = 'SLIGHT GRAYING at muzzle tip. Noble expression deepens. Amber eyes maintain warmth.';
      $profile['aging_3yr_body']         = 'Peak muscular condition. Lean, athletic, deep-chested adult.';
    } elseif ($this->mb($b, ['weimaraner'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A sleek silver-grey dog — long elegant neck, deep chest, tucked abdomen, pale grey eyes, floppy ears, and an aristocratic ghost-like appearance. Pure silver-grey short coat.';
      $profile['puppy_to_1yr']           = 'WEIMARANER GHOST DOG: Legs lengthen to elegant long proportions. Silver-grey coat becomes sleeker and tighter. Deep chest develops. Abdomen tucks dramatically. Long elegant neck develops. Pale grey/amber eyes become more prominent. Aristocratic adult silhouette emerges.';
      $profile['puppy_to_3yr']           = 'FULL WEIMARANER: Sleek silver-grey aristocrat — long elegant neck, deep chest, dramatic waist tuck, long floppy ears, pale grey eyes. Every muscle visible under the tight silver coat.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING (coat already grey). Pale eyes deepen. Aristocratic expression matures.';
      $profile['aging_3yr_body']         = 'Peak athletic condition. Deep chest, lean abdomen, powerful hindquarters.';
    } elseif ($this->mb($b, ['english pointer', 'german shorthaired pointer', 'german wirehaired pointer', 'pointer'])) {
      $isGSP = $this->mb($b, ['german shorthaired', 'gsp']);
      $isWire = $this->mb($b, ['wirehaired', 'wire']);
      $profile['size_category']          = 'large';
      $profile['coat_type']              = $isWire ? 'wire_harsh' : 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'An athletic pointing dog — lean muscular body with deep chest and pronounced tuck-up, liver or black spotted coat (GSP), square head with strong muzzle, and highly athletic build optimized for speed and endurance.';
      $profile['puppy_to_1yr']           = 'POINTER ATHLETE DEVELOPMENT: Body becomes lean and athletic with deep chest and dramatic tuck-up. Spots/ticking pattern intensifies on liver or black background. Head squares off with strong adult muzzle. Legs lengthen to athletic adult proportions. Muscular definition increases.';
      $profile['puppy_to_3yr']           = 'FULL POINTING DOG: Peak athletic pointing dog — deep chest, pronounced tuck, defined musculature, strong square head. Spot/tick pattern fully developed. The image of athletic efficiency.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING on muzzle. Strong adult expression. Spotting pattern maintains.';
    } elseif ($this->mb($b, ['irish setter', 'english setter', 'gordon setter', 'setter'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A tall, elegant gun dog with a long flowing silky coat — rich mahogany (Irish), white with orange/liver ticking (English), or black-and-tan (Gordon). Long elegant neck, feathering on chest/belly/legs/tail.';
      $profile['puppy_to_1yr']           = 'SETTER ELEGANCE EMERGES: Body grows tall and elegant. Rich coat color deepens. Flowing silky coat develops with feathering beginning on ears, chest, belly, and legs. Long elegant neck develops. Adult setter grace emerges.';
      $profile['puppy_to_3yr']           = 'FULL SETTER: Full flowing silky coat with rich color — feathering everywhere. Long elegant neck. Aristocratic head. The quintessence of elegant gun dog beauty.';
      $profile['aging_3yr_coat']         = 'Full adult feathering at maximum length and luster. Rich coat color at peak intensity.';
    } elseif ($this->mb($b, ['spaniel', 'english springer', 'field spaniel', 'welsh springer', 'clumber', 'sussex', 'boykin'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A sturdy, athletic spaniel with flowing silky coat and feathering, pendulous ears, and cheerful working dog expression.';
      $profile['puppy_to_1yr']           = 'SPANIEL DEVELOPMENT: Coat develops silky feathering on ears, chest, belly, and legs. Body becomes compact and sturdy. Color pattern intensifies.';
      $profile['puppy_to_3yr']           = 'FULL SPANIEL ADULT: Full silky feathering everywhere. Pendulous ears with flowing fringe. Sturdy balanced body. Rich color at full depth.';
    }

    // ══════════════════════════════════════════════════════════════════
    // LARGE WORKING / SHEPHERD BREEDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['german shepherd', 'german shepherd dog', 'gsd', 'alsatian', 'belgian malinois', 'dutch shepherd', 'belgian tervuren', 'belgian laekenois', 'belgian shepherd'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A powerful, athletic dog standing 22–26 inches — wolf-like, lean-muscled, with a dense double coat, perfectly erect ears, and a long confident stride. Alert intelligent expression.';
      $profile['puppy_to_1yr']           = 'WOLF-LIKE EMERGENCE: Legs lengthen dramatically. Erect ears — if flopped as puppy, now stand PERFECTLY upright and rigid. Coat transitions from puppy fluff to sleek dense double coat with visible saddle/blanket pattern. Face elongates to wolf-like adult snout. Body becomes lean and muscular with visible hindquarter angulation.';
      $profile['puppy_to_3yr']           = 'FULL WORKING DOG: Dense adult double coat with clear black saddle/blanket. Long wolf-like muzzle. Perfectly erect pointed ears. Deep chest, athletic waist tuck, powerful haunches. Military working dog in prime condition.';
      $profile['aging_1yr_body']         = 'Hindquarters muscle definition increases. Chest drops slightly deeper. Back becomes more defined.';
      $profile['aging_1yr_face']         = 'Face sharpens. Mask pattern intensifies. Slight muzzle tip silvering begins.';
      $profile['aging_3yr_body']         = 'Full muscle development — especially rear angulation. Chest prominent. Coat at full density.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING: Silver/white clearly visible on muzzle tip, chin, and above eyes. Mask slightly fades at edges.';
      $profile['aging_3yr_coat']         = 'Double coat at maximum density — thick undercoat visible at neck and chest. Guard hairs coarser.';
    } elseif ($this->mb($b, ['siberian husky', 'husky', 'alaskan malamute', 'malamute', 'samoyed', 'akita', 'shiba inu', 'chow chow', 'keeshond', 'spitz', 'american akita', 'japanese akita', 'kishu', 'shikoku', 'hokkaido', 'kai ken'])) {
      $isLarge = $this->mb($b, ['malamute', 'akita', 'american akita', 'japanese akita']);
      $profile['size_category']          = $isLarge ? 'large' : 'medium';
      $profile['coat_type']              = 'double_coat';
      $profile['body_shape']             = 'spitz';
      $profile['height_change']          = $isLarge ? 'large_increase' : 'moderate_increase';
      $profile['adult_size_description'] = 'A Nordic-type dog with thick plush double coat, erect pointed ears, curled tail over back, and compact powerful build. Regal, wolf-like appearance with striking mask or facial markings.';
      $profile['puppy_to_1yr']           = 'ARCTIC DOG TRANSFORMATION: Replace wispy puppy fluff with ENORMOUS thick double coat — visibly denser, plush, stand-off. Erect ears become rigid and pointed. Tail curls firmly over back. Face develops adult mask/coloring more intensely. Body becomes compact and muscular.';
      $profile['puppy_to_3yr']           = 'FULL ARCTIC ADULT: Peak double coat — massive ruff/mane at neck, thick undercoat visible, guard hairs coarse. Full adult mask. Curled tail perfectly formed. Powerful compact build.';
      $profile['aging_1yr_body']         = 'Coat reaches full adult volume — ruff develops prominently at neck.';
      $profile['aging_3yr_face']         = 'SUBTLE SILVERING on muzzle edges. Mask pattern may slightly fade. Eyes remain piercing.';
    } elseif ($this->mb($b, ['border collie', 'australian shepherd', 'aussie'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A lithe, athletic herding dog with flowing double coat, alert eyes (possibly merle or blue), intense focused expression, and lean agile build.';
      $profile['puppy_to_1yr']           = 'HERDING ATHLETE: Body lengthens and becomes leggier. Coat transitions to flowing adult coat with feathering. Merle/color pattern intensifies. Face sharpens to intense herding expression. Ears settle. Lean athletic body.';
      $profile['puppy_to_3yr']           = 'PEAK HERDING ATHLETE: Full adult coat with mane and feathering on legs/tail. Intense focused expression. Lean agile body with clear waist tuck.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING — these breeds gray slowly. Slight silver at muzzle tip only.';
    } elseif ($this->mb($b, ['collie', 'rough collie', 'smooth collie', 'sheltie', 'shetland sheepdog'])) {
      $isSheltie = $this->mb($b, ['sheltie', 'shetland']);
      $profile['size_category']          = $isSheltie ? 'small' : 'large';
      $profile['coat_type']              = $this->mb($b, ['smooth']) ? 'short' : 'long_silky';
      $profile['height_change']          = $isSheltie ? 'moderate_increase' : 'large_increase';
      $profile['adult_size_description'] = 'A strikingly elegant dog with a long flowing mane and frill, narrow aristocratic head with long pointed snout, and rich sable/tricolor/merle coat flowing down the body.';
      $profile['puppy_to_1yr']           = 'REGAL EMERGENCE: COAT TRANSFORMATION is the biggest change — replace puppy fluff with growing FLOWING MANE at neck and chest. Long muzzle elongates toward adult narrow aristocratic shape. Body becomes taller and more elegant.';
      $profile['puppy_to_3yr']           = 'FULL LASSIE ADULT: Enormous flowing mane and frill. Long silky coat down flanks and tail. Aristocratic narrow head fully developed. Rich sable or tricolor at full intensity.';
    }

    // ══════════════════════════════════════════════════════════════════
    // GOLDEN / LABRADOR / RETRIEVERS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['golden retriever'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large well-proportioned dog with thick golden flowing coat, broad head, soft intelligent eyes, deep chest, and feathering on legs, chest, and tail. Rich golden color.';
      $profile['puppy_to_1yr']           = 'GOLDEN TRANSFORMATION: Legs lengthen significantly (leggy adolescent phase). Coat transitions from puppy fluff to developing GOLDEN WAVES — feathering begins on chest, legs, and tail. Head broadens. Body becomes tall and athletic. Rich golden color intensifies.';
      $profile['puppy_to_3yr']           = 'FULL GOLDEN ADULT: Lush flowing golden coat at full length with prominent feathering on chest, belly, legs, and tail. Broad adult head. Deep chest, well-muscled body. Rich golden coat.';
      $profile['aging_1yr_coat']         = 'Developing feathering on chest and legs.';
      $profile['aging_3yr_face']         = 'EARLY MUZZLE GRAYING: Golden retrievers gray early. Clear silver/white on muzzle tip and chin.';
      $profile['aging_3yr_coat']         = 'Coat at peak length and luster — rich golden with full feathering everywhere.';
    } elseif ($this->mb($b, ['labrador retriever', 'labrador', 'lab'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large athletic dog with broad otter-like tail, dense short coat (black/yellow/chocolate), broad head, deep chest, and powerful stocky build. Classic family dog.';
      $profile['puppy_to_1yr']           = 'LABRADOR GROWTH BURST: Massive size increase. Legs become long and powerful. Otter tail (thick at base, tapering) must be clearly visible and prominent. Chest broadens and deepens. Short coat becomes denser. Head broadens with adult Lab expression.';
      $profile['puppy_to_3yr']           = 'FULL LAB ADULT: Classic — broad head, short dense coat, prominent otter tail, powerful stocky body, deep chest. Well-muscled. The archetypal family dog.';
      $profile['aging_3yr_body']         = 'Full Lab bulk — neck thick, chest barrel-like, body powerful. May begin showing slight weight around abdomen.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING: Clear silver/gray on muzzle tip and chin. Broad lab face slightly more jowled.';
    } elseif ($this->mb($b, ['flat-coated retriever', 'flat coated retriever', 'chesapeake bay retriever', 'curly coated retriever', 'nova scotia duck tolling', 'toller'])) {
      $isToller = $this->mb($b, ['tolling', 'toller']);
      $isCurly  = $this->mb($b, ['curly']);
      $profile['size_category']          = $isToller ? 'medium' : 'large';
      $profile['coat_type']              = $isCurly ? 'curly' : 'long_silky';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A large athletic retriever with a ' . ($isCurly ? 'dense curly' : 'flowing') . ' coat, deep chest, and strong athletic build. Classic working retriever physique.';
      $profile['puppy_to_3yr']           = 'FULL RETRIEVER: Athletic build, deep chest, full ' . ($isCurly ? 'curly' : 'flowing') . ' coat at adult length, strong powerful physique.';
    }

    // ══════════════════════════════════════════════════════════════════
    // COCKER SPANIELS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['cocker spaniel', 'english cocker', 'american cocker'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A compact sturdy spaniel with long luxurious silky coat and feathering, long pendulous ears framing a domed head, soft melting expression, and deep rich coat coloring.';
      $profile['puppy_to_1yr']           = 'SPANIEL BLOSSOMING: Most dramatic change is COAT GROWTH. Long silky feathering begins flowing from ears, chest, belly, and legs. Ears become longer and more pendulous with silky fringe. Head domes more. Body compact. GROW COAT DRAMATICALLY.';
      $profile['puppy_to_3yr']           = 'FULL SHOW COCKER: Enormous flowing silky coat — full feathering covering chest, belly, all four legs, and tail. Long pendulous ear leather with flowing fringe. Domed head, soft expression. Rich coat color at full depth.';
      $profile['aging_3yr_coat']         = 'Maximum feathering length — flowing silky coat everywhere. Rich deep color.';
    }

    // ══════════════════════════════════════════════════════════════════
    // POODLES
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['standard poodle', 'miniature poodle', 'toy poodle', 'poodle'])) {
      $isStandard = $this->mb($b, ['standard']);
      $isMini     = $this->mb($b, ['miniature', 'mini']);
      $isToy      = $this->mb($b, ['toy']);
      $profile['size_category']   = $isStandard ? 'large' : ($isMini ? 'small' : ($isToy ? 'toy' : 'medium'));
      $profile['coat_type']       = 'curly';
      $profile['height_change']   = $isStandard ? 'large_increase' : ($isMini ? 'moderate_increase' : 'none');
      $profile['adult_size_description'] = $isStandard
        ? 'A tall elegant dog 21–27 inches — athletic and graceful with a long refined head, pendant ears, and tight curly coat of solid color.'
        : 'A smaller compact poodle with the same dense curly coat and refined build.';
      $profile['puppy_to_1yr']    = 'POODLE COAT EXPLOSION: The single most dramatic change is the COAT. Replace wispy puppy fluff with TIGHT, DENSE, UNIFORM CURLS covering the entire body. ' . ($isStandard ? 'Body grows taller and more leggy. ' : '') . 'Head develops refined long angular shape. The coat must look distinctly poodle — dense and sculptable. NO puppy fluff should remain.';
      $profile['puppy_to_3yr']    = 'FULL POODLE ADULT: Entire body covered in tight dense curls at full adult length. Refined elegant head. ' . ($isStandard ? 'Tall athletic body. ' : 'Compact elegant body. ') . 'Coat is single solid color throughout.';
      $profile['aging_3yr_coat']  = 'Curls at maximum density and uniformity. Coat texture finest.';
    }

    // ══════════════════════════════════════════════════════════════════
    // DOODLE HYBRIDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['goldendoodle', 'labradoodle', 'bernedoodle', 'aussiedoodle', 'sheepadoodle', 'saint berdoodle', 'newfypoo', 'pyredoodle', 'airedoodle', 'rottle', 'shepadoodle', 'bordoodle', 'boxerdoodle', 'doberdoodle', 'weimardoodle', 'irishdoodle', 'springerdoodle'])) {
      $isLarge = $this->mb($b, ['standard', 'large', 'bernedoodle', 'sheepadoodle', 'saint berdoodle', 'newfypoo', 'pyredoodle', 'airedoodle', 'rottle', 'shepadoodle']);
      $profile['size_category']   = $isLarge ? 'large' : 'medium';
      $profile['coat_type']       = 'wavy_curly';
      $profile['height_change']   = $isLarge ? 'large_increase' : 'moderate_increase';
      $profile['puppy_to_1yr']    = 'DOODLE COAT EMERGENCE: Replace puppy fluff with THICK WAVY/CURLY adult coat — dramatically more voluminous and full. Body grows ' . ($isLarge ? 'significantly taller and heavier. ' : 'taller. ') . 'Teddy bear face sharpens. Beard and eyebrows become prominent.';
      $profile['puppy_to_3yr']    = 'FULL DOODLE ADULT: Enormous fluffy wavy coat everywhere. ' . ($isLarge ? 'Large powerful body under waves of fur. ' : '') . 'Classic teddy bear look — full beard, expressive eyes framed by fur.';
      $profile['aging_3yr_coat']  = 'Coat at maximum volume — rich waves everywhere.';
    } elseif ($this->mb($b, ['cockapoo', 'cavapoo', 'maltipoo', 'schnoodle', 'yorkipoo', 'chipoo', 'havapoo', 'pomapoo', 'shihpoo', 'shi-poo', 'poogle', 'jackapoo', 'corgipoo', 'westiepoo', 'cairnoodle'])) {
      $profile['size_category']   = 'small';
      $profile['coat_type']       = 'wavy_curly';
      $profile['height_change']   = 'none';
      $profile['puppy_to_1yr']    = 'SMALL DOODLE: HEIGHT STAYS SAME. COAT EXPLODES into adult waves/curls — much denser and more voluminous. Beard and facial furnishings grow prominently. Body compact and solid.';
      $profile['puppy_to_3yr']    = 'FULL SMALL DOODLE: Dense curly/wavy coat at full adult length. Prominent beard. Compact solid body. Classic small teddy bear look.';
    }

    // ══════════════════════════════════════════════════════════════════
    // ROTTWEILER / DOBERMANN / BOXERS / LARGE MOLOSSER
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['rottweiler', 'rottie'])) {
      $profile['size_category']          = 'large';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Massive blocky head with broad flat skull, prominent tan/mahogany points on black coat, thick heavily-muscled neck, broad chest, raw power and solidity.';
      $profile['puppy_to_1yr']           = 'ROTTWEILER MASS EXPLOSION: Neck becomes thick and powerful. Head SQUARES OFF — loses all puppy roundness, becomes a massive broad block. Tan/mahogany points intensify dramatically. Chest broadens to barrel-like. Legs become thick-boned columns of muscle.';
      $profile['puppy_to_3yr']           = 'FULL ROTTWEILER: Enormous blocky square head, thick powerful neck, massive chest, columnar legs. Clear defined tan/mahogany points. One of the most muscular-looking breeds at full development.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING: Clear gray/white on muzzle and chin. Square head with deepened wrinkles. Jowls more prominent.';
    } elseif ($this->mb($b, ['doberman', 'dobermann', 'doberman pinscher'])) {
      $profile['size_category']          = 'large';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A sleek athletic dog — long elegant neck, square body, sleek short coat showing every muscle, elegant pointed head with rust markings. The definition of athletic elegance.';
      $profile['puppy_to_1yr']           = 'DOBERMAN SLEEK EMERGENCE: Body elongates and becomes HIGHLY ATHLETIC — every muscle line visible. Neck lengthens elegantly. Rust/tan markings intensify sharply. Head narrows to elegant adult shape. Legs long and lean.';
      $profile['puppy_to_3yr']           = 'FULL DOBERMAN: Sleek, chiseled, every muscle visible. Long elegant neck, narrow refined head, clean rust markings. Most athletic-appearing breed.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING on muzzle. Refined face with slight aging around eyes. Markings stable.';
    } elseif ($this->mb($b, ['boxer'])) {
      $profile['size_category']          = 'large';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Muscular square-built dog with broad brachycephalic head, undershot jaw with prominent flews, fawn or brindle short coat with white flash markings, powerful athletic build.';
      $profile['puppy_to_1yr']           = 'BOXER POWERHOUSE: Body masses up dramatically — chest broadens to square barrel, neck thickens powerfully. FLAT FACE remains flat — do NOT elongate. Wrinkles deepen. Undershot jaw and flews more pronounced.';
      $profile['puppy_to_3yr']           = 'FULL BOXER: Square boxer — wide flat head, undershot jaw with full flews, deep forehead wrinkles, massive barrel chest, white flash markings fully defined. Powerful bouncy physique.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING: White/gray heavily across muzzle, chin, and forehead. Wrinkles deepen.';
    } elseif ($this->mb($b, ['pit bull', 'pitbull', 'american pit bull', 'american staffordshire', 'amstaff', 'american bully'])) {
      $profile['size_category']          = 'medium';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Incredibly muscular dog — broad blocky head, powerful neck and chest, extreme muscle striations throughout, smooth short coat. Most visibly muscular breed.';
      $profile['puppy_to_1yr']           = 'MUSCLE MACHINE: Head SQUARES OFF and broadens dramatically. Neck thickens. Chest broadens wider than the head. Muscle striations become visible through the short coat.';
      $profile['puppy_to_3yr']           = 'FULL POWER: Blocky broad head, thick neck, barrel chest wider than waist. Extreme muscle definition — deltoids, pectorals, quadriceps all visible. Short coat shows every muscle.';
      $profile['aging_3yr_face']         = 'LIGHT GRAYING on muzzle tip. Broad face with deepened jaw muscles.';
    } elseif ($this->mb($b, ['staffordshire bull terrier', 'staffy', 'staffie', 'staffordshire terrier'])) {
      $profile['size_category']          = 'medium';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'A compact, muscular, low-set dog with a broad head, short forehead, pronounced cheek muscles, and a wide chest. More compact and lower-slung than AmStaff.';
      $profile['puppy_to_1yr']           = 'STAFFY POWER: Head broadens significantly. Cheek muscles become very pronounced. Chest widens. Neck thickens. Compact muscular body develops. Short smooth coat tightens.';
      $profile['puppy_to_3yr']           = 'FULL STAFFY: Wide broad head, prominent cheek muscles, wide chest, compact muscular body. Classic English bulldog-terrier athletic build.';
    } elseif ($this->mb($b, ['bull terrier', 'english bull terrier', 'miniature bull terrier'])) {
      $isMini = $this->mb($b, ['miniature', 'mini']);
      $profile['size_category']          = $isMini ? 'small' : 'medium';
      $profile['height_change']          = $isMini ? 'minimal_increase' : 'moderate_increase';
      $profile['adult_size_description'] = 'Unique egg-shaped head — completely flat on top, curved from crown to tip of nose. Small triangular eyes. Muscular powerful body. Short smooth coat.';
      $profile['puppy_to_1yr']           = 'BULL TERRIER EGG HEAD: Most distinctive change — head transforms into the unique EGG SHAPE. Top of skull flattens completely while profile curves continuously from crown to nose tip. Triangular eyes become more defined. Body becomes muscular.';
      $profile['puppy_to_3yr']           = 'FULL BULL TERRIER: Perfect egg-shaped head unmistakable. Small triangular eyes. Powerful muscular body. The most distinctive head shape in dogdom.';
    }

    // ══════════════════════════════════════════════════════════════════
    // SIGHTHOUNDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['whippet', 'greyhound', 'italian greyhound', 'saluki', 'afghan hound', 'borzoi', 'azawakh', 'sloughi', 'pharaoh hound', 'ibizan hound', 'cirneco'])) {
      $isItalian = $this->mb($b, ['italian greyhound', 'cirneco']);
      $isLongCoat = $this->mb($b, ['afghan hound', 'borzoi', 'saluki']);
      $profile['size_category']          = $isItalian ? 'small' : 'medium';
      $profile['body_shape']             = 'sighthound';
      $profile['coat_type']              = $isLongCoat ? 'long_silky' : 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Ultimate athletic dog — aerodynamic silhouette with extreme deep chest tuck, visible ribs (normal), long neck, narrow refined head, extraordinary lean physique.';
      $profile['puppy_to_1yr']           = 'SIGHTHOUND METAMORPHOSIS: The round puppy completely disappears. Replace with EXTREMELY lean, angular silhouette. Deep chest tuck creates dramatic waist. Legs become impossibly long and lean. Visible ribcage outline (completely normal). Long narrow head fully elongated. Overall aerodynamic shape.';
      $profile['puppy_to_3yr']           = 'FULL SIGHTHOUND: Peak aerodynamic physique — deepest chest tuck of any breed, visible ribs, extremely long lean legs, narrow elongated head. ' . ($isLongCoat ? 'Long flowing silky coat at full length.' : 'Sleek tight coat showing every lean muscle.') . ' Breathtakingly elegant.';
      $profile['aging_3yr_face']         = 'MINIMAL GRAYING — sighthounds maintain youth well. 1–2 silver hairs at muzzle tip only.';
    }

    // ══════════════════════════════════════════════════════════════════
    // DALMATIAN
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['dalmatian'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'A lean, athletic dog with a pure white coat and distinctive black or liver spots throughout. Elegant athletic build with deep chest and tucked abdomen.';
      $profile['puppy_to_1yr']           = 'DALMATIAN SPOT DEVELOPMENT: Spots become more defined, darker, and numerous. Body grows lean and athletic. Chest deepens. Abdomen tucks. Legs lengthen to athletic adult proportions. Short coat becomes tighter and sleeker.';
      $profile['puppy_to_3yr']           = 'FULL DALMATIAN: Lean athletic white dog with fully defined bold spots. Deep chest, athletic tuck. Elegant aristocratic movement implied in the physique.';
      $profile['aging_3yr_body']         = 'Lean athletic prime. Spots fully defined and bold.';
    }

    // ══════════════════════════════════════════════════════════════════
    // FRENCH BULLDOG / ENGLISH BULLDOG / PUG / BRACHYCEPHALIC SMALL
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['french bulldog', 'frenchie'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Compact muscular small dog with extremely flat face, large bat-like ears, stocky barrel body, screw tail. Broad square head with deep forehead wrinkles.';
      $profile['puppy_to_1yr']           = 'FRENCHIE ADULT — HEIGHT DOES NOT CHANGE. Bat ears MUST now stand PERFECTLY upright and rigid. Head BROADENS significantly. Body becomes STOCKY and muscular — thick neck, barrel chest, no waist tuck. Deep facial wrinkles develop. Screw tail more defined.';
      $profile['puppy_to_3yr']           = 'FULL FRENCHIE: Perfectly erect bat ears, extremely broad flat head with deep forehead wrinkles, compact square stocky barrel body. Quintessential bulldog look.';
      $profile['aging_3yr_face']         = 'Deep facial wrinkles — especially forehead and around flat nose. Bat ears remain erect.';
    } elseif ($this->mb($b, ['english bulldog', 'british bulldog', 'bulldog'])) {
      $profile['size_category']          = 'medium';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'Massively built, extremely heavy — enormous head with hanging flews and deep wrinkles, massive chest on short bowed legs, rope nose wrinkle.';
      $profile['puppy_to_1yr']           = 'BULLDOG MASS: Body becomes MASSIVELY heavy. Enormous head with hanging flews and heavy facial wrinkles. Neck develops DEEP skin folds (dewlap). Chest widens to extreme width. Rose ears flatten. Short legs.';
      $profile['puppy_to_3yr']           = 'FULL BULLDOG: Enormous wrinkled head with heavy jowls, deep dewlap neck folds, massive barrel body on short bowed legs. Full rope wrinkle.';
      $profile['aging_3yr_face']         = 'Deep rope wrinkle, heavy jowls. HEAVY GRAYING across entire muzzle and chin.';
    } elseif ($this->mb($b, ['pug'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Small compact dog with extremely wrinkled flat face, large round eyes, deep facial creases, curly tail, and cobby square body.';
      $profile['puppy_to_1yr']           = 'PUG ADULT — HEIGHT BARELY CHANGES. FACE transforms: deep forehead WRINKLES multiply and deepen, facial folds around flat nose pronounced, large round eyes more prominent, curly tail tightens. Body becomes COBBY and compact. Deep black mask intensifies.';
      $profile['puppy_to_3yr']           = 'FULL PUG: Deeply wrinkled flat face, multiple forehead folds, prominent large round eyes, deep nose rope, black mask stark, compact square cobby body, tight double-curl tail.';
      $profile['aging_3yr_face']         = 'GRAYING: Gray/white hairs appearing in the black facial mask area. Wrinkles deepen further.';
    } elseif ($this->mb($b, ['boston terrier'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Compact square dog with distinctive tuxedo black-and-white markings, erect bat ears, flat face with deep stop, and athletic compact build.';
      $profile['puppy_to_1yr']           = 'BOSTON TERRIER: Bat ears become FULLY ERECT and rigid. Tuxedo markings sharpen dramatically. Head broadens and squares off. Flat face becomes more defined. Compact athletic body develops.';
      $profile['puppy_to_3yr']           = 'FULL BOSTON: Perfect tuxedo markings. Erect bat ears. Broad flat-faced head. Compact athletic body. Bright alert expression.';
    } elseif ($this->mb($b, ['cavalier king charles', 'cavalier', 'king charles spaniel', 'english toy spaniel'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'Small spaniel with large melting dark eyes, long silky feathered coat in chestnut/black-tan/ruby/blenheim colors, gentle aristocratic expression.';
      $profile['puppy_to_1yr']           = 'CAVALIER COAT: Feathering grows on ears, legs, and tail. Silky coat lengthens. Large round dark eyes become more prominent. Soft gentle expression deepens.';
      $profile['puppy_to_3yr']           = 'FULL CAVALIER: Long silky coat with full ear feathering and leg feathering. Large melting dark eyes. Gentle aristocratic expression. Rich color at full depth.';
      $profile['aging_3yr_coat']         = 'Full feathering everywhere — silky and flowing.';
    }

    // ══════════════════════════════════════════════════════════════════
    // YORKSHIRE TERRIER / SMALL TERRIERS / MALTESE
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Tiny dog with impossibly long, fine, silky STEEL-BLUE and TAN coat parted down the middle, perfectly erect small V-shaped ears, confident terrier personality.';
      $profile['puppy_to_1yr']           = 'YORKIE COAT TRANSFORMATION — HEIGHT STAYS SAME. ENTIRE TRANSFORMATION IS THE COAT. Replace puppy short fluffy black-and-tan with GROWING LONG SILKY STRAIGHT COAT — body developing steel-blue/silver (NOT black), face/legs rich tan/gold. Hair growing visibly longer and silkier. Erect ears firmly upright. Terrier sharpness emerging.';
      $profile['puppy_to_3yr']           = 'FULL YORKIE ADULT: Long flowing silky coat parted down the middle, reaching toward the floor — STEEL BLUE on body, RICH TAN/GOLD on head and legs. Perfectly erect tiny pointed ears. The body coat MUST be steel-blue, NOT black. Coat MUST be long and STRAIGHT, not fluffy. This is the signature Yorkie adult appearance.';
      $profile['aging_3yr_coat']         = 'Coat at full adult length — silky, flowing, straight. Steel-blue body, rich tan head and legs.';
      $profile['aging_3yr_face']         = 'Minimal graying. Slight lightening of tan possible. Terrier expression sharp.';
    } elseif ($this->mb($b, ['maltese'])) {
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Tiny all-white dog completely covered in flowing, silky, pure white coat that reaches the ground — like a living cloud.';
      $profile['puppy_to_1yr']           = 'MALTESE COAT — HEIGHT STAYS SAME. Pure white coat grows DRAMATICALLY LONGER and silkier — flowing EVERYWHERE. Eyes peek through longer facial hair. Body hidden under flowing white silk.';
      $profile['puppy_to_3yr']           = 'FULL MALTESE: Entire body hidden under floor-length flowing pure white silk coat. Only black nose and dark eyes visible. The coat IS the breed.';
      $profile['aging_3yr_coat']         = 'Floor-length pure white silk at maximum adult length.';
    } elseif ($this->mb($b, ['shih tzu', 'shih-tzu', 'shih'])) {
      $profile['size_category']          = 'small';
      $profile['brachycephalic']         = true;
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Small chrysanthemum-faced dog with long luxurious flowing coat, flat face with long facial hair, large dark eyes, and silky coat covering the entire body.';
      $profile['puppy_to_1yr']           = 'SHIH TZU — HEIGHT STAYS SAME. Coat grows MUCH LONGER — flowing body coat, long facial hair from flat nose. Flat face remains. Round large eyes more prominent. Colors intensify.';
      $profile['puppy_to_3yr']           = 'FULL SHIH TZU: Magnificent long flowing coat covering entire body. Facial hair long and flowing outward. Large round eyes. Full chrysanthemum face expression.';
    } elseif ($this->mb($b, ['chihuahua'])) {
      $isLongCoat = $this->mb($b, ['long coat', 'long-coat', 'longhaired', 'long hair']);
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = $isLongCoat ? 'long_silky' : 'short';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = "World's smallest breed — tiny body with apple-domed skull, large prominent dark eyes, large erect ears. Despite tiny size, bold confident expression.";
      $profile['puppy_to_1yr']           = 'CHIHUAHUA — HEIGHT BARELY CHANGES. Head ROUNDS into classic APPLE-DOME skull. Large erect ears MUST stand perfectly upright and rigid. Eyes become proportionally very large and round. Body compact. ' . ($isLongCoat ? 'Coat grows into longer flowing feathering.' : 'Short coat becomes sleek and tight.');
      $profile['puppy_to_3yr']           = 'FULL CHIHUAHUA: Classic apple-head — perfectly round domed skull, enormous erect pointed ears, very large round dark eyes, compact tiny body. Bold alert expression.';
    } elseif ($this->mb($b, ['pomeranian'])) {
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Tiny fluffy lion-like dog with enormous stand-off double coat, foxy pointed face, tiny erect ears, thick full mane/ruff, heavily plumed curled tail.';
      $profile['puppy_to_1yr']           = 'POMERANIAN FLOOF EXPLOSION — HEIGHT STAYS SAME. COAT IS EVERYTHING. Replace puppy fluff with ENORMOUS stand-off double coat — massive ruff/mane around neck, thick coat standing away from body. Foxy pointed face becomes more angular. Tail becomes heavily plumed and curled over back.';
      $profile['puppy_to_3yr']           = 'FULL POMERANIAN: Ball of fluff — enormous stand-off coat twice body size. Full lion mane, heavily plumed tail, foxy pointed face, tiny erect ears. Miniature lion/fox.';
      $profile['aging_3yr_coat']         = 'Coat at maximum volume — the most voluminous coat relative to body size.';
    } elseif ($this->mb($b, ['papillon', 'phalene'])) {
      $isPhalene = $this->mb($b, ['phalene']);
      $profile['size_category']          = 'toy';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Tiny dog with enormous butterfly-like erect fringed ears (papillon) or drooping ears (phalene), long silky coat, elegant fine-boned body.';
      $profile['puppy_to_1yr']           = 'PAPILLON: Ears become ' . ($isPhalene ? 'long and drooping with heavy fringe' : 'ENORMOUS erect butterfly-wing shape with heavy silky fringe') . '. Silky coat develops flowing feathering. Body remains tiny but more refined.';
      $profile['puppy_to_3yr']           = 'FULL PAPILLON: ' . ($isPhalene ? 'Large drooping ears' : 'Enormous erect butterfly ears') . ' with full silky fringe. Long silky flowing coat everywhere. The butterfly ears are the defining feature.';
    } elseif ($this->mb($b, ['havanese', 'bichon frise', 'bolognese', 'coton de tulear', 'lowchen', 'maltipoo'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = $this->mb($b, ['bichon', 'maltipoo', 'coton']) ? 'curly' : 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'A small companion dog with a long, flowing, silky or wavy coat in white or varied colors. Cheerful, bright-eyed expression.';
      $profile['puppy_to_1yr']           = 'COMPANION DOG COAT: Replace puppy fluff with full adult coat — ' . ($this->mb($b, ['bichon']) ? 'THICK FLUFFY CURLY white coat, powder-puff appearance, double coat' : 'long flowing silky coat') . '. Body compact and cheerful.';
      $profile['puppy_to_3yr']           = 'FULL COMPANION ADULT: ' . ($this->mb($b, ['bichon']) ? 'Full powder-puff double coat at maximum volume. Pure white throughout.' : 'Long flowing silky coat everywhere.') . ' Bright expression, compact body.';
    } elseif ($this->mb($b, ['lhasa apso', 'tibetan terrier', 'tibetan spaniel'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Small Tibetan dog with a dense long coat that falls forward over the face, parted down the middle, rich coloring.';
      $profile['puppy_to_3yr']           = 'FULL LHASA: Long dense coat parted down the middle. Heavy fall of hair over the face. Rich coloring at full adult intensity. Compact sturdy body.';
    } elseif ($this->mb($b, ['pekingese', 'peke'])) {
      $profile['size_category']          = 'toy';
      $profile['brachycephalic']         = true;
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'Small flat-faced dog with an enormous flowing double coat that fans out around the body like a lion\'s mane. Very flat face with wrinkle.';
      $profile['puppy_to_3yr']           = 'FULL PEKINGESE: Enormous flowing double coat — lion mane effect. Very flat face with deep wrinkle. Heavy coat flowing in all directions. Regal ancient breed appearance.';
    }

    // ══════════════════════════════════════════════════════════════════
    // CORGI / DACHSHUND / BASSET — LONG AND LOW
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      $profile['size_category']          = 'small';
      $profile['body_shape']             = 'long_low';
      $profile['coat_type']              = 'double_coat';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'Long low dog with short powerful legs, very long muscular torso, large upright bat-like ears, foxy face, dense double coat with ruff.';
      $profile['puppy_to_1yr']           = 'CORGI LOW-RIDER: LEGS DO NOT GROW TALL — stay SHORT and POWERFUL. Body LENGTHENS significantly. Large pointed ears MUST stand PERFECTLY ERECT and rigid (not flopped). Dense double coat develops with ruff at neck. Foxy face becomes more defined.';
      $profile['puppy_to_3yr']           = 'FULL CORGI: Very long muscular body on short stumpy legs, large upright pointed ears, dense double coat with ruff, foxy face. The signature Corgi silhouette.';
      $profile['aging_3yr_face']         = 'SLIGHT GRAYING on muzzle. Ears remain fully erect.';
    } elseif ($this->mb($b, ['dachshund', 'doxie', 'sausage dog', 'weiner dog', 'wiener dog'])) {
      $isLong = $this->mb($b, ['long', 'longhaired', 'long-haired', 'long hair']);
      $isWire = $this->mb($b, ['wire', 'wirehaired', 'wire-haired']);
      $isMini = $this->mb($b, ['mini', 'miniature']);
      $profile['size_category']          = $isMini ? 'toy' : 'small';
      $profile['body_shape']             = 'long_low';
      $profile['coat_type']              = $isLong ? 'long_silky' : ($isWire ? 'wire_harsh' : 'short');
      $profile['height_change']          = 'none';
      $profile['adult_size_description'] = 'The ultimate long-and-low dog — dramatically elongated sausage-shaped body on tiny short legs. Deep chest, very long torso.';
      $profile['puppy_to_1yr']           = 'DACHSHUND SAUSAGE — HEIGHT NEVER INCREASES. Body ELONGATES dramatically into the signature sausage shape. Deep chest develops. Very short stumpy legs remain short. ' . ($isLong ? 'Long silky coat develops feathering on ears and underside.' : ($isWire ? 'Wire coat becomes harsh and bristly with beard.' : 'Short coat tightens.')) . ' Deep chest very prominent.';
      $profile['puppy_to_3yr']           = 'FULL DACHSHUND: Maximum sausage — very long body, tiny legs, deep prominent chest. ' . ($isLong ? 'Full long silky coat.' : ($isWire ? 'Full harsh wire coat with beard.' : 'Tight smooth coat.'));
    } elseif ($this->mb($b, ['basset hound', 'basset'])) {
      $profile['size_category']          = 'medium';
      $profile['body_shape']             = 'long_low';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'Extremely heavy, low-slung — enormously long velvety ears, deeply wrinkled skin, large soulful eyes with prominent haw, deep chest almost touching ground, heavy bone.';
      $profile['puppy_to_1yr']           = 'BASSET HEAVY DEVELOPMENT: Ears GROW DRAMATICALLY LONGER — nearly touch the ground. Skin develops DEEP WRINKLES around face, neck, and legs. Eyes show more haw (red inner eyelid). Body very low and heavy. Dewlap neck folds appear.';
      $profile['puppy_to_3yr']           = 'FULL BASSET: Enormously long velvet ears, deeply wrinkled skin, very heavy low body, soulful drooping eyes. One of the most distinctive silhouettes.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING on muzzle. Deeper wrinkles. More prominent haw.';
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
      $profile['adult_size_description'] = 'Distinctive square-built dog with harsh wire coat, prominent beard and eyebrows, rectangular head.';
      $profile['puppy_to_1yr']    = 'SCHNAUZER WIRE COAT: Replace soft puppy coat with HARSH BRISTLY WIRE COAT. BEARD and EYEBROWS become very prominent — breed signature. Rectangular head develops. Body becomes square and muscular. Salt-and-pepper pattern sharpens.';
      $profile['puppy_to_3yr']    = 'FULL SCHNAUZER: Full harsh wire coat with PROMINENT BEARD and BUSHY EYEBROWS at maximum. Square body, rectangular head. Salt-and-pepper at full adult intensity.';
      $profile['aging_3yr_coat']  = 'Wire coat fully harsh. Beard and eyebrows at maximum volume.';
    }

    // ══════════════════════════════════════════════════════════════════
    // TERRIERS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['jack russell', 'parson russell', 'russell terrier'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = $this->mb($b, ['wire', 'rough']) ? 'wire_harsh' : 'short';
      $profile['height_change']          = 'minimal_increase';
      $profile['adult_size_description'] = 'Compact athletic small terrier — mostly white with tan/black patches, strong square-jawed head, intense expression, sturdy compact body.';
      $profile['puppy_to_1yr']           = 'JACK RUSSELL ADULT: Body compact, muscular, and solid. White with tan/black patches intensify. Head SQUARES OFF. Eyes develop intense, bold terrier expression.';
      $profile['puppy_to_3yr']           = 'FULL JACK RUSSELL: Classic compact athletic terrier — squared head, bold eyes, white/tan/black markings fully defined.';
    } elseif ($this->mb($b, ['west highland', 'westie', 'cairn terrier', 'scottish terrier', 'scottie', 'border terrier', 'norfolk terrier', 'norwich terrier', 'dandie dinmont', 'sealyham'])) {
      $profile['size_category']   = 'small';
      $profile['coat_type']       = 'wire_harsh';
      $profile['height_change']   = 'minimal_increase';
      $profile['puppy_to_1yr']    = 'TERRIER WIRE COAT: Replace puppy softness with HARSH WIRE COAT — rough, bristly. Beard and eyebrows become prominent. Face sharpens to distinctive terrier square jaw.';
      $profile['puppy_to_3yr']    = 'FULL WIRE TERRIER: Complete harsh wire coat, prominent facial furnishings, square terrier head. Bold confident expression.';
    } elseif ($this->mb($b, ['airedale terrier', 'airedale'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'wire_harsh';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Largest terrier — tall, athletic, black-and-tan wire coat, long flat rectangular head, V-shaped drop ears, distinctive beard, athletic upright carriage.';
      $profile['puppy_to_1yr']           = 'KING OF TERRIERS: Grows into LARGE ATHLETIC DOG. Harsh wire coat replaces puppy coat — black saddle/blanket with rich tan. BEARD and EYEBROWS develop prominently. Long rectangular head forms. Body tall and athletic.';
      $profile['puppy_to_3yr']           = 'FULL AIREDALE: Large athletic terrier with full harsh wire coat. Black saddle over rich tan. Full beard. Tall confident stance.';
    } elseif ($this->mb($b, ['soft coated wheaten terrier', 'wheaten terrier', 'wheaten'])) {
      $profile['size_category']   = 'medium';
      $profile['coat_type']       = 'wavy_curly';
      $profile['height_change']   = 'moderate_increase';
      $profile['puppy_to_1yr']    = 'WHEATEN: Replace puppy coat with soft wavy/silky wheaten-colored adult coat — falling naturally in waves. Beard and fall (hair over face) become more pronounced.';
      $profile['puppy_to_3yr']    = 'FULL WHEATEN: Full soft flowing wheat-colored coat. Distinctive fall of hair over face. Soft silky texture throughout.';
    } elseif ($this->mb($b, ['kerry blue terrier', 'irish terrier', 'welsh terrier', 'lakeland terrier', 'fox terrier', 'smooth fox terrier', 'wire fox terrier'])) {
      $isWire = $this->mb($b, ['wire', 'welsh', 'lakeland', 'kerry']);
      $profile['size_category']   = $this->mb($b, ['kerry', 'irish']) ? 'medium' : 'small';
      $profile['coat_type']       = $isWire ? 'wire_harsh' : 'short';
      $profile['height_change']   = 'moderate_increase';
      $profile['puppy_to_3yr']    = 'FULL TERRIER ADULT: Harsh wire coat (or sleek short) at full adult density. Square terrier head, strong jaw, bold expression.';
    }

    // ══════════════════════════════════════════════════════════════════
    // BEAGLE / HOUNDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['beagle'])) {
      $profile['size_category']          = 'small';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Compact sturdy scent hound — tricolor (black/tan/white) or two-color, square hound head, long pendulous soft ears, deep chest, friendly hound expression.';
      $profile['puppy_to_1yr']           = 'BEAGLE ADULT: Body sturdy and compact. Tricolor markings INTENSIFY — black saddle deepens, tan points sharpen, white brightens. Ears lengthen and become more pendulous. Head develops square hound proportions. Deep chest develops.';
      $profile['puppy_to_3yr']           = 'FULL BEAGLE: Classic scent hound — deep tricolor, long pendulous ears, square compact body, deep chest, friendly hound expression.';
      $profile['aging_3yr_face']         = 'MODERATE GRAYING on muzzle. Hound expression deepens.';
    } elseif ($this->mb($b, ['bloodhound', 'coonhound', 'redbone', 'bluetick', 'black and tan', 'treeing walker', 'plott hound', 'american english coonhound'])) {
      $profile['size_category']          = 'large';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Large heavy scent hound with deeply wrinkled skin, enormously long pendulous ears, prominent dewlap, large drooping eyes with red haw visible, heavy bone.';
      $profile['puppy_to_1yr']           = 'HOUND BULK: Body becomes LARGE and heavy. Skin develops DEEP WRINKLES on forehead and around face. Ears GROW VERY LONG and pendulous. Dewlap neck folds appear. Large drooping hound eyes with visible red haw.';
      $profile['puppy_to_3yr']           = 'FULL HOUND: Deeply wrinkled face, enormously long ears, heavy bone, powerful large body. Classic working hound.';
      $profile['aging_3yr_face']         = 'HEAVY GRAYING on muzzle. Deep wrinkles deepen. More soulful expression.';
    } elseif ($this->mb($b, ['rhodesian ridgeback'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Powerful athletic dog with distinctive ridge of reversed hair along the spine, deep wheaten/red-wheaten short coat, muscular lean build.';
      $profile['puppy_to_1yr']           = 'RIDGEBACK ATHLETE: Body becomes lean and powerfully muscled. Ridge along spine becomes very distinctive. Wheaten coat tightens. Deep chest develops. Athletic tuck.';
      $profile['puppy_to_3yr']           = 'FULL RIDGEBACK: Peak athletic lion dog. Powerful lean muscles, distinctive ridge, deep chest, wheaten coat. One of Africa\'s great hunting dogs.';
    } elseif ($this->mb($b, ['irish wolfhound'])) {
      $profile['size_category']          = 'giant';
      $profile['coat_type']              = 'wire_harsh';
      $profile['height_change']          = 'dramatic_increase';
      $profile['adult_size_description'] = 'Tallest dog breed — enormous rough-coated giant standing 30–35 inches, gentle giant expression, rough harsh coat.';
      $profile['puppy_to_3yr']           = 'FULL WOLFHOUND: One of the tallest animals on four legs. Enormous rough-coated giant. Rough harsh coat. Gentle but imposing. Head level with most adult humans when standing.';
    } elseif ($this->mb($b, ['deerhound', 'scottish deerhound'])) {
      $profile['size_category']          = 'giant';
      $profile['body_shape']             = 'sighthound';
      $profile['coat_type']              = 'wire_harsh';
      $profile['height_change']          = 'dramatic_increase';
      $profile['adult_size_description'] = 'Large rough-coated sighthound — lean, deep-chested, long-legged, with a shaggy but close-lying coat. Elegant and large.';
    }

    // ══════════════════════════════════════════════════════════════════
    // BERNESE / SWISS MOUNTAIN DOGS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['bernese mountain dog', 'berner', 'greater swiss mountain dog', 'appenzeller', 'entlebucher'])) {
      $isGSWD = $this->mb($b, ['greater swiss', 'swissie', 'appenzeller', 'entlebucher']);
      $profile['size_category']          = 'large';
      $profile['coat_type']              = $isGSWD ? 'short' : 'long_silky';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Large, sturdy tricolor mountain dog — black body with rust/tan points and white blaze/chest/paws. ' . ($isGSWD ? 'Short dense coat.' : 'Long thick silky coat with gentle waves.');
      $profile['puppy_to_1yr']           = 'MOUNTAIN DOG GROWTH: Tricolor markings become very sharp and bold — black, rust, and white clearly defined. Body becomes large and sturdy. ' . ($isGSWD ? 'Short coat densifies.' : 'Long coat develops thickness and slight waves.');
      $profile['puppy_to_3yr']           = 'FULL MOUNTAIN DOG: Large, sturdy, strikingly tricolored. Black, rust/tan, and white markings precisely defined. ' . ($isGSWD ? 'Short dense tricolor coat.' : 'Long flowing tricolor coat.');
      $profile['aging_3yr_face']         = 'MODERATE GRAYING around muzzle. Tan/rust points may lighten slightly. Distinguished mature expression.';
    }

    // ══════════════════════════════════════════════════════════════════
    // OLD ENGLISH SHEEPDOG / BOUVIER / BRIARD
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['old english sheepdog', 'oes', 'bobtail'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'wavy_curly';
      $profile['height_change']          = 'large_increase';
      $profile['adult_size_description'] = 'Large shaggy dog completely covered in thick, profuse, grey-and-white coat — even the face and eyes covered by long hair, creating an unmistakable bear-like appearance.';
      $profile['puppy_to_1yr']           = 'OES SHAGGY EMERGENCE: Body grows large. Thick shaggy grey-and-white coat develops dramatically — much denser and longer. Hair begins to cover face and eyes. Body hidden under growing shaggy coat.';
      $profile['puppy_to_3yr']           = 'FULL OES: Entire body hidden under profuse thick grey-and-white shaggy coat. Even eyes and face covered. Bear-like mound of fur. One of the most dramatically coated breeds.';
    } elseif ($this->mb($b, ['bouvier des flandres', 'bouvier', 'briard'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'wire_harsh';
      $profile['height_change']          = 'large_increase';
      $profile['puppy_to_3yr']           = 'FULL HERDING DOG: Large, powerful, with full harsh coat. Prominent beard and mustache. Strong square head. Dense rough double coat. Powerful working dog appearance.';
    }

    // ══════════════════════════════════════════════════════════════════
    // SPANIELS — KING CHARLES / PAPILLON already above
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['chinese shar pei', 'shar pei', 'shar-pei'])) {
      $profile['size_category']          = 'medium';
      $profile['brachycephalic']         = true;
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Square dog with extraordinarily loose, wrinkled skin especially around head and neck, small hippo-like head, tiny sunken eyes, blue-black tongue.';
      $profile['puppy_to_1yr']           = 'SHAR PEI WRINKLES: Body grows more square. Deep skin wrinkles INCREASE dramatically around head and neck. Head becomes hippo-like with heavy folds. Small sunken eyes with heavy skin above. Blue-black tongue visible.';
      $profile['puppy_to_3yr']           = 'FULL SHAR PEI: Heavily wrinkled square dog. Enormous skin folds around head, neck, and shoulders. Hippo-like head. The most wrinkled breed.';
    } elseif ($this->mb($b, ['chow chow'])) {
      $profile['size_category']          = 'large';
      $profile['coat_type']              = 'double_coat';
      $profile['body_shape']             = 'spitz';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Lion-like dog with enormous thick ruff around head and neck, distinctive blue-black tongue, straight hind legs giving stilted gait, and dense double coat.';
      $profile['puppy_to_1yr']           = 'CHOW LION EMERGENCE: ENORMOUS RUFF develops around neck and head. Blue-black tongue becomes very visible. Coat densifies dramatically. Body becomes square and solid. Stilted back legs develop.';
      $profile['puppy_to_3yr']           = 'FULL CHOW: Lion-like mane at full volume. Blue-black tongue. Dense thick coat. Square solid body. Scowling dignified expression.';
    } elseif ($this->mb($b, ['basenji'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'moderate_increase';
      $profile['body_shape']             = 'sighthound';
      $profile['adult_size_description'] = 'Small elegant primitive dog — wrinkled forehead, almond eyes, tightly curled tail over back, short sleek coat, and cat-like cleanliness.';
      $profile['puppy_to_3yr']           = 'FULL BASENJI: Elegant athletic build. Wrinkled forehead. Tightly curled tail. Short sleek coat. Almond eyes with alert expression. Barkless dignity.';
    } elseif ($this->mb($b, ['shiba inu', 'shiba'])) {
      $profile['size_category']          = 'small';
      $profile['coat_type']              = 'double_coat';
      $profile['body_shape']             = 'spitz';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Compact spitz-type dog — bold curled tail, erect pointed ears, fox-like face, thick double coat in red/black-tan/sesame/cream.';
      $profile['puppy_to_1yr']           = 'SHIBA: Thick double coat develops fully. Erect pointed ears become rigid. Tail curls firmly over back. Fox-like adult face with sharp expression. Rich coat color intensifies.';
      $profile['puppy_to_3yr']           = 'FULL SHIBA: Dense plush double coat, erect pointed ears, tight curled tail, fox-like face. Compact powerful body. Rich color.';
    } elseif ($this->mb($b, ['australian cattle dog', 'blue heeler', 'red heeler', 'queensland heeler', 'cattle dog'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'short';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Compact, muscular herding dog — blue or red speckled/mottled coat, pricked ears, strong athletic build, intense working expression.';
      $profile['puppy_to_1yr']           = 'CATTLE DOG: Blue or red mottling/speckling pattern INTENSIFIES dramatically. Body becomes compact and muscular. Erect ears firm. Intense working expression develops.';
      $profile['puppy_to_3yr']           = 'FULL CATTLE DOG: Peak working condition. Blue or red mottled coat at full adult pattern intensity. Compact powerful body. Intense intelligent expression.';
    } elseif ($this->mb($b, ['english springer spaniel', 'welsh springer', 'field spaniel', 'clumber', 'sussex spaniel', 'boykin'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'moderate_increase';
      $profile['puppy_to_3yr']           = 'FULL SPRINGER: Long silky coat with full feathering on ears, legs, and belly. Liver-and-white or black-and-white pattern at full contrast. Cheerful spaniel expression.';
    } elseif ($this->mb($b, ['hungarian puli', 'puli', 'komondor', 'bergamasco'])) {
      $profile['size_category']          = $this->mb($b, ['komondor']) ? 'large' : 'medium';
      $profile['coat_type']              = 'curly';
      $profile['height_change']          = 'moderate_increase';
      $profile['adult_size_description'] = 'Unique corded/dreadlocked dog — entire body covered in long, tightly twisted cords or dreadlocks that can reach the floor.';
      $profile['puppy_to_1yr']           = 'CORDED DOG: Puppy coat begins forming cords/dreadlocks. Cords start to develop and lengthen. Body covered in developing mat-like structures.';
      $profile['puppy_to_3yr']           = 'FULL CORDED ADULT: Entire body covered in long, tightly twisted cords/dreadlocks reaching toward the floor. The dog is barely visible under cords. Most unusual coat in dogdom.';
    } elseif ($this->mb($b, ['english cocker', 'american cocker', 'cocker'])) {
      $profile['size_category']          = 'medium';
      $profile['coat_type']              = 'long_silky';
      $profile['height_change']          = 'moderate_increase';
      $profile['puppy_to_3yr']           = 'FULL COCKER: Enormous flowing silky coat. Full feathering on ears (nearly to ground), chest, belly, and legs. Domed head. Soft expression.';
    }

    // ══════════════════════════════════════════════════════════════════
    // ASPIN / NATIVE DOGS / MIXED BREEDS
    // ══════════════════════════════════════════════════════════════════
    elseif ($this->mb($b, ['aspin', 'asong pinoy', 'philippine native', 'village dog', 'street dog', 'mixed breed', 'mutt', 'mixed'])) {
      $profile['size_category']   = 'medium';
      $profile['height_change']   = 'moderate_increase';
      $profile['adult_size_description'] = 'Lean athletic medium dog with smooth short coat, semi-erect or erect ears, sickle tail, and the lithe build of a primitive pariah dog.';
      $profile['puppy_to_1yr']    = 'NATIVE DOG MATURATION: Body becomes lean and athletic — visible tuck-up. Ears settle (semi-erect or erect). Short coat tightens and becomes sleeker. Legs lengthen to lean adult proportions. Sickle tail more defined. Adult primitive silhouette.';
      $profile['puppy_to_3yr']    = 'FULL ADULT NATIVE DOG: Lean athletic medium dog. Tight short coat, well-defined lean musculature, settled ears, sickle tail. Primitive/pariah dog appearance at full maturity.';
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
