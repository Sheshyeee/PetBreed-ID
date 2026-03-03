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

  // ─────────────────────────────────────────────────────────────────────
  //  PROMPT BUILDER — AGGRESSIVE, BREED + AGE AWARE
  // ─────────────────────────────────────────────────────────────────────
  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed       = $profile['breed'];
    $size        = $profile['size_category'] ?? 'medium';
    $coat        = $profile['coat_type'] ?? 'short';
    $isBrachy    = $profile['brachycephalic'] ?? false;
    $bodyShape   = $profile['body_shape'] ?? 'standard';

    // Dynamic structural and facial changes based on breed profiles
    $agingBody1yr = $profile['aging_1yr_body'] ?? 'Visibly increase muscle mass and leg length. Lose all puppy roundness.';
    $agingFace1yr = $profile['aging_1yr_face'] ?? 'Elongate the snout and sharpen facial structure.';
    $agingBody3yr = $profile['aging_3yr_body'] ?? 'Thicken the neck and chest. Add heavy adult weight.';
    $agingFace3yr = $profile['aging_3yr_face'] ?? 'Add distinct white/gray hairs around the muzzle and chin.';

    $L = [];

    $L[] = '╔═══════════════════════════════════════════════════════════════╗';
    $L[] = '║  IMAGE MODIFICATION COMMAND: STRIKING TRANSFORMATION REQUIRED ║';
    $L[] = '╚═══════════════════════════════════════════════════════════════╝';
    $L[] = "TARGET BREED: {$breed}";
    $L[] = "TASK: Evolve this EXACT dog to be EXACTLY +{$targetYears} year(s) older.";
    $L[] = 'CRITICAL RULE: You MUST create a striking, undeniable visual change in the dog\'s physical geometry. Do NOT just apply a filter.';
    $L[] = '';

    // SCENE LOCK (Preserve original image integrity)
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 1: THE LOCKS (NEVER ALTER THESE)';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = '  • The background, environment, and all objects MUST remain 100% identical.';
    $L[] = '  • The dog\'s unique coat markings, eye color, and pose MUST remain identical.';
    $L[] = '';

    // DYNAMIC TRANSFORMATIONS
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "PHASE 2: MANDATORY STRUCTURAL AGING (+{$targetYears} Year(s))";
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

    if ($targetYears === 1) {
      $L[] = "🔴 STRUCTURAL SHIFT (Adolescent to Adult): {$agingBody1yr}";
      $L[] = "🔴 FACIAL/COAT SHIFT: {$agingFace1yr}";
      $L[] = "MANDATORY: Scale up the dog's height/size slightly while tucking the belly to show the transition to lean muscle.";
    } else {
      $L[] = "🔴 STRUCTURAL SHIFT (Full Maturity): {$agingBody3yr}";
      $L[] = "🔴 FACIAL/COAT SHIFT: {$agingFace3yr}";
      $L[] = "MANDATORY: Expand the chest width significantly. The silhouette MUST look heavier and more settled.";
    }

    $L[] = '';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = 'PHASE 3: BREED-SPECIFIC BIOLOGY GUARDRAILS';
    $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
    $L[] = "  • Coat Type ({$coat}): Make the coat texture appropriate for an older dog (denser, harsher, or longer depending on type).";

    if ($bodyShape === 'long_low') {
      $L[] = '  • LONG-AND-LOW BREED: Legs NEVER grow taller. Lengthen and thicken the torso only.';
    }
    if ($isBrachy) {
      $L[] = '  • BRACHYCEPHALIC BREED: Do NOT elongate the snout. Flat face is PERMANENT. Age by deepening facial wrinkles and sagging jowls.';
    }

    $L[] = '';
    $L[] = 'FINAL GATE: If the output does not immediately look physically older, thicker, or grayer than the input, YOU HAVE FAILED. Execute transformation now.';

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

  // ─────────────────────────────────────────────────────────────────────
  //  BREED PROFILES — EXTREME DESCRIPTIONS FOR THE AI
  // ─────────────────────────────────────────────────────────────────────
  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    // DEFAULT BASE (If breed isn't matched)
    $profile = [
      'breed' => $breed,
      'size_category' => 'medium',
      'body_shape' => 'standard',
      'coat_type' => 'short',
      'brachycephalic' => false,
      'aging_1yr_body' => 'Visibly increase muscle mass. Widen the chest cavity. Legs should look thicker and fully developed.',
      'aging_1yr_face' => 'Elongate the snout slightly. Remove puppy roundness from cheeks.',
      'aging_3yr_body' => 'Thicken the neck significantly. The body should look dense, heavy, and fully settled into adult weight.',
      'aging_3yr_face' => 'PROMINENT GRAYING: Add highly visible white and gray hairs covering the chin, muzzle, and dusting the eyebrows.',
    ];

    // LARGE/MOLOSSER BREEDS (Boxers, Rotts, Mastiffs, Danes, Bulldogs, Pitbulls)
    if ($this->mb($b, ['boxer', 'rottweiler', 'mastiff', 'dane', 'bully', 'bulldog', 'pit', 'staffy'])) {
      $profile['size_category'] = 'large';
      $profile['brachycephalic'] = $this->mb($b, ['boxer', 'bulldog', 'bully']);
      $profile['aging_1yr_body'] = 'DRAMATIC MASS INCREASE: Broaden the shoulders and expand the chest into a massive barrel shape. Must look significantly more muscular and powerful.';
      $profile['aging_1yr_face'] = 'Widen the skull. Muzzle becomes blocky and square. Deepen forehead wrinkles.';
      $profile['aging_3yr_body'] = 'HEAVY MATURITY: Add heavy, thick mass to the neck. The jowls (lips) and neck skin should noticeably sag downwards showing gravity.';
      $profile['aging_3yr_face'] = 'HEAVY GRAYING: Paint distinct, stark white/frosty gray fur all over the muzzle, lips, and under the chin. Deepen facial creases prominently.';
    }
    // SHEPHERDS/WORKING/SPITZ (GSD, Malinois, Husky, Malamute, Collie, Akita)
    elseif ($this->mb($b, ['shepherd', 'malinois', 'husky', 'collie', 'malamute', 'akita', 'samoyed'])) {
      $profile['size_category'] = 'large';
      $profile['coat_type'] = 'double_coat';
      $profile['aging_1yr_body'] = 'COAT EXPLOSION: Puppy fuzz replaced by a thick, coarse adult double-coat. Widen chest and add noticeable lean muscle to hindquarters.';
      $profile['aging_1yr_face'] = 'Snout becomes highly elongated and wolf-like. Ears must be perfectly erect, firm, and proportionate.';
      $profile['aging_3yr_body'] = 'STURDY BUILD: Develop a highly visible, thick "ruff" or mane of fur around the neck and shoulders. Chest drops deeper.';
      $profile['aging_3yr_face'] = 'SILVERING: Lighten the dark mask on the face. Add a distinct dusting of silver/white guard hairs across the top of the head, eyebrows, and sides of muzzle.';
    }
    // TOY/SMALL BREEDS (Poodle, Terrier, Chihuahua, Shih Tzu, Yorkie, Pug)
    elseif ($this->mb($b, ['poodle', 'terrier', 'yorkie', 'chi', 'shi', 'pug', 'maltese', 'pom'])) {
      $profile['size_category'] = 'small';
      $profile['brachycephalic'] = $this->mb($b, ['pug', 'shi']);
      $profile['coat_type'] = $this->mb($b, ['poodle', 'terrier']) ? 'curly/fluffy' : 'long_silky';
      $profile['aging_1yr_body'] = 'TEXTURE OVERHAUL: Stop increasing physical height. Instead, drastically change the coat texture—make it noticeably longer or curlier. Body becomes compact and solid.';
      $profile['aging_1yr_face'] = 'Eyes appear slightly smaller in proportion to the fully grown head. Grow the facial hair (beard/mustache) significantly if it is a long-haired breed.';
      $profile['aging_3yr_body'] = 'Coat loses puppy shine, becoming highly textured and thick. Posture is rigid and alert.';
      $profile['aging_3yr_face'] = 'EXTREME FROSTING: The most noticeable change MUST be a heavy mask of white/gray fur completely surrounding the nose, mouth, and eyes.';
    }
    // LONG & LOW (Corgi, Dachshund, Basset)
    elseif ($this->mb($b, ['corgi', 'dachshund', 'basset'])) {
      $profile['body_shape'] = 'long_low';
      $profile['aging_1yr_body'] = 'Elongate the torso significantly and widen the chest. KEEP LEGS SHORT AND STUBBY.';
      $profile['aging_3yr_body'] = 'Thicken the body, making the dog look heavier and lower to the ground. Noticeable chest drop.';
    }

    return $profile;
  }

  // Ensure you have this helper method right below getBreedProfile if you don't already:
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
