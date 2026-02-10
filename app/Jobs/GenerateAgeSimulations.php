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
use OpenAI\Laravel\Facades\OpenAI;

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

        $response = OpenAI::images()->create([
          'model' => 'dall-e-2',
          'prompt' => $prompt1Year,
          'n' => 1,
          'size' => '512x512',
          'response_format' => 'url',
        ]);

        $generatedImageUrl = $response->data[0]->url;
        $generatedImageContent = file_get_contents($generatedImageUrl);
        $simulationFilename = "simulation_1_years_" . time() . "_" . Str::random(6) . ".png";
        $simulationPath = "simulations/{$simulationFilename}";

        Storage::disk('object-storage')->put($simulationPath, $generatedImageContent);
        $simulationData['1_years'] = $simulationPath;

        // UPDATE DATABASE IMMEDIATELY
        $result->update(['simulation_data' => json_encode($simulationData)]);
        Log::info("✓ Generated 1_years: {$simulationPath}");
      } catch (\OpenAI\Exceptions\ErrorException $e) {
        // OpenAI API specific error
        Log::error("OpenAI API Error (1-year): " . $e->getMessage());
        $simulationData['1_years'] = null;
        $simulationData['error_1year'] = 'Content policy rejection or API error';
      } catch (\Exception $e) {
        Log::error("Failed 1_years simulation: " . $e->getMessage());
        $simulationData['1_years'] = null;
      }

      // Wait between API calls
      sleep(3);

      // GENERATE 3-YEAR IMAGE
      try {
        Log::info("=== Generating 3_years simulation ===");

        // SAFER PROMPT
        $prompt3Years = "A {$this->breed} dog portrait, {$coatColor} colored {$coatLength} fur with {$coatPattern} pattern, {$build} build, {$getSimpleAgeDescription($age3YearsLater)}, professional pet photography, natural outdoor lighting";

        Log::info("Prompt 3-year: " . substr($prompt3Years, 0, 150) . "...");

        $response = OpenAI::images()->create([
          'model' => 'dall-e-2',
          'prompt' => $prompt3Years,
          'n' => 1,
          'size' => '512x512',
          'response_format' => 'url',
        ]);

        $generatedImageUrl = $response->data[0]->url;
        $generatedImageContent = file_get_contents($generatedImageUrl);
        $simulationFilename = "simulation_3_years_" . time() . "_" . Str::random(6) . ".png";
        $simulationPath = "simulations/{$simulationFilename}";

        Storage::disk('object-storage')->put($simulationPath, $generatedImageContent);
        $simulationData['3_years'] = $simulationPath;

        Log::info("✓ Generated 3_years: {$simulationPath}");
      } catch (\OpenAI\Exceptions\ErrorException $e) {
        Log::error("OpenAI API Error (3-year): " . $e->getMessage());
        $simulationData['3_years'] = null;
        $simulationData['error_3year'] = 'Content policy rejection or API error';
      } catch (\Exception $e) {
        Log::error("Failed 3_years simulation: " . $e->getMessage());
        $simulationData['3_years'] = null;
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
}
