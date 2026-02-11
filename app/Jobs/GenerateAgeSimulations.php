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

class GenerateAgeSimulations implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 300; // 5 minutes max
  public $tries = 1; // Don't retry on failure

  protected $resultId;
  protected $breed;
  protected $dogFeatures;

  public function __construct($resultId, $breed, array $dogFeatures = [])
  {
    $this->resultId = $resultId;
    $this->breed = $breed;
    $this->dogFeatures = $dogFeatures;
  }

  public function handle()
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
      $coatColor = isset($this->dogFeatures['coat_color']) ? $this->dogFeatures['coat_color'] : 'brown';
      $coatPattern = isset($this->dogFeatures['coat_pattern']) ? $this->dogFeatures['coat_pattern'] : 'solid';
      $coatLength = isset($this->dogFeatures['coat_length']) ? $this->dogFeatures['coat_length'] : 'medium';
      $build = isset($this->dogFeatures['build']) ? $this->dogFeatures['build'] : 'medium';
      $estimatedAge = isset($this->dogFeatures['estimated_age']) ? $this->dogFeatures['estimated_age'] : 'young adult';

      // Convert estimated age to numeric years (replacing match with switch)
      $estimatedAgeLower = strtolower($estimatedAge);
      switch ($estimatedAgeLower) {
        case 'puppy':
          $currentAgeYears = 0.5;
          break;
        case 'young adult':
          $currentAgeYears = 2;
          break;
        case 'adult':
          $currentAgeYears = 4;
          break;
        case 'mature':
          $currentAgeYears = 6;
          break;
        case 'senior':
          $currentAgeYears = 9;
          break;
        default:
          $currentAgeYears = 2;
          break;
      }

      // Calculate future ages
      $age1YearLater = $currentAgeYears + 1;
      $age3YearsLater = $currentAgeYears + 3;

      Log::info("Ages: Current={$currentAgeYears}y, +1year={$age1YearLater}y, +3years={$age3YearsLater}y");

      // SIMPLIFIED, SAFER PROMPTS (removed detailed aging descriptions that trigger safety)
      $getSimpleAgeDescription = function ($ageYears) {
        if ($ageYears < 2) {
          return "youthful appearance, shiny coat, bright eyes";
        } elseif ($ageYears < 5) {
          return "adult appearance, healthy coat, alert expression";
        } elseif ($ageYears < 8) {
          return "mature appearance, some gray around muzzle";
        } else {
          return "senior appearance, more gray fur, calm expression";
        }
      };

      // GENERATE 1-YEAR IMAGE
      try {
        Log::info("=== Generating 1_years simulation ===");

        // SAFER PROMPT - removed overly detailed descriptions
        $prompt1Year = "A {$this->breed} dog portrait, {$coatColor} colored {$coatLength} fur with {$coatPattern} pattern, {$build} build, {$getSimpleAgeDescription($age1YearLater)}, professional pet photography, natural outdoor lighting";

        Log::info("Prompt 1-year: " . substr($prompt1Year, 0, 150) . "...");

        // Call Gemini Nano Banana Pro API
        $imageData = $this->generateImageWithGemini($prompt1Year);

        if ($imageData) {
          $simulationFilename = "simulation_1_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $imageData);
          $simulationData['1_years'] = $simulationPath;

          // UPDATE DATABASE IMMEDIATELY
          $result->update(['simulation_data' => json_encode($simulationData)]);
          Log::info("✓ Generated 1_years: {$simulationPath}");
        } else {
          throw new \Exception("Failed to generate image data");
        }
      } catch (\Exception $e) {
        Log::error("Failed 1_years simulation: " . $e->getMessage());
        $simulationData['1_years'] = null;
        $simulationData['error_1year'] = 'Content policy rejection or API error: ' . $e->getMessage();
      }

      // Wait between API calls
      sleep(3);

      // GENERATE 3-YEAR IMAGE
      try {
        Log::info("=== Generating 3_years simulation ===");

        // SAFER PROMPT
        $prompt3Years = "A {$this->breed} dog portrait, {$coatColor} colored {$coatLength} fur with {$coatPattern} pattern, {$build} build, {$getSimpleAgeDescription($age3YearsLater)}, professional pet photography, natural outdoor lighting";

        Log::info("Prompt 3-year: " . substr($prompt3Years, 0, 150) . "...");

        // Call Gemini Nano Banana Pro API
        $imageData = $this->generateImageWithGemini($prompt3Years);

        if ($imageData) {
          $simulationFilename = "simulation_3_years_" . time() . "_" . Str::random(6) . ".png";
          $simulationPath = "simulations/{$simulationFilename}";

          Storage::disk('object-storage')->put($simulationPath, $imageData);
          $simulationData['3_years'] = $simulationPath;

          Log::info("✓ Generated 3_years: {$simulationPath}");
        } else {
          throw new \Exception("Failed to generate image data");
        }
      } catch (\Exception $e) {
        Log::error("Failed 3_years simulation: " . $e->getMessage());
        $simulationData['3_years'] = null;
        $simulationData['error_3year'] = 'Content policy rejection or API error: ' . $e->getMessage();
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
        'error' => $e->getMessage()
      ];

      Results::where('id', $this->resultId)->update([
        'simulation_data' => json_encode($failedData)
      ]);
    }
  }

  /**
   * Generate image using Gemini Nano Banana Pro 3 API
   * Uses cURL for maximum compatibility
   * 
   * @param string $prompt
   * @return string|null Binary image data
   */
  private function generateImageWithGemini($prompt)
  {
    try {
      $apiKey = config('services.gemini.api_key');
      if (!$apiKey) {
        $apiKey = env('GEMINI_API_KEY');
      }

      if (!$apiKey) {
        throw new \Exception("Gemini API key not configured");
      }

      // Gemini Nano Banana Pro 3 endpoint
      $modelName = "nano-banana-pro-preview";
      $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

      Log::info("Calling Gemini Nano Banana Pro API...");
      Log::info("Endpoint: {$endpoint}");

      // Prepare request payload - REMOVED responseMimeType
      $payload = array(
        'contents' => array(
          array(
            'parts' => array(
              array(
                'text' => $prompt
              )
            )
          )
        ),
        'generationConfig' => array(
          'temperature' => 0.9,
          'topK' => 40,
          'topP' => 0.95,
          'maxOutputTokens' => 8192
          // REMOVED: 'responseMimeType' => 'image/png'
        )
      );

      $jsonPayload = json_encode($payload);

      // Use cURL for reliable HTTP request
      $ch = curl_init($endpoint);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
      ));
      curl_setopt($ch, CURLOPT_TIMEOUT, 120);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

      $responseBody = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);

      if ($curlError) {
        throw new \Exception("cURL error: {$curlError}");
      }

      if ($httpCode !== 200) {
        Log::error("Gemini API Error (HTTP {$httpCode}): " . $responseBody);
        $errorData = json_decode($responseBody, true);
        $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Unknown error';
        throw new \Exception("Gemini API returned error (HTTP {$httpCode}): {$errorMessage}");
      }

      // Parse JSON response
      $responseData = json_decode($responseBody, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        Log::error("JSON decode error: " . json_last_error_msg());
        Log::error("Raw response: " . substr($responseBody, 0, 500));
        throw new \Exception("Failed to parse Gemini API response");
      }

      Log::info("Gemini API Response received successfully");
      Log::info("Response structure: " . json_encode(array_keys($responseData)));

      // Extract image data from response
      // Nano Banana Pro returns image as inline data in parts
      if (isset($responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        Log::info("✓ Image data extracted successfully (base64 length: " . strlen($base64Image) . ")");
        return base64_decode($base64Image);
      }
      // Alternative response format
      elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        // Sometimes the base64 might be in text field
        $base64Image = $responseData['candidates'][0]['content']['parts'][0]['text'];
        // Remove any potential markdown code block markers
        $base64Image = preg_replace('/```[\w]*\n?/', '', $base64Image);
        $base64Image = trim($base64Image);
        Log::info("✓ Image data extracted from text field (length: " . strlen($base64Image) . ")");
        return base64_decode($base64Image);
      } else {
        Log::error("Unexpected Gemini response format");
        Log::error("Full response: " . json_encode($responseData));
        throw new \Exception("Unexpected response format from Gemini API - no image data found");
      }
    } catch (\Exception $e) {
      Log::error("Gemini API call failed: " . $e->getMessage());
      throw $e;
    }
  }
}
