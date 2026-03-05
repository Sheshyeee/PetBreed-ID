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
        'gemini-2.0-flash-exp-image-generation',
        'gemini-2.5-flash-preview-05-20',
        'gemini-2.5-flash-image',
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

            $breedProfile                       = $this->getBreedProfile($this->breed);
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
    //  AGE STAGE DETECTION — improved precision
    // ─────────────────────────────────────────────────────────────────────

    private function detectAgeStage(array $imageData): string
    {
        try {
            $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

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
                'temperature'        => 0.35,
                'topK'               => 40,
                'topP'               => 0.90,
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
    //  ★★★ COMPLETELY REDESIGNED PROMPT — MAXIMUM VISUAL IMPACT ★★★
    //
    //  Philosophy: Instead of listing what to CHANGE, we describe the
    //  COMPLETE FINAL STATE of the output image. The model should produce
    //  the target image, not "apply edits" to the source.
    //
    //  Structure:
    //    1. TASK FRAMING    — what kind of image to produce
    //    2. IDENTITY ANCHOR — what must stay the same (color, markings, pose)
    //    3. COMPLETE OUTPUT DESCRIPTION — full head-to-tail description of
    //       what the OUTPUT dog must look like (breed-accurate for target age)
    //    4. FORBIDDEN CHANGES — explicit anti-subtle-change instructions
    //    5. QUALITY GATE
    // ─────────────────────────────────────────────────────────────────────

    private function buildAgingPrompt(array $profile, int $targetYears): string
    {
        $breed     = $profile['breed'];
        $ageStage  = $profile['detected_age_stage'] ?? 'adult';
        $isPuppy   = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager']);
        $isYoung   = ($ageStage === 'young_adult');
        $isSenior  = ($ageStage === 'senior');
        $isAdult   = !$isPuppy && !$isYoung && !$isSenior;

        // ── pull profile fields ───────────────────────────────────────────
        $size      = $profile['size_category']     ?? 'medium';
        $coat      = $profile['coat_type']          ?? 'short';
        $isBrachy  = $profile['brachycephalic']     ?? false;
        $bodyShape = $profile['body_shape']         ?? 'standard';
        $heightChg = $profile['height_change']      ?? 'moderate_increase';

        // What does the fully-aged output dog look like?
        $outputDescription = $this->buildOutputDescription($profile, $ageStage, $targetYears);

        // Magnitude label
        if ($isPuppy && $targetYears === 3)      $magnitude = 'EXTREME — puppy becomes full adult';
        elseif ($isPuppy && $targetYears === 1)  $magnitude = 'VERY HIGH — puppy becomes adolescent/young adult';
        elseif ($isYoung && $targetYears === 3)  $magnitude = 'HIGH — young adult reaches prime maturity';
        elseif ($isYoung && $targetYears === 1)  $magnitude = 'MODERATE-HIGH — young adult matures noticeably';
        elseif ($isSenior)                       $magnitude = 'MODERATE — senior shows more visible aging';
        elseif ($targetYears === 3)              $magnitude = 'HIGH — adult shows clear 3-year aging signs';
        else                                     $magnitude = 'MODERATE — adult shows clear 1-year aging signs';

        $L = [];

        // ══ TASK FRAMING ════════════════════════════════════════════════
        $L[] = '┌─────────────────────────────────────────────────────────────────────────────┐';
        $L[] = '│  TASK: AGE PROGRESSION PHOTO — CREATE THE FUTURE VERSION OF THIS DOG        │';
        $L[] = '└─────────────────────────────────────────────────────────────────────────────┘';
        $L[] = '';
        $L[] = "BREED      : {$breed}";
        $L[] = "DOG NOW    : {$ageStage}";
        $L[] = "TARGET     : This same dog, +{$targetYears} year(s) older";
        $L[] = "CHANGE MAG : {$magnitude}";
        $L[] = '';
        $L[] = 'You are a professional visual artist specializing in photorealistic dog age progression.';
        $L[] = 'Produce a PHOTOREALISTIC IMAGE of this dog aged forward by exactly ' . $targetYears . ' year(s).';
        $L[] = 'The transformation must be OBVIOUS and UNMISTAKABLE to any viewer.';
        $L[] = '';

        // ══ IDENTITY ANCHORS — DO NOT CHANGE THESE ══════════════════════
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = 'SECTION 1 — IDENTITY ANCHORS (copy these PIXEL-PERFECT from the source):';
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = '  ✦ Background, floor, environment — IDENTICAL';
        $L[] = '  ✦ Base coat color and unique markings pattern — IDENTICAL';
        $L[] = '  ✦ Eye color — IDENTICAL';
        $L[] = '  ✦ Overall pose and body orientation — IDENTICAL';
        $L[] = '  ✦ Camera angle and framing — IDENTICAL';
        $L[] = '';

        // ══ COMPLETE OUTPUT DESCRIPTION ══════════════════════════════════
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = "SECTION 2 — COMPLETE DESCRIPTION OF THE OUTPUT DOG (every detail matters):";
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = '';
        $L[] = "The output dog MUST match this description EXACTLY:";
        $L[] = '';
        foreach ($outputDescription as $line) {
            $L[] = "  {$line}";
        }
        $L[] = '';

        // ══ TRANSFORMATION EMPHASIS ══════════════════════════════════════
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = 'SECTION 3 — CRITICAL TRANSFORMATION POINTS (most common failure areas):';
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = '';

        if ($isPuppy) {
            $L[] = "  ⚡ PUPPY → OLDER: The MOST important thing — the output must NOT look like a puppy anymore.";
            $L[] = "     DO NOT keep the round baby face.";
            $L[] = "     DO NOT keep the oversized head-to-body ratio.";
            $L[] = "     DO NOT keep the soft fluffy puppy coat.";
            $L[] = "     DO NOT keep the stumpy short legs (unless long-and-low breed like Dachshund/Corgi).";
            $L[] = "     DO NOT keep the potbelly/barrel abdomen.";
            $L[] = '';

            if ($targetYears === 3) {
                $L[] = "  ⚡ PUPPY → FULL ADULT: Three years of growth means this dog has COMPLETELY CHANGED.";
                $L[] = "     A viewer seeing only the OUTPUT must immediately know it is a FULLY GROWN adult dog.";
                $L[] = "     If the output still looks like a puppy in any way — it is a COMPLETE FAILURE.";
            } else {
                $L[] = "  ⚡ PUPPY → ADOLESCENT/YOUNG: One year of growth — still maturing, but clearly no longer a puppy.";
                $L[] = "     Body is noticeably longer and taller. Baby proportions are GONE.";
                $L[] = "     The viewer must see a clearly OLDER, BIGGER dog.";
            }
        } elseif ($isYoung) {
            $L[] = "  ⚡ YOUNG ADULT → MATURE: The dog looks MORE SETTLED, HEAVIER, and MORE DEFINED.";
            $L[] = "     Visible muscle development — especially chest, neck, and hindquarters.";
            if ($targetYears === 3) {
                $L[] = "     Clear graying on muzzle tip and chin. Face looks more experienced.";
                $L[] = "     Body at PEAK PRIME ADULT condition — deep chest, defined musculature.";
            }
        } elseif ($isSenior) {
            $L[] = "  ⚡ SENIOR → OLDER SENIOR: Aging signs must be MORE PRONOUNCED than in the input.";
            $L[] = "     More white/gray on muzzle. Cloudier eyes. Saggier jowls. Thinner coat.";
        } else {
            // adult
            if ($targetYears === 1) {
                $L[] = "  ⚡ ADULT → +1 YEAR: Subtle but VISIBLE aging. A viewer must notice the difference.";
                $L[] = "     Add 5–15 silver/white hairs at the muzzle tip and chin — clearly visible.";
                $L[] = "     Face looks marginally more mature and settled. Body slightly heavier/denser.";
                $L[] = "     DO NOT produce an image that looks identical to the input. That is failure.";
            } else {
                $L[] = "  ⚡ ADULT → +3 YEARS: Clear and obvious aging — this must be immediately noticeable.";
                $L[] = "     WHITE/GRAY muzzle: entire muzzle tip, chin, and around eyes must show clear graying.";
                $L[] = "     BODY: noticeably heavier neck and chest. Less lean definition. More settled weight.";
                $L[] = "     FACE: more defined jowls, slightly deeper facial lines. A more experienced look.";
                $L[] = "     DO NOT produce an image that looks only slightly different. That is failure.";
            }
        }

        // ══ BREED ANATOMY GUARDRAILS ══════════════════════════════════════
        $L[] = '';
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = 'SECTION 4 — BREED ANATOMY RULES (never violate these):';
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = '';

        if ($bodyShape === 'long_low') {
            $L[] = "  🔒 LONG-AND-LOW BREED: Legs NEVER grow tall. Body grows LONGER and HEAVIER only.";
        }
        if ($isBrachy) {
            $L[] = "  🔒 FLAT-FACED BREED: The pushed-in face NEVER elongates. Keep flat face — add wrinkles for age.";
        }
        if ($bodyShape === 'sighthound') {
            $L[] = "  🔒 SIGHTHOUND: Always lean/aerodynamic. Deep chest tuck always present. Never becomes fat.";
        }
        if ($size === 'giant') {
            $L[] = "  🔒 GIANT BREED: Full adult is ENORMOUS. Puppy-to-adult change is one of the largest in nature.";
        }
        if ($size === 'toy' || $size === 'small') {
            $L[] = "  🔒 SMALL BREED: Height changes minimally. Aging shown primarily through face/coat changes.";
        }
        if ($bodyShape === 'spitz') {
            $L[] = "  🔒 SPITZ: Erect pointed ears always present. Curled tail over back always present.";
        }
        if ($coat === 'long_silky' || $coat === 'double_coat') {
            $L[] = "  🔒 COATED BREED: Adult coat must be dramatically fuller/longer/denser than puppy coat.";
        }

        // ══ QUALITY GATE ════════════════════════════════════════════════
        $L[] = '';
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = 'SECTION 5 — FINAL QUALITY CHECK before rendering:';
        $L[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $L[] = '';
        $L[] = "  ✅ Does the output dog look UNDENIABLY {$targetYears} year(s) older? (MUST be YES)";
        $L[] = "  ✅ Does the output match the COMPLETE DESCRIPTION in Section 2? (MUST be YES)";
        $L[] = "  ✅ Are all Section 1 identity anchors preserved? (MUST be YES)";
        $L[] = "  ✅ Does the dog still look like the SAME INDIVIDUAL, just older? (MUST be YES)";
        $L[] = '';
        $L[] = "  ❌ Output looks almost identical to input → REJECTED. Re-render with MORE change.";
        $L[] = "  ❌ Only brightness/contrast changed, no structural change → REJECTED.";
        $L[] = "  ❌ Background changed → REJECTED.";
        $L[] = '';
        $L[] = '══════════════════════════════════════════════════════════════════════════════';
        $L[] = '  RENDER THE AGED DOG NOW. Output the photorealistic image only.';
        $L[] = '══════════════════════════════════════════════════════════════════════════════';

        return implode("\n", $L);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  BUILD OUTPUT DESCRIPTION
    //  Returns an array of lines describing the COMPLETE appearance of the
    //  output dog. This is the core of the improved prompt strategy.
    // ─────────────────────────────────────────────────────────────────────

    private function buildOutputDescription(array $profile, string $ageStage, int $targetYears): array
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

        // ── HEAD / FACE ───────────────────────────────────────────────────
        $lines[] = '[ HEAD & FACE ]';

        if ($isPuppy && $targetYears >= 1) {
            $lines[] = '• Skull shape: FULLY ADULT proportions — NOT baby-round. Same size as body, not oversized.';
            if ($isBrachy) {
                $lines[] = '• Flat brachycephalic face: unchanged push-in — but now with MORE prominent skin folds/wrinkles.';
            } else {
                $lines[] = '• Muzzle: LONGER than in puppy photo — adult length, well-defined, not short/stubby.';
            }
            $lines[] = '• Skull muscles and brow ridges visible — no soft baby roundness.';
            if ($targetYears === 3) {
                if ($size !== 'toy' && $size !== 'small') {
                    $lines[] = '• MUZZLE TIP & CHIN: show ' . ($ageStage === 'teenager' ? '2–5' : '5–15') . ' silver/white hairs — early aging marker.';
                }
                $lines[] = '• Facial expression: EXPERIENCED and CONFIDENT adult look, not playful baby.';
            }
        } elseif ($isYoung) {
            $lines[] = '• Face: sharper, more defined than before. All remaining youthful softness gone.';
            $lines[] = '• Jaw muscles more defined. Brow slightly more prominent.';
            if ($targetYears === 3) {
                $lines[] = '• Muzzle tip: CLEAR silver/white hairs — 10–25 visible. Chin: sparse gray hairs.';
                $lines[] = '• Eyes: same color, but surrounded by a more experienced, settled expression.';
            } else {
                $lines[] = '• Muzzle: 3–8 silver hairs just at the very tip. Barely but noticeably aging.';
            }
        } elseif ($isSenior) {
            if ($targetYears === 1) {
                $lines[] = '• Muzzle: MORE white/gray than in source photo — expanded coverage.';
                $lines[] = '• Eyes: slightly cloudier and deeper-set than in source.';
                $lines[] = '• Jowls: slightly more sagging than source.';
            } else {
                $lines[] = '• Muzzle: COMPLETELY GRAY/WHITE — entire muzzle tip, chin, and around eyes silver.';
                $lines[] = '• Eyes: visibly cloudy with age-related opacity. Very deep-set.';
                $lines[] = '• Jowls and neck: significant skin sag. Deep facial creases.';
            }
        } else {
            // adult
            if ($targetYears === 1) {
                $lines[] = '• Face: marginally more settled and experienced looking.';
                $lines[] = '• Muzzle tip: 5–15 CLEARLY VISIBLE silver/white hairs. Chin: 3–8 gray hairs.';
                $lines[] = '• Jowls: very slightly more developed than source.';
            } else {
                $lines[] = '• MUZZLE: distinctly grayed — 30–50% of muzzle surface covered in white/silver.';
                $lines[] = '• CHIN: clearly gray/white. Around eyes: sparse silver hairs.';
                $lines[] = '• Jowls: noticeably more developed. Slight skin sag under chin area.';
                $lines[] = '• Overall face: a more mature, experienced, settled expression.';
            }
        }

        $lines[] = '';

        // ── EARS ──────────────────────────────────────────────────────────
        $lines[] = '[ EARS ]';
        if ($isPuppy) {
            $lines[] = '• Ears: in their FINAL ADULT POSITION for this breed (no longer flopped unless breed always flops).';
            if ($bodyShape === 'spitz') {
                $lines[] = '• Ears: PERFECTLY ERECT and rigid pointed spitz ears.';
            } elseif ($this->mb(strtolower($breed), ['french bulldog', 'boston terrier', 'chihuahua', 'corgi', 'pembroke', 'cardigan'])) {
                $lines[] = '• Ears: FULLY ERECT, rigid, and upright — no longer drooping or semi-erect.';
            }
        } else {
            $lines[] = '• Ears: same as source — fully adult and settled.';
        }

        $lines[] = '';

        // ── BODY ──────────────────────────────────────────────────────────
        $lines[] = '[ BODY & BUILD ]';

        if ($isPuppy) {
            switch ($heightChg) {
                case 'dramatic_increase':
                    $lines[] = '• Body size: ENORMOUSLY larger than puppy — this is a giant breed. ' . ($targetYears === 3 ? '3–4×' : '2–3×') . ' the puppy size.';
                    $lines[] = '• Legs: massively longer and more powerful. Towering adult presence.';
                    break;
                case 'large_increase':
                    $lines[] = '• Body size: SIGNIFICANTLY larger. ' . ($targetYears === 3 ? '2–3×' : '1.5–2×') . ' taller and heavier than the puppy.';
                    $lines[] = '• Legs: considerably longer and muscular — no trace of stumpy puppy legs.';
                    break;
                case 'moderate_increase':
                    $lines[] = '• Body size: noticeably larger. Legs ' . ($targetYears === 3 ? '50–80%' : '30–50%') . ' longer than puppy. Clearly bigger.';
                    break;
                case 'minimal_increase':
                    $lines[] = '• Body size: minimal height increase. But body is now HEAVIER, DENSER, and more muscular.';
                    $lines[] = '• Low-and-wide adult form. Chest drops lower.';
                    break;
                case 'none':
                    $lines[] = '• Body size: similar height. But proportions completely different — all baby proportions GONE.';
                    break;
            }

            $lines[] = '• Abdomen: FLAT adult tuck — NO potbelly or barrel shape.';
            $lines[] = '• Chest: deep and developed.';
            $lines[] = '• Paws: proportionate to legs — no longer oversized.';
            $lines[] = '• Musculature: ' . ($targetYears === 3 ? 'FULL adult muscle mass — defined shoulders, chest, and haunches.' : 'Developing adult muscles — lean adolescent build.');

        } elseif ($isYoung) {
            if ($targetYears === 3) {
                $lines[] = '• Body: PRIME ADULT condition — peak muscle development.';
                $lines[] = '• Chest: deeper and broader than in source photo.';
                $lines[] = '• Neck: thicker and more muscular than in source.';
                $lines[] = '• Hindquarters: more defined and powerful.';
            } else {
                $lines[] = '• Body: noticeably more muscular than source. Chest and shoulders slightly broader.';
                $lines[] = '• Neck: marginally thicker.';
            }
        } elseif ($isSenior) {
            $lines[] = '• Body: slightly less muscle mass than prime adult — softer definition.';
            if ($targetYears === 3) {
                $lines[] = '• Coat appears thinner in places. Less vibrant than in source.';
            }
        } else {
            // adult
            if ($targetYears === 1) {
                $lines[] = '• Body: marginally heavier/denser than source. Chest slightly thicker.';
                $lines[] = '• Neck: very slightly thicker at base.';
            } else {
                $lines[] = '• Body: NOTICEABLY heavier than source — thicker neck, broader chest, more mass overall.';
                $lines[] = '• Less lean definition than prime youth. Body looks heavier and more settled.';
                $lines[] = '• Chest: clearly broader. Neck: clearly thicker.';
            }
        }

        $lines[] = '';

        // ── COAT ──────────────────────────────────────────────────────────
        $lines[] = '[ COAT ]';

        switch ($coat) {
            case 'long_silky':
                $b = strtolower($breed);
                if ($isPuppy) {
                    if ($this->mb($b, ['yorkshire', 'yorkie'])) {
                        $lines[] = '• COAT TRANSFORMATION (critical): puppy black-and-tan fluffy coat → LONG SILKY STRAIGHT coat.';
                        $lines[] = '• Body: STEEL-BLUE / silver color (NOT black). Head/legs: rich GOLDEN-TAN.';
                        $lines[] = '• Length: ' . ($targetYears === 3 ? 'reaching toward the floor' : 'mid-body, visibly long') . '.';
                        $lines[] = '• Texture: SILKY and STRAIGHT — not fluffy, not wavy.';
                    } elseif ($this->mb($b, ['maltese'])) {
                        $lines[] = '• Coat: pure white LONG SILKY coat — ' . ($targetYears === 3 ? 'floor-length flowing white silk' : 'noticeably longer, flowing white coat growing') . '.';
                    } elseif ($this->mb($b, ['golden retriever'])) {
                        $lines[] = '• Coat: developing/full golden waves with feathering on chest, legs, belly, tail.';
                        $lines[] = '• Rich golden color — denser and more voluminous than puppy.';
                    } elseif ($this->mb($b, ['collie', 'sheltie'])) {
                        $lines[] = '• Coat: growing/full FLOWING MANE at neck and chest. Long coat on flanks.';
                    } else {
                        $lines[] = '• Coat: longer and silkier than puppy coat. ' . ($targetYears === 3 ? 'Full adult feathering everywhere.' : 'Developing adult feathering on ears/legs.');
                    }
                } elseif ($targetYears === 3 && ($isAdult || $isSenior)) {
                    $lines[] = '• Coat: same as source but ' . ($isSenior ? 'slightly thinner/less lustrous.' : 'fully mature, perhaps marginally denser.');
                }
                break;

            case 'double_coat':
                if ($isPuppy) {
                    $lines[] = '• Coat: puppy fuzz REPLACED by DENSE DOUBLE COAT — thick undercoat visible, coarse guard hairs.';
                    $lines[] = '• Volume: much more voluminous and stand-off than puppy coat.';
                    if ($this->mb(strtolower($breed), ['pomeranian'])) {
                        $lines[] = '• Pomeranian: ENORMOUS stand-off coat — huge ruff/mane, plumed tail. Ball of fluff.';
                    }
                }
                break;

            case 'wire':
            case 'wire_harsh':
                if ($isPuppy) {
                    $lines[] = '• Coat: soft puppy coat REPLACED by HARSH BRISTLY wire coat.';
                    $lines[] = '• Beard and eyebrows: prominent — this is the breed signature.';
                }
                break;

            case 'curly':
            case 'wavy_curly':
                if ($isPuppy) {
                    $lines[] = '• Coat: puppy fluff REPLACED by ' . ($targetYears === 3 ? 'FULL TIGHT CURLS/WAVES' : 'developing curls/waves') . ' — much denser and more voluminous.';
                }
                break;

            default: // short
                if ($isPuppy) {
                    $lines[] = '• Coat: puppy fuzz REPLACED by sleek, tight, dense adult short coat.';
                } elseif (!$isSenior && $targetYears === 3) {
                    $lines[] = '• Coat: same as source but marginally denser and slightly less glossy with age.';
                } elseif ($isSenior) {
                    $lines[] = '• Coat: ' . ($targetYears === 3 ? 'noticeably thinner and duller than source' : 'slightly less vibrant than source') . '.';
                }
                break;
        }

        $lines[] = '';

        // ── BREED-SPECIFIC ADDITIONS ──────────────────────────────────────
        $extra = $this->getBreedSpecificOutputLines($profile, $ageStage, $targetYears);
        if (!empty($extra)) {
            $lines[] = '[ BREED-SPECIFIC ]';
            foreach ($extra as $el) {
                $lines[] = $el;
            }
            $lines[] = '';
        }

        // ── OVERALL IMPRESSION ────────────────────────────────────────────
        $lines[] = '[ OVERALL IMPRESSION ]';
        $adultDesc = $profile['adult_size_description'] ?? '';
        if ($adultDesc && ($isPuppy && $targetYears === 3)) {
            $lines[] = "• The output dog must match this description: \"{$adultDesc}\"";
        } elseif ($isPuppy) {
            $lines[] = "• A viewer sees: clearly older and bigger than the puppy — an adolescent or young adult {$breed}.";
        } elseif ($isSenior) {
            $lines[] = "• A viewer sees: a clearly OLDER senior dog — more gray, more sagging, more aged than the source.";
        } elseif ($targetYears === 3) {
            $lines[] = "• A viewer sees: the SAME dog, but clearly 3 years older. The aging must be OBVIOUS.";
        } else {
            $lines[] = "• A viewer sees: the same dog, with subtle-but-VISIBLE aging. Not identical to source.";
        }

        return $lines;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  BREED-SPECIFIC OUTPUT LINES
    // ─────────────────────────────────────────────────────────────────────

    private function getBreedSpecificOutputLines(array $profile, string $ageStage, int $targetYears): array
    {
        $b        = strtolower($profile['breed']);
        $isPuppy  = in_array($ageStage, ['newborn_puppy', 'puppy', 'teenager']);
        $lines    = [];

        if ($this->mb($b, ['rottweiler', 'rottie']) && $isPuppy) {
            $lines[] = '• HEAD: massive blocky SQUARE head — not round. Broad flat skull.';
            $lines[] = '• TAN POINTS: rich mahogany/rust points on eyebrows, cheeks, chest, legs — clearly defined on black coat.';
            $lines[] = '• NECK: thick and powerful. CHEST: barrel-wide.';
        }
        if ($this->mb($b, ['french bulldog', 'frenchie'])) {
            if ($isPuppy) {
                $lines[] = '• BAT EARS: PERFECTLY ERECT and rigid — both standing straight up.';
                $lines[] = '• HEAD: broad, square, flat-faced. Forehead wrinkles develop.';
                $lines[] = '• BODY: compact muscular barrel — thick neck, wide chest.';
            }
            if ($targetYears === 3) {
                $lines[] = '• WRINKLES: deeper forehead wrinkles. More prominent nose rope.';
            }
        }
        if ($this->mb($b, ['german shepherd', 'gsd', 'alsatian']) && $isPuppy) {
            $lines[] = '• EARS: FULLY ERECT rigid pointed ears — no longer flopped.';
            $lines[] = '• COAT: saddle/blanket pattern clearly defined — black over tan/sable.';
            $lines[] = '• BODY: lean powerful athletic build. Wolf-like profile.';
        }
        if ($this->mb($b, ['golden retriever']) && $isPuppy) {
            $lines[] = '• COLOR: rich golden — more saturated than puppy.';
            if ($targetYears === 3) {
                $lines[] = '• COAT: full adult waves with prominent feathering — chest, belly, legs, tail.';
            }
        }
        if ($this->mb($b, ['labrador', 'lab']) && $isPuppy) {
            $lines[] = '• TAIL: thick otter-tail (thick at base, tapers) — prominent and distinctive.';
            $lines[] = '• HEAD: broad blocky lab head with soft eyes.';
            $lines[] = '• BUILD: stocky and powerful.';
        }
        if ($this->mb($b, ['dachshund', 'doxie', 'wiener', 'weiner', 'sausage'])) {
            $lines[] = '• BODY SHAPE: dramatically elongated sausage shape — NOT tall. Tiny legs, very long torso.';
            $lines[] = '• The body gets LONGER with age, not taller.';
        }
        if ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
            $lines[] = '• EARS: FULLY ERECT large pointed bat-like ears — both standing straight up.';
            $lines[] = '• BODY: long torso on SHORT legs — legs stay short, body is long.';
        }
        if ($this->mb($b, ['poodle'])) {
            if ($isPuppy) {
                $lines[] = '• COAT: TIGHT DENSE UNIFORM CURLS covering entire body — no puppy fluff remaining.';
                $lines[] = '• The coat is the single biggest transformation — from wispy to voluminous curls.';
            }
        }
        if ($this->mb($b, ['husky', 'malamute', 'samoyed']) && $isPuppy) {
            $lines[] = '• COAT: thick plush double coat — much more voluminous than puppy.';
            $lines[] = '• RUFF: thick mane visible around neck.';
            $lines[] = '• MASK: facial markings more intensely defined.';
        }
        if ($this->mb($b, ['beagle']) && $isPuppy) {
            $lines[] = '• TRICOLOR: black saddle deeper, tan points sharper, white brighter — all more defined.';
            $lines[] = '• EARS: longer and more pendulous than puppy.';
        }
        if ($this->mb($b, ['boxer'])) {
            if ($isPuppy) {
                $lines[] = '• FLAT FACE: unchanged — do NOT elongate muzzle.';
                $lines[] = '• CHEST: massively broad barrel chest.';
                $lines[] = '• WRINKLES: deep forehead wrinkles on broad flat head.';
            }
        }
        if ($this->mb($b, ['pug'])) {
            if ($isPuppy) {
                $lines[] = '• WRINKLES: deep forehead folds multiply — this is the adult pug face.';
                $lines[] = '• EYES: large round prominent eyes.';
                $lines[] = '• BODY: cobby compact square shape.';
                $lines[] = '• TAIL: tight double-curl.';
            }
        }
        if ($this->mb($b, ['chihuahua'])) {
            if ($isPuppy) {
                $lines[] = '• SKULL: classic APPLE-DOME — round, prominent.';
                $lines[] = '• EARS: LARGE ERECT pointed ears — both fully upright.';
                $lines[] = '• EYES: proportionally very large and round.';
            }
        }
        if ($this->mb($b, ['shiba inu', 'shiba'])) {
            if ($isPuppy) {
                $lines[] = '• EARS: fully erect, rigid, pointed.';
                $lines[] = '• TAIL: tightly curled over back.';
                $lines[] = '• COAT: dense plush double coat — much denser than puppy.';
                $lines[] = '• COLOR: rich red/black-and-tan/sesame — intensified adult coloring.';
            }
        }
        if ($this->mb($b, ['aspin', 'asong pinoy', 'philippine', 'village dog', 'street dog', 'mixed breed', 'mutt', 'mixed'])) {
            if ($isPuppy) {
                $lines[] = '• BODY: lean athletic native dog — visible tuck-up at abdomen.';
                $lines[] = '• EARS: semi-erect or erect — settled in adult position.';
                $lines[] = '• COAT: tight short sleek adult coat. Lean primitive pariah-dog silhouette.';
            }
            if ($targetYears >= 1 && !$isPuppy) {
                $lines[] = '• Lean athletic medium build — coat colors/markings same as source.';
                if ($targetYears === 3) {
                    $lines[] = '• Slight graying at muzzle tip. More settled, experienced expression.';
                }
            }
        }

        return $lines;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  IMAGE PREPARATION — UPSCALE SMALL IMAGES
    // ─────────────────────────────────────────────────────────────────────

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
    //  COMPREHENSIVE BREED PROFILES
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

        // ── GIANT BREEDS ──────────────────────────────────────────────────
        if ($this->mb($b, ['great dane', 'irish wolfhound', 'saint bernard', 'newfoundland', 'leonberger',
                           'mastiff', 'great pyrenees', 'anatolian', 'kangal', 'caucasian',
                           'tibetan mastiff', 'boerboel', 'cane corso', 'dogue de bordeaux',
                           'french mastiff', 'neapolitan mastiff', 'broholmer', 'moscow watchdog'])) {
            $profile['size_category']        = 'giant';
            $profile['height_change']        = 'dramatic_increase';
            $profile['adult_size_description']= 'One of the largest dog breeds — a towering, massively built adult standing 28–35 inches tall with enormous bone structure, broad skull, and imposing physical presence.';
            $profile['brachycephalic']       = $this->mb($b, ['mastiff', 'saint bernard', 'leonberger', 'cane corso', 'dogue', 'neapolitan', 'broholmer']);
        }

        // ── WORKING/SHEPHERD LARGE ────────────────────────────────────────
        elseif ($this->mb($b, ['german shepherd', 'gsd', 'alsatian', 'belgian malinois',
                               'dutch shepherd', 'belgian tervuren', 'belgian laekenois', 'belgian shepherd'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'double_coat';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'A powerful, athletic dog standing 22–26 inches — wolf-like, lean-muscled, with dense double coat, perfectly erect ears, and long confident stride.';
        }

        // ── NORDIC/SPITZ ──────────────────────────────────────────────────
        elseif ($this->mb($b, ['siberian husky', 'husky', 'alaskan malamute', 'malamute',
                               'samoyed', 'akita', 'shiba inu', 'shiba', 'chow chow',
                               'keeshond', 'spitz', 'american akita', 'japanese akita'])) {
            $isLarge = $this->mb($b, ['malamute', 'akita', 'american akita', 'chow chow']);
            $profile['size_category'] = $isLarge ? 'large' : 'medium';
            $profile['coat_type']     = 'double_coat';
            $profile['body_shape']    = 'spitz';
            $profile['height_change'] = $isLarge ? 'large_increase' : 'moderate_increase';
            $profile['adult_size_description'] = 'A Nordic-type dog with thick plush double coat, erect pointed ears, curled tail over back, and compact powerful build.';
        }

        // ── RETRIEVERS ────────────────────────────────────────────────────
        elseif ($this->mb($b, ['golden retriever'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'long_silky';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'A large well-proportioned dog with thick golden flowing coat, broad head, soft intelligent eyes, deep chest, and feathering on legs, chest, and tail.';
        }
        elseif ($this->mb($b, ['labrador retriever', 'labrador', 'lab'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'short';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'A large athletic dog with broad otter-like tail, dense short coat, broad head, deep chest, and powerful stocky build.';
        }
        elseif ($this->mb($b, ['flat-coated retriever', 'flat coated', 'chesapeake bay'])) {
            $profile['size_category']  = 'large';
            $profile['coat_type']      = 'long_silky';
            $profile['height_change']  = 'large_increase';
        }

        // ── POODLES ───────────────────────────────────────────────────────
        elseif ($this->mb($b, ['standard poodle', 'miniature poodle', 'toy poodle', 'poodle'])) {
            $isStandard = $this->mb($b, ['standard']);
            $isMini     = $this->mb($b, ['miniature', 'mini']);
            $isToy      = $this->mb($b, ['toy']);
            $profile['size_category'] = $isStandard ? 'large' : ($isMini ? 'small' : ($isToy ? 'toy' : 'medium'));
            $profile['coat_type']     = 'curly';
            $profile['height_change'] = $isStandard ? 'large_increase' : ($isMini ? 'moderate_increase' : 'none');
            $profile['adult_size_description'] = $isStandard
                ? 'A tall elegant dog 21–27 inches — athletic with a long refined head and tight curly coat.'
                : 'A compact poodle with dense curly coat and refined build.';
        }

        // ── DOODLES ───────────────────────────────────────────────────────
        elseif ($this->mb($b, ['goldendoodle', 'labradoodle', 'bernedoodle', 'aussiedoodle',
                               'sheepadoodle', 'newfypoo', 'pyredoodle'])) {
            $isLarge = $this->mb($b, ['standard', 'bernedoodle', 'sheepadoodle', 'newfypoo', 'pyredoodle']);
            $profile['size_category'] = $isLarge ? 'large' : 'medium';
            $profile['coat_type']     = 'wavy_curly';
            $profile['height_change'] = $isLarge ? 'large_increase' : 'moderate_increase';
        }
        elseif ($this->mb($b, ['cockapoo', 'cavapoo', 'maltipoo', 'schnoodle', 'yorkipoo'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = 'wavy_curly';
            $profile['height_change'] = 'none';
        }

        // ── MOLOSSER / POWERFUL ───────────────────────────────────────────
        elseif ($this->mb($b, ['rottweiler', 'rottie'])) {
            $profile['size_category']        = 'large';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Massive blocky head with broad flat skull, prominent tan/mahogany points on black coat, thick heavily-muscled neck, broad chest.';
        }
        elseif ($this->mb($b, ['doberman', 'dobermann'])) {
            $profile['size_category']        = 'large';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Sleek athletic — long elegant neck, square body, sleek short coat showing every muscle, elegant pointed head with rust markings.';
        }
        elseif ($this->mb($b, ['boxer'])) {
            $profile['size_category']   = 'large';
            $profile['brachycephalic']  = true;
            $profile['height_change']   = 'large_increase';
            $profile['adult_size_description'] = 'Muscular square-built dog with broad brachycephalic head, undershot jaw with prominent flews, fawn or brindle short coat.';
        }
        elseif ($this->mb($b, ['pit bull', 'pitbull', 'american pit bull', 'american staffordshire',
                               'amstaff', 'american bully'])) {
            $profile['size_category']        = 'medium';
            $profile['height_change']        = 'moderate_increase';
            $profile['adult_size_description']= 'Incredibly muscular — broad blocky head, powerful neck and chest, extreme muscle striations, smooth short coat.';
        }
        elseif ($this->mb($b, ['staffordshire bull terrier', 'staffy', 'staffie'])) {
            $profile['size_category'] = 'medium';
            $profile['height_change'] = 'moderate_increase';
        }
        elseif ($this->mb($b, ['bull terrier', 'english bull terrier'])) {
            $isMini = $this->mb($b, ['miniature', 'mini']);
            $profile['size_category'] = $isMini ? 'small' : 'medium';
            $profile['height_change'] = $isMini ? 'minimal_increase' : 'moderate_increase';
            $profile['adult_size_description'] = 'Unique egg-shaped head — completely flat on top, curved from crown to nose tip. Muscular powerful body.';
        }

        // ── SIGHTHOUNDS ───────────────────────────────────────────────────
        elseif ($this->mb($b, ['whippet', 'greyhound', 'italian greyhound', 'saluki',
                               'afghan hound', 'borzoi', 'azawakh', 'pharaoh hound', 'ibizan hound'])) {
            $isLongCoat = $this->mb($b, ['afghan hound', 'borzoi', 'saluki']);
            $profile['size_category'] = $this->mb($b, ['italian greyhound']) ? 'small' : 'medium';
            $profile['body_shape']    = 'sighthound';
            $profile['coat_type']     = $isLongCoat ? 'long_silky' : 'short';
            $profile['height_change'] = 'large_increase';
            $profile['adult_size_description'] = 'Ultimate athletic dog — aerodynamic silhouette with extreme deep chest tuck, long neck, narrow refined head, extraordinary lean physique.';
        }

        // ── FRENCH BULLDOG / BRACHYCEPHALIC SMALL ────────────────────────
        elseif ($this->mb($b, ['french bulldog', 'frenchie'])) {
            $profile['size_category'] = 'small';
            $profile['brachycephalic']= true;
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'Compact muscular small dog with extremely flat face, large bat-like ears, stocky barrel body, screw tail.';
        }
        elseif ($this->mb($b, ['english bulldog', 'british bulldog', 'bulldog'])) {
            $profile['size_category'] = 'medium';
            $profile['brachycephalic']= true;
            $profile['height_change'] = 'minimal_increase';
            $profile['adult_size_description'] = 'Massively built — enormous head with hanging flews and deep wrinkles, massive chest on short bowed legs.';
        }
        elseif ($this->mb($b, ['pug'])) {
            $profile['size_category'] = 'small';
            $profile['brachycephalic']= true;
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'Small compact dog with extremely wrinkled flat face, large round eyes, cobby square body.';
        }
        elseif ($this->mb($b, ['boston terrier'])) {
            $profile['size_category'] = 'small';
            $profile['brachycephalic']= true;
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'Compact tuxedo dog with bat ears, flat face, and athletic compact build.';
        }
        elseif ($this->mb($b, ['chinese shar pei', 'shar pei'])) {
            $profile['size_category'] = 'medium';
            $profile['brachycephalic']= true;
            $profile['height_change'] = 'moderate_increase';
            $profile['adult_size_description'] = 'Square dog with extraordinarily loose wrinkled skin, small hippo-like head, blue-black tongue.';
        }
        elseif ($this->mb($b, ['shih tzu'])) {
            $profile['size_category'] = 'small';
            $profile['brachycephalic']= true;
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'none';
        }

        // ── TOY / SMALL COMPANION ─────────────────────────────────────────
        elseif ($this->mb($b, ['yorkshire terrier', 'yorkie'])) {
            $profile['size_category'] = 'toy';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'Tiny dog with long, fine, silky STEEL-BLUE and TAN coat, perfectly erect small V-shaped ears.';
        }
        elseif ($this->mb($b, ['maltese'])) {
            $profile['size_category'] = 'toy';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'Tiny all-white dog completely covered in flowing, silky, pure white coat that reaches the ground.';
        }
        elseif ($this->mb($b, ['chihuahua'])) {
            $isLongCoat = $this->mb($b, ['long coat', 'longhaired', 'long hair']);
            $profile['size_category'] = 'toy';
            $profile['coat_type']     = $isLongCoat ? 'long_silky' : 'short';
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = "World's smallest breed — apple-domed skull, large prominent eyes, large erect ears.";
        }
        elseif ($this->mb($b, ['pomeranian'])) {
            $profile['size_category'] = 'toy';
            $profile['coat_type']     = 'double_coat';
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'Tiny fluffy lion-like dog with enormous stand-off double coat, foxy face, tiny erect ears, plumed tail.';
        }
        elseif ($this->mb($b, ['cavalier king charles', 'cavalier'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'minimal_increase';
        }
        elseif ($this->mb($b, ['bichon frise', 'bichon'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = 'curly';
            $profile['height_change'] = 'none';
        }
        elseif ($this->mb($b, ['havanese', 'coton de tulear', 'bolognese'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'none';
        }
        elseif ($this->mb($b, ['lhasa apso'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'none';
        }
        elseif ($this->mb($b, ['papillon'])) {
            $profile['size_category'] = 'toy';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'none';
        }

        // ── LONG AND LOW ──────────────────────────────────────────────────
        elseif ($this->mb($b, ['corgi', 'pembroke', 'cardigan'])) {
            $profile['size_category'] = 'small';
            $profile['body_shape']    = 'long_low';
            $profile['coat_type']     = 'double_coat';
            $profile['height_change'] = 'minimal_increase';
            $profile['adult_size_description'] = 'Long low dog with short powerful legs, very long muscular torso, large upright bat-like ears, foxy face.';
        }
        elseif ($this->mb($b, ['dachshund', 'doxie', 'sausage dog', 'wiener', 'weiner'])) {
            $isLong = $this->mb($b, ['long', 'longhaired', 'long-haired']);
            $isWire = $this->mb($b, ['wire', 'wirehaired', 'wire-haired']);
            $isMini = $this->mb($b, ['mini', 'miniature']);
            $profile['size_category'] = $isMini ? 'toy' : 'small';
            $profile['body_shape']    = 'long_low';
            $profile['coat_type']     = $isLong ? 'long_silky' : ($isWire ? 'wire_harsh' : 'short');
            $profile['height_change'] = 'none';
            $profile['adult_size_description'] = 'The ultimate long-and-low sausage dog — dramatically elongated body on tiny short legs.';
        }
        elseif ($this->mb($b, ['basset hound', 'basset'])) {
            $profile['size_category'] = 'medium';
            $profile['body_shape']    = 'long_low';
            $profile['height_change'] = 'minimal_increase';
            $profile['adult_size_description'] = 'Extremely heavy, low-slung — enormously long velvety ears, deeply wrinkled skin, large soulful eyes, heavy bone.';
        }

        // ── SCHNAUZERS ────────────────────────────────────────────────────
        elseif ($this->mb($b, ['giant schnauzer', 'standard schnauzer', 'miniature schnauzer', 'schnauzer'])) {
            $isGiant = $this->mb($b, ['giant']);
            $isMini  = $this->mb($b, ['miniature', 'mini']);
            $profile['size_category'] = $isGiant ? 'large' : ($isMini ? 'small' : 'medium');
            $profile['coat_type']     = 'wire_harsh';
            $profile['height_change'] = $isGiant ? 'large_increase' : ($isMini ? 'none' : 'moderate_increase');
        }

        // ── TERRIERS ──────────────────────────────────────────────────────
        elseif ($this->mb($b, ['jack russell', 'parson russell', 'russell terrier'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = $this->mb($b, ['wire', 'rough']) ? 'wire_harsh' : 'short';
            $profile['height_change'] = 'minimal_increase';
        }
        elseif ($this->mb($b, ['west highland', 'westie', 'cairn terrier', 'scottish terrier',
                               'scottie', 'border terrier', 'norfolk terrier'])) {
            $profile['size_category'] = 'small';
            $profile['coat_type']     = 'wire_harsh';
            $profile['height_change'] = 'minimal_increase';
        }
        elseif ($this->mb($b, ['airedale terrier', 'airedale'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'wire_harsh';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Largest terrier — tall, athletic, black-and-tan wire coat, long flat rectangular head, distinctive beard.';
        }
        elseif ($this->mb($b, ['soft coated wheaten terrier', 'wheaten'])) {
            $profile['size_category'] = 'medium';
            $profile['coat_type']     = 'wavy_curly';
            $profile['height_change'] = 'moderate_increase';
        }

        // ── HOUNDS ────────────────────────────────────────────────────────
        elseif ($this->mb($b, ['beagle'])) {
            $profile['size_category']        = 'small';
            $profile['height_change']        = 'moderate_increase';
            $profile['adult_size_description']= 'Compact sturdy scent hound — tricolor, square hound head, long pendulous soft ears, deep chest.';
        }
        elseif ($this->mb($b, ['bloodhound', 'coonhound', 'redbone', 'treeing walker', 'plott hound'])) {
            $profile['size_category'] = 'large';
            $profile['height_change'] = 'large_increase';
        }
        elseif ($this->mb($b, ['rhodesian ridgeback'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'short';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Powerful athletic dog with distinctive ridge of reversed hair along the spine, deep wheaten short coat.';
        }
        elseif ($this->mb($b, ['dalmatian'])) {
            $profile['size_category'] = 'large';
            $profile['coat_type']     = 'short';
            $profile['height_change'] = 'large_increase';
            $profile['adult_size_description'] = 'Lean athletic white dog with bold black/liver spots, deep chest, elegant build.';
        }

        // ── HERDING / SPORTING ────────────────────────────────────────────
        elseif ($this->mb($b, ['border collie', 'australian shepherd', 'aussie'])) {
            $profile['size_category'] = 'medium';
            $profile['coat_type']     = 'double_coat';
            $profile['height_change'] = 'moderate_increase';
        }
        elseif ($this->mb($b, ['collie', 'rough collie', 'sheltie', 'shetland sheepdog'])) {
            $isSheltie = $this->mb($b, ['sheltie', 'shetland']);
            $profile['size_category'] = $isSheltie ? 'small' : 'large';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = $isSheltie ? 'moderate_increase' : 'large_increase';
            $profile['adult_size_description'] = 'Strikingly elegant with long flowing mane and frill, narrow aristocratic head, rich sable/tricolor/merle coat.';
        }
        elseif ($this->mb($b, ['australian cattle dog', 'blue heeler', 'red heeler'])) {
            $profile['size_category'] = 'medium';
            $profile['coat_type']     = 'short';
            $profile['height_change'] = 'moderate_increase';
        }
        elseif ($this->mb($b, ['bernese mountain dog', 'berner'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'long_silky';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Large, sturdy tricolor mountain dog — black body with rust/tan points and white blaze/chest/paws.';
        }

        // ── POINTERS / GUN DOGS ───────────────────────────────────────────
        elseif ($this->mb($b, ['vizsla', 'hungarian vizsla'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'short';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Lean muscular golden-rust hunting dog — long aristocratic head, amber eyes, floppy ears, tucked abdomen.';
        }
        elseif ($this->mb($b, ['weimaraner'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'short';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'Sleek silver-grey ghost dog — long elegant neck, deep chest, tucked abdomen, pale grey eyes.';
        }
        elseif ($this->mb($b, ['german shorthaired pointer', 'german wirehaired pointer', 'english pointer', 'pointer'])) {
            $isWire = $this->mb($b, ['wirehaired', 'wire']);
            $profile['size_category'] = 'large';
            $profile['coat_type']     = $isWire ? 'wire_harsh' : 'short';
            $profile['height_change'] = 'large_increase';
        }
        elseif ($this->mb($b, ['bracco italiano', 'italian pointer'])) {
            $profile['size_category']        = 'large';
            $profile['coat_type']            = 'short';
            $profile['height_change']        = 'large_increase';
            $profile['adult_size_description']= 'A large noble hunting dog — pendulous long ears, slightly loose jowl skin, strong athletic build, deep-chested with visible musculature.';
        }
        elseif ($this->mb($b, ['irish setter', 'english setter', 'gordon setter', 'setter'])) {
            $profile['size_category'] = 'large';
            $profile['coat_type']     = 'long_silky';
            $profile['height_change'] = 'large_increase';
        }
        elseif ($this->mb($b, ['cocker spaniel', 'english cocker', 'american cocker'])) {
            $profile['size_category']        = 'medium';
            $profile['coat_type']            = 'long_silky';
            $profile['height_change']        = 'moderate_increase';
            $profile['adult_size_description']= 'Compact spaniel with long luxurious silky coat and feathering, long pendulous ears framing a domed head.';
        }

        // ── OLD ENGLISH / BOUVIER ─────────────────────────────────────────
        elseif ($this->mb($b, ['old english sheepdog', 'oes', 'bobtail'])) {
            $profile['size_category'] = 'large';
            $profile['coat_type']     = 'wavy_curly';
            $profile['height_change'] = 'large_increase';
            $profile['adult_size_description'] = 'Large shaggy dog completely covered in thick profuse grey-and-white coat — even face and eyes covered.';
        }
        elseif ($this->mb($b, ['bouvier des flandres', 'bouvier', 'briard'])) {
            $profile['size_category'] = 'large';
            $profile['coat_type']     = 'wire_harsh';
            $profile['height_change'] = 'large_increase';
        }

        // ── NATIVE / MIXED ────────────────────────────────────────────────
        elseif ($this->mb($b, ['aspin', 'asong pinoy', 'philippine native',
                               'village dog', 'street dog', 'mixed breed', 'mutt', 'mixed'])) {
            $profile['size_category']        = 'medium';
            $profile['height_change']        = 'moderate_increase';
            $profile['adult_size_description']= 'Lean athletic medium dog with smooth short coat, semi-erect or erect ears, sickle tail, and the lithe build of a primitive pariah dog.';
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