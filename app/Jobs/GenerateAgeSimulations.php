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
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 150;  // ⚡ Reduced from 180
  public $tries = 2;

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
      Log::info("=== ULTRA-FAST GUARANTEED TRANSFORMATIONS ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);
      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      $imageData = $this->prepareOriginalImage($this->imagePath);
      if (!$imageData) {
        throw new \Exception("Failed to load original image");
      }

      $breedSize = $this->getBreedSize($this->breed);
      Log::info("✓ Breed size: {$breedSize}");

      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => ['breed_size' => $breedSize]
      ];

      $result->update(['simulation_data' => json_encode($simulationData)]);

      // ⚡ COMPACT prompts for SPEED
      $prompt1Year = $this->buildCompactPrompt($this->breed, 1, $breedSize);
      $prompt3Years = $this->buildCompactPrompt($this->breed, 3, $breedSize);

      Log::info("=== PARALLEL ULTRA-FAST generation ===");

      $promises = [
        '1_year' => $this->generateImageAsync($prompt1Year, $imageData),
        '3_years' => $this->generateImageAsync($prompt3Years, $imageData)
      ];

      $results = Promise\Utils::settle($promises)->wait();

      // Process results
      if ($results['1_year']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['1_year']['value'];
          $simulationPath = $this->saveSimulationWebP($imageOutput, '1_years');
          $simulationData['1_years'] = $simulationPath;
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ 1-year: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Save 1-year failed: " . $e->getMessage());
          $simulationData['error_1year'] = $e->getMessage();
        }
      } else {
        Log::error("1-year failed: " . $results['1_year']['reason']->getMessage());
        $simulationData['error_1year'] = $results['1_year']['reason']->getMessage();
      }

      if ($results['3_years']['state'] === 'fulfilled') {
        try {
          $imageOutput = $results['3_years']['value'];
          $simulationPath = $this->saveSimulationWebP($imageOutput, '3_years');
          $simulationData['3_years'] = $simulationPath;
          Log::info("✓ 3-year: {$simulationPath}");
        } catch (\Exception $e) {
          Log::error("Save 3-year failed: " . $e->getMessage());
          $simulationData['error_3year'] = $e->getMessage();
        }
      } else {
        Log::error("3-year failed: " . $results['3_years']['reason']->getMessage());
        $simulationData['error_3year'] = $results['3_years']['reason']->getMessage();
      }

      $simulationData['status'] = 'complete';
      $result->update(['simulation_data' => json_encode($simulationData)]);

      $elapsed = round(microtime(true) - $startTime, 2);
      Log::info("✓ DONE in {$elapsed}s");
    } catch (\Exception $e) {
      Log::error("Job FAILED: " . $e->getMessage());
      Results::where('id', $this->resultId)->update([
        'simulation_data' => json_encode([
          '1_years' => null,
          '3_years' => null,
          'status' => 'failed',
          'error' => $e->getMessage()
        ])
      ]);
    }
  }

  /**
   * ⚡ FAST: Simple breed size detection
   */
  private function getBreedSize($breed)
  {
    $breedLower = strtolower($breed);

    // Quick pattern matching
    if (preg_match('/(great dane|mastiff|saint bernard|newfoundland|wolfhound|leonberger|bernese)/i', $breedLower)) {
      return 'giant';
    }
    if (preg_match('/(shepherd|retriever|labrador|rottweiler|doberman|boxer|husky|malamute|akita)/i', $breedLower)) {
      return 'large';
    }
    if (preg_match('/(chihuahua|pomeranian|yorkshire|shih tzu|pug|maltese|toy)/i', $breedLower)) {
      return 'small';
    }

    return 'medium';
  }

  /**
   * ⚡ ULTRA-COMPACT PROMPT - Guaranteed visible changes, minimal words
   */
  private function buildCompactPrompt($breed, $yearsAhead, $breedSize)
  {
    if ($yearsAhead === 1) {
      // 1 YEAR - Moderate but CLEAR changes
      $changes = [
        "20% gray muzzle hairs",
        "eyes 20% duller, slight haze",
        "coat 25% less shiny, rougher",
        "subtle jowl loosening",
        "calmer expression"
      ];

      if ($breedSize === 'giant' || $breedSize === 'large') {
        $changes[] = "if puppy: 35% larger body";
      }
    } else {
      // 3 YEARS - EXTREME dramatic changes
      $changes = [
        "50% gray/white muzzle REQUIRED",
        "eyes cloudy/milky aged look",
        "coat dull, thin, rough texture",
        "VISIBLE jowl/face sagging",
        "gray patches: eyebrows, ears, chest",
        "less muscle tone, weight shift",
        "tired senior demeanor"
      ];

      if ($breedSize === 'small') {
        $changes[] = "accelerated aging signs";
      }
    }

    $changesList = implode(". ", $changes);

    return "Age {$breed} +{$yearsAhead}yr. MUST CHANGE: {$changesList}. KEEP: breed, color, markings, pose, background. Photorealistic.";
  }

  /**
   * ⚡ MAXIMUM SPEED generation settings
   */
  private function generateImageAsync($prompt, $imageData)
  {
    $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    if (!$apiKey) {
      throw new \Exception("Gemini API key not configured");
    }

    $modelName = "nano-banana-pro-preview";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

    $client = new Client([
      'timeout' => 75,  // ⚡ Reduced from 90
      'connect_timeout' => 3,  // ⚡ Reduced from 5
    ]);

    $payload = [
      'contents' => [
        [
          'parts' => [
            ['text' => $prompt],
            [
              'inlineData' => [
                'mimeType' => $imageData['mimeType'],
                'data' => $imageData['base64']
              ]
            ]
          ]
        ]
      ],
      'generationConfig' => [
        'temperature' => 0.65,  // ⚡ Balanced: creativity + speed
        'topK' => 35,
        'topP' => 0.9,
        'maxOutputTokens' => 8192
      ]
    ];

    return $client->postAsync($endpoint, [
      'json' => $payload,
      'headers' => ['Content-Type' => 'application/json']
    ])->then(function ($response) {
      $responseData = json_decode($response->getBody()->getContents(), true);

      if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        return base64_decode($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data']);
      } elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $base64 = preg_replace('/```[\w]*\n?/', '', $responseData['candidates'][0]['content']['parts'][0]['text']);
        return base64_decode(trim($base64));
      }

      throw new \Exception("No image data in response");
    });
  }

  /**
   * ⚡ AGGRESSIVE optimization for speed
   */
  private function prepareOriginalImage($fullPath)
  {
    try {
      $imageContents = Storage::disk('object-storage')->get($fullPath);
      if (empty($imageContents)) {
        throw new \Exception('Empty image');
      }

      $imageInfo = @getimagesizefromstring($imageContents);
      if ($imageInfo === false) {
        throw new \Exception('Invalid image');
      }

      $width = $imageInfo[0];
      $height = $imageInfo[1];

      // ⚡ AGGRESSIVE resize for maximum speed
      $maxSize = 1280;  // ⚡ Reduced from 1536
      if ($width > $maxSize || $height > $maxSize) {
        Log::info("Resizing {$width}x{$height} to {$maxSize}px");
        $imageContents = $this->resizeImage($imageContents, $maxSize, $maxSize);
      }

      // ⚡ Lower quality JPEG for faster upload
      $img = imagecreatefromstring($imageContents);
      ob_start();
      imagejpeg($img, null, 78);  // ⚡ Reduced from 85
      $imageContents = ob_get_clean();
      imagedestroy($img);

      return [
        'base64' => base64_encode($imageContents),
        'mimeType' => 'image/jpeg'
      ];
    } catch (\Exception $e) {
      Log::error("Image prep failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * ⚡ Fast resize
   */
  private function resizeImage($imageContents, $maxWidth, $maxHeight)
  {
    $image = imagecreatefromstring($imageContents);
    $width = imagesx($image);
    $height = imagesy($image);

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    ob_start();
    imagejpeg($resized, null, 78);  // ⚡ Lower quality
    $output = ob_get_clean();

    imagedestroy($image);
    imagedestroy($resized);

    return $output;
  }

  /**
   * ⚡ Fast WebP save
   */
  private function saveSimulationWebP($imageOutput, $type)
  {
    $img = imagecreatefromstring($imageOutput);

    ob_start();
    imagewebp($img, null, 78);  // ⚡ Reduced from 85
    $webpData = ob_get_clean();
    imagedestroy($img);

    $filename = "simulation_{$type}_" . time() . "_" . Str::random(6) . ".webp";
    $path = "simulations/{$filename}";

    Storage::disk('object-storage')->put($path, $webpData);

    return $path;
  }
}
