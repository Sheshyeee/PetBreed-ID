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

  // ── FIX 1: flash-image first (fast ~15s), pro as fallback only ──────
  private const MODEL_PRIORITY = [
    'gemini-3.1-flash-image-preview',        // Fast — primary (~15-20s)
    'gemini-3-pro-image-preview',            // Slow but high quality — fallback only (~80s)
    'gemini-2.5-flash-image',                // Legacy fallback
    'gemini-2.0-flash-exp-image-generation', // Last resort
  ];

  private const SEND_SIZE = 1024;
  private const MAX_SIZE  = 1536;

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

      Log::info("📐 Image sent to Gemini: {$imageData['sendWidth']}x{$imageData['sendHeight']}");

      $currentAgeStage = $this->detectAgeStage($imageData);
      Log::info("🔍 Detected age stage: {$currentAgeStage}");

      $detectedMarkings = $this->detectCoatMarkings($imageData);
      Log::info("🎨 Detected markings: {$detectedMarkings}");

      $breedProfile                        = $this->getBreedProfile($this->breed);
      $breedProfile['detected_age_stage']  = $currentAgeStage;
      $breedProfile['detected_markings']   = $detectedMarkings;

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

      if ($finalStatus === 'complete') {
        try {
          $ageProfiles = $this->generateAgeProfiles($breedProfile);
          if (!empty($ageProfiles)) {
            $currentData = json_decode($result->simulation_data, true) ?? [];
            $currentData['age_profiles'] = $ageProfiles;
            $result->update(['simulation_data' => json_encode($currentData)]);
            Log::info('✅ Age profiles stored for breed: ' . $breedProfile['breed']);
          }
        } catch (\Exception $e) {
          Log::warning('⚠️ Age profile generation failed (non-critical): ' . $e->getMessage());
        }
      }

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
  //  AGE STAGE DETECTION
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
              'You are a veterinary age assessment expert. Examine this dog photo with forensic precision.\n\n' .
                'PUPPY SIGNALS (count how many you see):\n' .
                '- Head disproportionately large relative to body\n' .
                '- Paws appear oversized relative to legs\n' .
                '- Short stumpy legs relative to torso\n' .
                '- Rounded barrel-shaped abdomen / potbelly\n' .
                '- Round soft baby-like facial structure\n' .
                '- Thin wispy fluffy or soft puppy coat\n' .
                '- Short muzzle proportionally to skull\n' .
                '- Ears partially or fully flopped, not yet settled\n' .
                '- Lack of defined muscle mass\n' .
                '- No gray/white on muzzle or face\n' .
                '- Overall baby-animal appearance with soft curves\n\n' .
                'If 3+ puppy signals present: puppy or teenager.\n\n' .
                'ADULT SIGNALS: Proportionate head-to-body, defined adult musculature, fully developed coat, settled ears, balanced muzzle.\n' .
                'SENIOR SIGNALS: Gray/white muzzle, cloudy eyes, skin sagging, thinner/duller coat.\n\n' .
                'CLASSIFICATIONS:\n' .
                'newborn_puppy = under 3 months\n' .
                'puppy = 3-6 months\n' .
                'teenager = 6-12 months\n' .
                'young_adult = 1-2 years\n' .
                'adult = 2-7 years\n' .
                'senior = 7+ years\n\n' .
                'Reply with EXACTLY ONE word: newborn_puppy | puppy | teenager | young_adult | adult | senior',
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
  //  COAT MARKINGS DETECTION
  // ─────────────────────────────────────────────────────────────────────

  private function detectCoatMarkings(array $imageData): string
  {
    try {
      $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
      $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

      $payload = [
        'contents' => [[
          'parts' => [
            [
              'text' =>
              'Describe this dog\'s coat color and markings in precise detail. ' .
                'Be specific about: (1) the base coat color, (2) every white or lighter area and exactly where it is (muzzle, blaze, chest, belly, paws, tail tip etc), ' .
                '(3) any darker patches and where they are, (4) any other distinctive color patterns. ' .
                'Write 2-3 sentences maximum. Be factual and specific. Example: ' .
                '"The dog has a tan/golden-brown coat on the back and sides. It has a distinct white blaze running from the forehead down the muzzle, white chest and belly, and white lower legs and paws. The tail tip is white."',
            ],
            [
              'inlineData' => [
                'mimeType' => $imageData['mimeType'],
                'data'     => $imageData['base64'],
              ],
            ],
          ],
        ]],
        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 150],
      ];

      $client   = new Client(['timeout' => 20]);
      $response = $client->post($endpoint, [
        'json'    => $payload,
        'headers' => ['Content-Type' => 'application/json'],
      ]);
      $data = json_decode($response->getBody()->getContents(), true);
      $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
      return $text ?: 'same coat color and markings as in the photo';
    } catch (\Exception $e) {
      Log::warning('Markings detection failed: ' . $e->getMessage());
      return 'same coat color and markings as in the photo';
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
        'temperature'        => 0.2,
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
  //  AGING PROMPT BUILDER
  // ─────────────────────────────────────────────────────────────────────

  private function buildAgingPrompt(array $profile, int $targetYears): string
  {
    $breed            = $profile['breed'];
    $ageStage         = $profile['detected_age_stage']  ?? 'adult';
    $detectedMarkings = $profile['detected_markings']   ?? 'same coat color and markings as in the photo';

    $isPuppy  = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager']);
    $isYoung  = ($ageStage === 'young_adult');
    $isSenior = ($ageStage === 'senior');
    $isAdult  = !$isPuppy && !$isYoung && !$isSenior;

    $agingDesc = $this->buildNaturalAgingDescription($profile, $ageStage, $targetYears);
    $guardrail = $this->buildBreedGuardrail($profile);

    $prompt  = "You are editing a dog photo. Make ONLY the age-related changes listed below. ";
    $prompt .= "Everything else in the photo must remain pixel-perfect identical.\n\n";

    $prompt .= "THIS DOG'S EXACT COAT MARKINGS (you MUST preserve all of these perfectly):\n";
    $prompt .= "{$detectedMarkings}\n";
    $prompt .= "Do NOT change, remove, or alter ANY of the above markings. ";
    $prompt .= "If the dog has a white chest — keep the white chest. ";
    $prompt .= "If the dog has a white blaze — keep the white blaze. ";
    $prompt .= "Every color patch must appear in exactly the same location as in the original photo.\n\n";

    $prompt .= "ALSO KEEP UNCHANGED:\n";
    $prompt .= "- Background, floor, walls, environment (identical)\n";
    $prompt .= "- Dog's pose and body position (identical)\n";
    $prompt .= "- Camera angle and framing (identical)\n";
    $prompt .= "- Eye color (identical)\n";
    $prompt .= "- Photo sharpness and quality level (identical — NOT blurry, NOT painterly)\n\n";

    $prompt .= "AGE-RELATED CHANGES TO MAKE (for a {$breed} aged +{$targetYears} year(s)):\n";
    $prompt .= $agingDesc . "\n\n";

    if ($guardrail) {
      $prompt .= "BREED NOTE: {$guardrail}\n\n";
    }

    $prompt .= "OUTPUT: A sharp, photorealistic photo — the same dog, same scene, aged by {$targetYears} year(s). ";
    $prompt .= "Not a painting. Not blurry. A real-looking photograph with clear aging differences visible.";

    return $prompt;
  }

  private function buildNaturalAgingDescription(array $profile, string $ageStage, int $targetYears): string
  {
    $breed     = $profile['breed'];
    $size      = $profile['size_category']   ?? 'medium';
    $coat      = $profile['coat_type']        ?? 'short';
    $isBrachy  = $profile['brachycephalic']   ?? false;
    $bodyShape = $profile['body_shape']       ?? 'standard';
    $heightChg = $profile['height_change']    ?? 'moderate_increase';

    $isPuppy  = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager']);
    $isYoung  = ($ageStage === 'young_adult');
    $isSenior = ($ageStage === 'senior');
    $isAdult  = !$isPuppy && !$isYoung && !$isSenior;

    $lines = [];

    if ($isPuppy) {
      if ($targetYears === 1) {
        switch ($heightChg) {
          case 'dramatic_increase':
          case 'large_increase':
            $lines[] = "- Body is significantly larger and taller — legs are much longer and more muscular";
            $lines[] = "- Head is now proportionate to the body (no longer oversized baby head)";
            break;
          case 'moderate_increase':
            $lines[] = "- Body is noticeably taller and longer — legs are clearly longer than in the puppy photo";
            $lines[] = "- Head is more proportionate to body, losing the baby-round shape";
            break;
          case 'minimal_increase':
            $lines[] = "- Body is heavier and denser rather than taller — legs stay short but muscular";
            $lines[] = "- Head is more square and defined, losing the baby-round shape";
            break;
          case 'none':
            $lines[] = "- Body shape is more defined and adult — even at same height, proportions are mature";
            $lines[] = "- Head has adult proportions and sharper features";
            break;
        }
        $lines[] = "- Face is sharper and more angular — no more soft round baby face";
        $lines[] = "- Abdomen is flat and tucked, not round/barrel-shaped";
        $lines[] = "- Paws are proportionate to the body (no longer oversized)";
        $lines[] = "- Ears are in their settled adult position for this breed";
        $lines[] = "- Coat is denser and more defined adult coat texture";
      } else {
        $adultDesc = $profile['adult_size_description'] ?? '';
        switch ($heightChg) {
          case 'dramatic_increase':
            $lines[] = "- The dog is now a GIANT — enormous body, 3-4x larger than the puppy";
            $lines[] = "- Massive bone structure, towering presence, thick powerful legs";
            break;
          case 'large_increase':
            $lines[] = "- The dog is fully grown and large — 2-3x the size of the puppy";
            $lines[] = "- Powerful legs, developed musculature throughout";
            break;
          case 'moderate_increase':
            $lines[] = "- The dog is fully grown — 1.5-2x taller and heavier than the puppy";
            $lines[] = "- Adult legs, developed chest and hindquarters";
            break;
          case 'minimal_increase':
            $lines[] = "- The dog is low-and-heavy — same low profile but much heavier and more muscular";
            break;
          case 'none':
            $lines[] = "- Same small height but completely adult proportions and features";
            break;
        }
        $lines[] = "- Fully adult head with proper proportions — muzzle is at full adult length";
        $lines[] = "- Deep chest, flat/tucked abdomen, full muscle development";
        $lines[] = "- Adult ears firmly set in breed-correct position";
        $lines[] = "- Full adult coat texture and density";
        if ($adultDesc) {
          $lines[] = "- This dog should look like: {$adultDesc}";
        }
      }
    } elseif ($isYoung) {
      if ($targetYears === 1) {
        $lines[] = "- Chest is slightly broader and deeper than in the photo";
        $lines[] = "- Neck is slightly thicker";
        $lines[] = "- Face looks more mature and settled — any remaining youthful softness is gone";
        $lines[] = "- A few silver/white hairs just at the very tip of the muzzle (2-5 hairs)";
      } else {
        $lines[] = "- Body at peak adult muscle development — chest broader, neck thicker, hindquarters more defined";
        $lines[] = "- Clear white/silver hairs covering the muzzle tip and chin (visible graying starting)";
        $lines[] = "- Face has a settled, experienced mature expression";
        $lines[] = "- Jowls very slightly more developed";
      }
    } elseif ($isSenior) {
      if ($targetYears === 1) {
        $lines[] = "- More white/gray fur on muzzle — expanded from whatever is in the photo";
        $lines[] = "- Eyes look slightly cloudier and more deep-set";
        $lines[] = "- Jowls/neck skin slightly saggier than in the photo";
        $lines[] = "- Coat appears slightly coarser and less shiny";
      } else {
        $lines[] = "- Muzzle is predominantly white/silver — much more graying than in the photo";
        $lines[] = "- White/silver fur around the eyes as well";
        $lines[] = "- Eyes visibly cloudier with age";
        $lines[] = "- Significant skin sagging on jowls and under the chin";
        $lines[] = "- Coat noticeably thinner and duller than in the photo";
        $lines[] = "- Slightly reduced muscle mass — less defined than prime adult";
      }
    } else {
      if ($targetYears === 1) {
        $lines[] = "- Add clearly visible white/silver hairs on the muzzle tip and chin — at least 10-20 hairs, clearly noticeable";
        $lines[] = "- Face looks marginally more mature — slightly more settled expression";
        $lines[] = "- Body very slightly heavier/more filled out in chest and neck area";
        $lines[] = "- The change should be subtle but definitely noticeable when comparing";
      } else {
        $lines[] = "- The muzzle tip and chin have clear white/gray graying — at least 30-50% of the muzzle is now gray/white";
        $lines[] = "- Sparse gray/white hairs appear around the eyes";
        $lines[] = "- Jowls are more developed and slightly saggy";
        $lines[] = "- Neck is thicker and chest is broader — body looks heavier and more settled";
        $lines[] = "- The dog has a more experienced, mature expression";
        $lines[] = "- Coat is marginally coarser and less glossy than in the photo";
      }
    }

    if ($isPuppy) {
      $b = strtolower($breed);
      switch ($coat) {
        case 'long_silky':
          if ($this->mb($b, ['yorkshire', 'yorkie'])) {
            $lines[] = "- Coat has transformed from short puppy fluff to long, straight, silky adult coat — steel-blue/silver on the body, rich golden-tan on the head and legs";
          } elseif ($this->mb($b, ['maltese'])) {
            $lines[] = "- Pure white coat is now much longer and more flowing";
          } elseif ($this->mb($b, ['golden retriever'])) {
            $lines[] = "- Golden coat is now wavier and has feathering developing on chest, legs, and tail";
          } elseif ($this->mb($b, ['collie', 'sheltie'])) {
            $lines[] = "- Growing mane/frill forming at the neck and chest";
          } else {
            $lines[] = "- Coat is noticeably longer and silkier than the puppy fluff";
          }
          break;
        case 'double_coat':
          if ($this->mb($b, ['pomeranian'])) {
            $lines[] = "- Coat has exploded into the enormous stand-off double coat with massive ruff/mane — completely different from puppy fluff";
          } else {
            $lines[] = "- Puppy fuzz replaced by thick, dense double coat — much more voluminous";
          }
          break;
        case 'curly':
        case 'wavy_curly':
          $lines[] = "- Puppy fluff replaced by " . ($targetYears === 3 ? "full tight adult curls/waves" : "developing curls/waves") . " — denser and more voluminous";
          break;
        case 'wire':
        case 'wire_harsh':
          $lines[] = "- Soft puppy coat replaced by harsh, bristly wire coat with prominent beard/eyebrows";
          break;
        default:
          $lines[] = "- Puppy fuzz replaced by sleek, tight, dense adult short coat";
          break;
      }
    }

    return implode("\n", $lines);
  }

  private function buildBreedGuardrail(array $profile): string
  {
    $b         = strtolower($profile['breed']);
    $bodyShape = $profile['body_shape']     ?? 'standard';
    $isBrachy  = $profile['brachycephalic'] ?? false;
    $size      = $profile['size_category']  ?? 'medium';
    $heightChg = $profile['height_change']  ?? 'moderate_increase';

    $notes = [];

    if ($bodyShape === 'long_low') {
      $notes[] = "This is a long-and-low breed — legs stay short, the body grows longer and heavier, never taller.";
    }
    if ($isBrachy) {
      $notes[] = "This breed has a permanently flat/pushed-in face — do NOT elongate the muzzle. Show aging through wrinkles and jowls, not muzzle length.";
    }
    if ($bodyShape === 'sighthound') {
      $notes[] = "This is a sighthound — always lean and aerodynamic with a deep chest tuck. Never becomes fat or heavy.";
    }
    if ($bodyShape === 'spitz') {
      $notes[] = "This is a spitz breed — always has erect pointed ears and a curled tail over the back.";
    }
    if ($this->mb($b, ['french bulldog', 'frenchie', 'boston terrier', 'chihuahua'])) {
      $notes[] = "This breed has large erect bat/triangle ears that stand up straight — keep them fully upright.";
    }
    if ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
      $notes[] = "This breed has large upright pointed ears — both must stand straight up in the adult.";
    }
    if ($this->mb($b, ['dachshund', 'doxie', 'wiener', 'weiner', 'sausage'])) {
      $notes[] = "Dachshund: the body grows longer and lower, legs never grow taller. Classic sausage silhouette.";
    }

    return implode(" ", $notes);
  }

  // ─────────────────────────────────────────────────────────────────────
  //  IMAGE PREPARATION
  // ─────────────────────────────────────────────────────────────────────

  private function _REMOVED_buildOutputDescription(array $profile, string $ageStage, int $targetYears): array { return []; }
  private function _REMOVED_getBreedSpecificOutputLines(array $profile, string $ageStage, int $targetYears): array { return []; }

  private function prepareImage(string $fullPath): ?array
  {
    try {
      $cacheKey = 'hq_img_v5_' . md5($fullPath);

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
          $imageContents = $this->scaleImage($imageContents, self::SEND_SIZE);
          Log::info("📐 Upscaled {$origW}x{$origH} → longest side " . self::SEND_SIZE . "px");
        } elseif ($longest > self::MAX_SIZE) {
          $imageContents = $this->scaleImage($imageContents, self::MAX_SIZE);
          Log::info("📐 Downscaled {$origW}x{$origH} → longest side " . self::MAX_SIZE . "px");
        }

        $scaledInfo = @getimagesizefromstring($imageContents);
        $sendW      = $scaledInfo ? $scaledInfo[0] : $origW;
        $sendH      = $scaledInfo ? $scaledInfo[1] : $origH;

        $img = @imagecreatefromstring($imageContents);
        if (!$img) throw new \Exception('GD cannot parse image');
        ob_start();
        imagejpeg($img, null, 95);
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
    imagejpeg($dst, null, 95);
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
  //  COMPREHENSIVE BREED PROFILES — all intact, unchanged
  // ─────────────────────────────────────────────────────────────────────

  private function getBreedProfile(string $breed): array
  {
    $b = strtolower(trim($breed));

    $profile = [
      'breed'                    => $breed,
      'size_category'            => 'medium',
      'body_shape'               => 'standard',
      'coat_type'                => 'short',
      'brachycephalic'           => false,
      'growth_rate'              => 'standard',
      'height_change'            => 'moderate_increase',
      'adult_size_description'   => 'A medium-sized adult dog with well-developed musculature and a fully settled adult coat.',
    ];

    if ($this->mb($b, ['great dane','irish wolfhound','saint bernard','newfoundland','leonberger','mastiff','great pyrenees','anatolian','kangal','caucasian','tibetan mastiff','boerboel','cane corso','dogue de bordeaux','french mastiff','neapolitan mastiff','broholmer','moscow watchdog'])) {
      $profile['size_category']        = 'giant';
      $profile['height_change']        = 'dramatic_increase';
      $profile['adult_size_description'] = 'One of the largest dog breeds — a towering, massively built adult standing 28–35 inches tall with enormous bone structure, broad skull, and imposing physical presence.';
      $profile['brachycephalic']       = $this->mb($b, ['mastiff','saint bernard','leonberger','cane corso','dogue','neapolitan','broholmer']);
    } elseif ($this->mb($b, ['german shepherd','gsd','alsatian','belgian malinois','dutch shepherd','belgian tervuren','belgian laekenois','belgian shepherd'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'double_coat';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'A powerful, athletic dog standing 22–26 inches — wolf-like, lean-muscled, with dense double coat, perfectly erect ears, and long confident stride.';
    } elseif ($this->mb($b, ['siberian husky','husky','alaskan malamute','malamute','samoyed','akita','shiba inu','shiba','chow chow','keeshond','spitz','american akita','japanese akita'])) {
      $isLarge = $this->mb($b, ['malamute','akita','american akita','chow chow']);
      $profile['size_category'] = $isLarge ? 'large' : 'medium';
      $profile['coat_type']     = 'double_coat';
      $profile['body_shape']    = 'spitz';
      $profile['height_change'] = $isLarge ? 'large_increase' : 'moderate_increase';
      $profile['adult_size_description'] = 'A Nordic-type dog with thick plush double coat, erect pointed ears, curled tail over back, and compact powerful build.';
    } elseif ($this->mb($b, ['golden retriever'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'long_silky';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'A large well-proportioned dog with thick golden flowing coat, broad head, soft intelligent eyes, deep chest, and feathering on legs, chest, and tail.';
    } elseif ($this->mb($b, ['labrador retriever','labrador','lab'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'short';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'A large athletic dog with broad otter-like tail, dense short coat, broad head, deep chest, and powerful stocky build.';
    } elseif ($this->mb($b, ['flat-coated retriever','flat coated','chesapeake bay'])) {
      $profile['size_category']  = 'large';
      $profile['coat_type']      = 'long_silky';
      $profile['height_change']  = 'large_increase';
    } elseif ($this->mb($b, ['standard poodle','miniature poodle','toy poodle','poodle'])) {
      $isStandard = $this->mb($b, ['standard']);
      $isMini     = $this->mb($b, ['miniature','mini']);
      $isToy      = $this->mb($b, ['toy']);
      $profile['size_category'] = $isStandard ? 'large' : ($isMini ? 'small' : ($isToy ? 'toy' : 'medium'));
      $profile['coat_type']     = 'curly';
      $profile['height_change'] = $isStandard ? 'large_increase' : ($isMini ? 'moderate_increase' : 'none');
      $profile['adult_size_description'] = $isStandard ? 'A tall elegant dog 21–27 inches — athletic with a long refined head and tight curly coat.' : 'A compact poodle with dense curly coat and refined build.';
    } elseif ($this->mb($b, ['goldendoodle','labradoodle','bernedoodle','aussiedoodle','sheepadoodle','newfypoo','pyredoodle'])) {
      $isLarge = $this->mb($b, ['standard','bernedoodle','sheepadoodle','newfypoo','pyredoodle']);
      $profile['size_category'] = $isLarge ? 'large' : 'medium';
      $profile['coat_type']     = 'wavy_curly';
      $profile['height_change'] = $isLarge ? 'large_increase' : 'moderate_increase';
    } elseif ($this->mb($b, ['cockapoo','cavapoo','maltipoo','schnoodle','yorkipoo'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = 'wavy_curly';
      $profile['height_change'] = 'none';
    } elseif ($this->mb($b, ['rottweiler','rottie'])) {
      $profile['size_category']        = 'large';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Massive blocky head with broad flat skull, prominent tan/mahogany points on black coat, thick heavily-muscled neck, broad chest.';
    } elseif ($this->mb($b, ['doberman','dobermann'])) {
      $profile['size_category']        = 'large';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Sleek athletic — long elegant neck, square body, sleek short coat showing every muscle, elegant pointed head with rust markings.';
    } elseif ($this->mb($b, ['boxer'])) {
      $profile['size_category']   = 'large';
      $profile['brachycephalic']  = true;
      $profile['height_change']   = 'large_increase';
      $profile['adult_size_description'] = 'Muscular square-built dog with broad brachycephalic head, undershot jaw with prominent flews, fawn or brindle short coat.';
    } elseif ($this->mb($b, ['pit bull','pitbull','american pit bull','american staffordshire','amstaff','american bully'])) {
      $profile['size_category']        = 'medium';
      $profile['height_change']        = 'moderate_increase';
      $profile['adult_size_description'] = 'Incredibly muscular — broad blocky head, powerful neck and chest, extreme muscle striations, smooth short coat.';
    } elseif ($this->mb($b, ['staffordshire bull terrier','staffy','staffie'])) {
      $profile['size_category'] = 'medium';
      $profile['height_change'] = 'moderate_increase';
    } elseif ($this->mb($b, ['bull terrier','english bull terrier'])) {
      $isMini = $this->mb($b, ['miniature','mini']);
      $profile['size_category'] = $isMini ? 'small' : 'medium';
      $profile['height_change'] = $isMini ? 'minimal_increase' : 'moderate_increase';
      $profile['adult_size_description'] = 'Unique egg-shaped head — completely flat on top, curved from crown to nose tip. Muscular powerful body.';
    } elseif ($this->mb($b, ['whippet','greyhound','italian greyhound','saluki','afghan hound','borzoi','azawakh','pharaoh hound','ibizan hound'])) {
      $isLongCoat = $this->mb($b, ['afghan hound','borzoi','saluki']);
      $profile['size_category'] = $this->mb($b, ['italian greyhound']) ? 'small' : 'medium';
      $profile['body_shape']    = 'sighthound';
      $profile['coat_type']     = $isLongCoat ? 'long_silky' : 'short';
      $profile['height_change'] = 'large_increase';
      $profile['adult_size_description'] = 'Ultimate athletic dog — aerodynamic silhouette with extreme deep chest tuck, long neck, narrow refined head, extraordinary lean physique.';
    } elseif ($this->mb($b, ['french bulldog','frenchie'])) {
      $profile['size_category']  = 'small';
      $profile['brachycephalic'] = true;
      $profile['height_change']  = 'none';
      $profile['adult_size_description'] = 'Compact muscular small dog with extremely flat face, large bat-like ears, stocky barrel body, screw tail.';
    } elseif ($this->mb($b, ['english bulldog','british bulldog','bulldog'])) {
      $profile['size_category']  = 'medium';
      $profile['brachycephalic'] = true;
      $profile['height_change']  = 'minimal_increase';
      $profile['adult_size_description'] = 'Massively built — enormous head with hanging flews and deep wrinkles, massive chest on short bowed legs.';
    } elseif ($this->mb($b, ['pug'])) {
      $profile['size_category']  = 'small';
      $profile['brachycephalic'] = true;
      $profile['height_change']  = 'none';
      $profile['adult_size_description'] = 'Small compact dog with extremely wrinkled flat face, large round eyes, cobby square body.';
    } elseif ($this->mb($b, ['boston terrier'])) {
      $profile['size_category']  = 'small';
      $profile['brachycephalic'] = true;
      $profile['height_change']  = 'none';
      $profile['adult_size_description'] = 'Compact tuxedo dog with bat ears, flat face, and athletic compact build.';
    } elseif ($this->mb($b, ['chinese shar pei','shar pei'])) {
      $profile['size_category']  = 'medium';
      $profile['brachycephalic'] = true;
      $profile['height_change']  = 'moderate_increase';
      $profile['adult_size_description'] = 'Square dog with extraordinarily loose wrinkled skin, small hippo-like head, blue-black tongue.';
    } elseif ($this->mb($b, ['shih tzu'])) {
      $profile['size_category']  = 'small';
      $profile['brachycephalic'] = true;
      $profile['coat_type']      = 'long_silky';
      $profile['height_change']  = 'none';
    } elseif ($this->mb($b, ['yorkshire terrier','yorkie'])) {
      $profile['size_category']        = 'toy';
      $profile['coat_type']            = 'long_silky';
      $profile['height_change']        = 'none';
      $profile['adult_size_description'] = 'Tiny dog with long, fine, silky STEEL-BLUE and TAN coat, perfectly erect small V-shaped ears.';
    } elseif ($this->mb($b, ['maltese'])) {
      $profile['size_category']        = 'toy';
      $profile['coat_type']            = 'long_silky';
      $profile['height_change']        = 'none';
      $profile['adult_size_description'] = 'Tiny all-white dog completely covered in flowing, silky, pure white coat that reaches the ground.';
    } elseif ($this->mb($b, ['chihuahua'])) {
      $isLongCoat = $this->mb($b, ['long coat','longhaired','long hair']);
      $profile['size_category']        = 'toy';
      $profile['coat_type']            = $isLongCoat ? 'long_silky' : 'short';
      $profile['height_change']        = 'none';
      $profile['adult_size_description'] = "World's smallest breed — apple-domed skull, large prominent eyes, large erect ears.";
    } elseif ($this->mb($b, ['pomeranian'])) {
      $profile['size_category']        = 'toy';
      $profile['coat_type']            = 'double_coat';
      $profile['height_change']        = 'none';
      $profile['adult_size_description'] = 'Tiny fluffy lion-like dog with enormous stand-off double coat, foxy face, tiny erect ears, plumed tail.';
    } elseif ($this->mb($b, ['cavalier king charles','cavalier'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = 'long_silky';
      $profile['height_change'] = 'minimal_increase';
    } elseif ($this->mb($b, ['bichon frise','bichon'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = 'curly';
      $profile['height_change'] = 'none';
    } elseif ($this->mb($b, ['havanese','coton de tulear','bolognese'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = 'long_silky';
      $profile['height_change'] = 'none';
    } elseif ($this->mb($b, ['lhasa apso'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = 'long_silky';
      $profile['height_change'] = 'none';
    } elseif ($this->mb($b, ['papillon'])) {
      $profile['size_category'] = 'toy';
      $profile['coat_type']     = 'long_silky';
      $profile['height_change'] = 'none';
    } elseif ($this->mb($b, ['corgi','pembroke','cardigan'])) {
      $profile['size_category']        = 'small';
      $profile['body_shape']           = 'long_low';
      $profile['coat_type']            = 'double_coat';
      $profile['height_change']        = 'minimal_increase';
      $profile['adult_size_description'] = 'Long low dog with short powerful legs, very long muscular torso, large upright bat-like ears, foxy face.';
    } elseif ($this->mb($b, ['dachshund','doxie','sausage dog','wiener','weiner'])) {
      $isLong = $this->mb($b, ['long','longhaired','long-haired']);
      $isWire = $this->mb($b, ['wire','wirehaired','wire-haired']);
      $isMini = $this->mb($b, ['mini','miniature']);
      $profile['size_category']        = $isMini ? 'toy' : 'small';
      $profile['body_shape']           = 'long_low';
      $profile['coat_type']            = $isLong ? 'long_silky' : ($isWire ? 'wire_harsh' : 'short');
      $profile['height_change']        = 'none';
      $profile['adult_size_description'] = 'The ultimate long-and-low sausage dog — dramatically elongated body on tiny short legs.';
    } elseif ($this->mb($b, ['basset hound','basset'])) {
      $profile['size_category']        = 'medium';
      $profile['body_shape']           = 'long_low';
      $profile['height_change']        = 'minimal_increase';
      $profile['adult_size_description'] = 'Extremely heavy, low-slung — enormously long velvety ears, deeply wrinkled skin, large soulful eyes, heavy bone.';
    } elseif ($this->mb($b, ['giant schnauzer','standard schnauzer','miniature schnauzer','schnauzer'])) {
      $isGiant = $this->mb($b, ['giant']);
      $isMini  = $this->mb($b, ['miniature','mini']);
      $profile['size_category'] = $isGiant ? 'large' : ($isMini ? 'small' : 'medium');
      $profile['coat_type']     = 'wire_harsh';
      $profile['height_change'] = $isGiant ? 'large_increase' : ($isMini ? 'none' : 'moderate_increase');
    } elseif ($this->mb($b, ['jack russell','parson russell','russell terrier'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = $this->mb($b, ['wire','rough']) ? 'wire_harsh' : 'short';
      $profile['height_change'] = 'minimal_increase';
    } elseif ($this->mb($b, ['west highland','westie','cairn terrier','scottish terrier','scottie','border terrier','norfolk terrier'])) {
      $profile['size_category'] = 'small';
      $profile['coat_type']     = 'wire_harsh';
      $profile['height_change'] = 'minimal_increase';
    } elseif ($this->mb($b, ['airedale terrier','airedale'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'wire_harsh';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Largest terrier — tall, athletic, black-and-tan wire coat, long flat rectangular head, distinctive beard.';
    } elseif ($this->mb($b, ['soft coated wheaten terrier','wheaten'])) {
      $profile['size_category'] = 'medium';
      $profile['coat_type']     = 'wavy_curly';
      $profile['height_change'] = 'moderate_increase';
    } elseif ($this->mb($b, ['beagle'])) {
      $profile['size_category']        = 'small';
      $profile['height_change']        = 'moderate_increase';
      $profile['adult_size_description'] = 'Compact sturdy scent hound — tricolor, square hound head, long pendulous soft ears, deep chest.';
    } elseif ($this->mb($b, ['bloodhound','coonhound','redbone','treeing walker','plott hound'])) {
      $profile['size_category'] = 'large';
      $profile['height_change'] = 'large_increase';
    } elseif ($this->mb($b, ['rhodesian ridgeback'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'short';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Powerful athletic dog with distinctive ridge of reversed hair along the spine, deep wheaten short coat.';
    } elseif ($this->mb($b, ['dalmatian'])) {
      $profile['size_category'] = 'large';
      $profile['coat_type']     = 'short';
      $profile['height_change'] = 'large_increase';
      $profile['adult_size_description'] = 'Lean athletic white dog with bold black/liver spots, deep chest, elegant build.';
    } elseif ($this->mb($b, ['border collie','australian shepherd','aussie'])) {
      $profile['size_category'] = 'medium';
      $profile['coat_type']     = 'double_coat';
      $profile['height_change'] = 'moderate_increase';
    } elseif ($this->mb($b, ['collie','rough collie','sheltie','shetland sheepdog'])) {
      $isSheltie = $this->mb($b, ['sheltie','shetland']);
      $profile['size_category']        = $isSheltie ? 'small' : 'large';
      $profile['coat_type']            = 'long_silky';
      $profile['height_change']        = $isSheltie ? 'moderate_increase' : 'large_increase';
      $profile['adult_size_description'] = 'Strikingly elegant with long flowing mane and frill, narrow aristocratic head, rich sable/tricolor/merle coat.';
    } elseif ($this->mb($b, ['australian cattle dog','blue heeler','red heeler'])) {
      $profile['size_category'] = 'medium';
      $profile['coat_type']     = 'short';
      $profile['height_change'] = 'moderate_increase';
    } elseif ($this->mb($b, ['bernese mountain dog','berner'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'long_silky';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Large, sturdy tricolor mountain dog — black body with rust/tan points and white blaze/chest/paws.';
    } elseif ($this->mb($b, ['vizsla','hungarian vizsla'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'short';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Lean muscular golden-rust hunting dog — long aristocratic head, amber eyes, floppy ears, tucked abdomen.';
    } elseif ($this->mb($b, ['weimaraner'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'short';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Sleek silver-grey ghost dog — long elegant neck, deep chest, tucked abdomen, pale grey eyes.';
    } elseif ($this->mb($b, ['german shorthaired pointer','german wirehaired pointer','english pointer','pointer'])) {
      $isWire = $this->mb($b, ['wirehaired','wire']);
      $profile['size_category'] = 'large';
      $profile['coat_type']     = $isWire ? 'wire_harsh' : 'short';
      $profile['height_change'] = 'large_increase';
    } elseif ($this->mb($b, ['bracco italiano','italian pointer'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'short';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'A large noble hunting dog — pendulous long ears, slightly loose jowl skin, strong athletic build, deep-chested with visible musculature.';
    } elseif ($this->mb($b, ['irish setter','english setter','gordon setter','setter'])) {
      $profile['size_category'] = 'large';
      $profile['coat_type']     = 'long_silky';
      $profile['height_change'] = 'large_increase';
    } elseif ($this->mb($b, ['cocker spaniel','english cocker','american cocker'])) {
      $profile['size_category']        = 'medium';
      $profile['coat_type']            = 'long_silky';
      $profile['height_change']        = 'moderate_increase';
      $profile['adult_size_description'] = 'Compact spaniel with long luxurious silky coat and feathering, long pendulous ears framing a domed head.';
    } elseif ($this->mb($b, ['old english sheepdog','oes','bobtail'])) {
      $profile['size_category']        = 'large';
      $profile['coat_type']            = 'wavy_curly';
      $profile['height_change']        = 'large_increase';
      $profile['adult_size_description'] = 'Large shaggy dog completely covered in thick profuse grey-and-white coat — even face and eyes covered.';
    } elseif ($this->mb($b, ['bouvier des flandres','bouvier','briard'])) {
      $profile['size_category'] = 'large';
      $profile['coat_type']     = 'wire_harsh';
      $profile['height_change'] = 'large_increase';
    } elseif ($this->mb($b, ['aspin','asong pinoy','philippine native','village dog','street dog','mixed breed','mutt','mixed'])) {
      $profile['size_category']        = 'medium';
      $profile['height_change']        = 'moderate_increase';
      $profile['adult_size_description'] = 'Lean athletic medium dog with smooth short coat, semi-erect or erect ears, sickle tail, and the lithe build of a primitive pariah dog.';
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
  //  AGE PROFILES — FIX 2: sanitize control characters before json_decode
  // ─────────────────────────────────────────────────────────────────────

  private function generateAgeProfiles(array $breedProfile): array
  {
    $breed    = $breedProfile['breed'];
    $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

    $prompt = "You are a veterinary expert. For a {$breed} dog, provide accurate physical characteristics at 1 year old and 3 years old.

Return ONLY a valid JSON object — no markdown, no explanation, no extra text. Just the raw JSON:
{
  \"1_year\": {
    \"weight\": {\"male\": \"e.g. 18-22 lbs (8-10 kg)\", \"female\": \"e.g. 15-19 lbs (7-9 kg)\"},
    \"height\": {\"male\": \"e.g. 12-14 inches (30-36 cm)\", \"female\": \"e.g. 11-13 inches (28-33 cm)\"},
    \"visual_features\": [
      {\"label\": \"Coat Type\",  \"value\": \"Describe coat texture at 1 year\"},
      {\"label\": \"Coat Color\", \"value\": \"Describe how color looks at 1 year\"},
      {\"label\": \"Body Build\", \"value\": \"Describe physique at 1 year\"},
      {\"label\": \"Ear Shape\",  \"value\": \"Describe ears at 1 year\"},
      {\"label\": \"Tail\",       \"value\": \"Describe tail at 1 year\"}
    ]
  },
  \"3_years\": {
    \"weight\": {\"male\": \"e.g. 20-26 lbs (9-12 kg)\", \"female\": \"e.g. 17-22 lbs (8-10 kg)\"},
    \"height\": {\"male\": \"e.g. 13-15 inches (33-38 cm)\", \"female\": \"e.g. 12-14 inches (30-36 cm)\"},
    \"visual_features\": [
      {\"label\": \"Coat Type\",  \"value\": \"Describe coat texture at 3 years\"},
      {\"label\": \"Coat Color\", \"value\": \"Describe color at 3 years\"},
      {\"label\": \"Body Build\", \"value\": \"Describe physique at 3 years\"},
      {\"label\": \"Ear Shape\",  \"value\": \"Describe ears at 3 years\"},
      {\"label\": \"Tail\",       \"value\": \"Describe tail at 3 years\"}
    ]
  }
}";

    $payload = [
      'contents'         => [['parts' => [['text' => $prompt]]]],
      'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 900],
    ];

    $client   = new Client(['timeout' => 30]);
    $response = $client->post($endpoint, [
      'json'    => $payload,
      'headers' => ['Content-Type' => 'application/json'],
    ]);

    $data = json_decode($response->getBody()->getContents(), true);
    $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

    // Strip markdown fences
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/^```\s*/i',     '', $text);
    $text = preg_replace('/\s*```$/i',     '', $text);
    $text = trim($text);

    // ── FIX 2: Remove control characters that break json_decode ──────
    // Gemini occasionally returns unescaped newlines/tabs inside strings
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    $parsed = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      // Last resort: try to clean further and retry
      $text   = preg_replace('/\r\n|\r|\n/', ' ', $text);
      $parsed = json_decode($text, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON from Gemini age profiles: ' . json_last_error_msg());
      }
    }

    return $parsed;
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