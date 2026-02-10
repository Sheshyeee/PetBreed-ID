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
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 300; // 5 minutes max
  public $tries = 1; // Don't retry on failure

  protected int $resultId;
  protected string $breed;
  protected array $dogFeatures;

  public function __construct(int $resultId, string $breed, array $dogFeatures = [])
  {
    $this->resultId = $resultId;
    $this->breed = $breed;
    $this->dogFeatures = $dogFeatures;
  }

  public function handle(): void
  {
    try {
      Log::info("=== GENERATING AGE SIMULATIONS ===");
      Log::info("Result ID: {$this->resultId}, Breed: {$this->breed}");

      $result = Results::find($this->resultId);

      if (!$result) {
        Log::error("Result not found: {$this->resultId}");
        return;
      }

      // Initialize simulation data
      $simulationData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'generating',
        'dog_features' => $this->dogFeatures // PRESERVE FEATURES
      ];

      // Update status to generating IMMEDIATELY
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Status updated to 'generating'");

      // Extract features with defaults
      $coatColor = $this->dogFeatures['coat_color'] ?? 'brown';
      $coatPattern = $this->dogFeatures['coat_pattern'] ?? 'solid';
      $coatLength = $this->dogFeatures['coat_length'] ?? 'medium';
      $build = $this->dogFeatures['build'] ?? 'medium';
      $estimatedAge = $this->dogFeatures['estimated_age'] ?? 'young adult';

      // Convert estimated age to numeric years
      $currentAgeYears = match (strtolower($estimatedAge)) {
        'puppy' => 0.5,
        'young adult' => 2,
        'adult' => 4,
        'mature' => 6,
        'senior' => 9,
        default => 2
      };

      // Calculate future ages
      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1year={$age1YearLater}y, +3years={$age3YearsLater}y");

      // Age descriptions for image generation
      $getAgeDescription = function ($ageYears) {
        if ($ageYears < 2) {
          return "young puppy with bright clear eyes, glossy shiny coat, energetic posture, youthful facial features";
        } elseif ($ageYears < 5) {
          return "healthy adult dog with vibrant coat, alert bright eyes, strong athletic build, mature facial structure";
        } elseif ($ageYears < 8) {
          return "mature adult dog with some subtle gray fur around muzzle, wise calm expression, well-maintained coat, dignified posture";
        } else {
          return "senior dog with noticeable gray fur on face and muzzle, gentle wise eyes, thinner coat texture, calm dignified expression";
        }
      };

      // GENERATE 1-YEAR IMAGE
      try {
        Log::info("=== Generating 1_years simulation ===");

        $prompt1Year = "Professional high-quality photograph of a {$this->breed} dog. Physical characteristics: {$coatColor} colored fur with {$coatPattern} pattern, {$coatLength} coat length, {$build} body build. Age appearance: {$getAgeDescription($age1YearLater)}. Portrait style photography, natural outdoor lighting, sharp focus, photorealistic, detailed texture, professional pet photography quality.";

        Log::info("Prompt 1-year: " . substr($prompt1Year, 0, 150) . "...");

        $generatedImageData = $this->generateImageWithGemini($prompt1Year);

        if ($generatedImageData) {
          $simulationFilename = "simulation_1_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $generatedImageData);
          $simulationData['1_years'] = $simulationPath;

          // UPDATE DATABASE IMMEDIATELY
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ Generated 1_years: {$simulationPath}");
        } else {
          throw new \Exception('Failed to generate image');
        }
      } catch (\Exception $e) {
        Log::error("Failed 1_years simulation: " . $e->getMessage());
        $simulationData['1_years'] = null;
        $simulationData['error_1year'] = 'Image generation failed: ' . $e->getMessage();
      }

      // Wait between API calls to avoid rate limits
      sleep(3);

      // GENERATE 3-YEAR IMAGE
      try {
        Log::info("=== Generating 3_years simulation ===");

        $prompt3Years = "Professional high-quality photograph of a {$this->breed} dog. Physical characteristics: {$coatColor} colored fur with {$coatPattern} pattern, {$coatLength} coat length, {$build} body build. Age appearance: {$getAgeDescription($age3YearsLater)}. Portrait style photography, natural outdoor lighting, sharp focus, photorealistic, detailed texture, professional pet photography quality.";

        Log::info("Prompt 3-year: " . substr($prompt3Years, 0, 150) . "...");

        $generatedImageData = $this->generateImageWithGemini($prompt3Years);

        if ($generatedImageData) {
          $simulationFilename = "simulation_3_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $generatedImageData);
          $simulationData['3_years'] = $simulationPath;

          Log::info("✓ Generated 3_years: {$simulationPath}");
        } else {
          throw new \Exception('Failed to generate image');
        }
      } catch (\Exception $e) {
        Log::error("Failed 3_years simulation: " . $e->getMessage());
        $simulationData['3_years'] = null;
        $simulationData['error_3year'] = 'Image generation failed: ' . $e->getMessage();
      }

      // Mark as complete (even if some failed)
      $simulationData['status'] = 'complete';

      // Final database update
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Final status: complete");
      Log::info("✓ Simulation job finished - Success: 1yr=" . ($simulationData['1_years'] ? 'YES' : 'NO') . ", 3yr=" . ($simulationData['3_years'] ? 'YES' : 'NO'));
    } catch (\Exception $e) {
      Log::error("Age simulation job FAILED: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());

      // Update status to failed
      $failedData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'failed',
        'error' => $e->getMessage(),
        'dog_features' => $this->dogFeatures
      ];

      Results::where('id', $this->resultId)->update([
        'simulation_data' => json_encode($failedData)
      ]);
    }
  }

  /**
   * ✅ FIXED: Generate image using Gemini 2.5 Flash Image (Nano Banana)
   * Uses the correct model: gemini-2.5-flash-image
   * Returns binary image data or null on failure
   */
  private function generateImageWithGemini(string $prompt): ?string
  {
    try {
      $apiKey = env('GEMINI_API_KEY');

      if (!$apiKey) {
        throw new \Exception('GEMINI_API_KEY not configured');
      }

      Log::info('Calling Gemini 2.5 Flash Image (Nano Banana) for image generation...');

      $client = new Client(['timeout' => 120]);

      try {
        // ✅ CORRECT MODEL: gemini-2.5-flash-image (not nano-banana-pro-preview)
        $response = $client->request('POST', "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent?key={$apiKey}", [
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'contents' => [
              [
                'parts' => [
                  ['text' => $prompt]
                ]
              ]
            ],
            'generationConfig' => [
              'temperature' => 0.8,
              'topK' => 64,
              'topP' => 0.95,
              'maxOutputTokens' => 8192,
            ]
          ]
        ]);

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();

        if ($statusCode !== 200) {
          Log::error('Gemini API Error', [
            'status' => $statusCode,
            'body' => substr($responseBody, 0, 500)
          ]);
          throw new \Exception('Gemini API request failed with status: ' . $statusCode);
        }

        $data = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
          Log::error('JSON decode error: ' . json_last_error_msg());
          throw new \Exception('Invalid JSON response from Gemini API');
        }

        Log::info('Gemini Response received', [
          'has_candidates' => isset($data['candidates']),
          'candidate_count' => count($data['candidates'] ?? [])
        ]);

        // Extract image data from response
        // Gemini 2.5 Flash Image returns base64 encoded image in inlineData
        if (isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
          $base64Data = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
          $imageData = base64_decode($base64Data);

          if ($imageData === false) {
            throw new \Exception('Failed to decode base64 image data');
          }

          Log::info('✓ Image extracted from Gemini response', [
            'data_size' => strlen($imageData),
            'mime_type' => $data['candidates'][0]['content']['parts'][0]['inlineData']['mimeType'] ?? 'unknown'
          ]);

          return $imageData;
        }

        // Check if there's an error in the response
        if (isset($data['error'])) {
          Log::error('Gemini API returned error', [
            'error' => $data['error']
          ]);
          throw new \Exception('Gemini API error: ' . json_encode($data['error']));
        }

        // Check for content filters or blocks
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] !== 'STOP') {
          Log::warning('Image generation blocked', [
            'finish_reason' => $data['candidates'][0]['finishReason'],
            'safety_ratings' => $data['candidates'][0]['safetyRatings'] ?? []
          ]);
          throw new \Exception('Image generation blocked: ' . $data['candidates'][0]['finishReason']);
        }

        Log::error('No image data found in Gemini response', [
          'response_structure' => json_encode($data, JSON_PRETTY_PRINT)
        ]);
        throw new \Exception('No image data found in Gemini response');
      } catch (RequestException $e) {
        $errorBody = '';
        if ($e->hasResponse()) {
          $errorBody = $e->getResponse()->getBody()->getContents();
        }

        Log::error('Gemini API request failed', [
          'error' => $e->getMessage(),
          'code' => $e->getCode(),
          'response' => substr($errorBody, 0, 500)
        ]);
        throw new \Exception('Gemini image generation request failed: ' . $e->getMessage());
      }
    } catch (\Exception $e) {
      Log::error('Gemini image generation error: ' . $e->getMessage());
      return null;
    }
  }
}
