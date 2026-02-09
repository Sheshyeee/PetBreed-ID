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
      Log::info("=== GENERATING AGE SIMULATIONS (CURRENT AGE + FUTURE) ===");
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
        'status' => 'generating'
      ];

      // Update status to generating IMMEDIATELY
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Status updated to 'generating'");

      // Extract features with defaults
      $coatColor = $this->dogFeatures['coat_color'] ?? 'brown';
      $coatPattern = $this->dogFeatures['coat_pattern'] ?? 'solid';
      $coatLength = $this->dogFeatures['coat_length'] ?? 'medium';
      $coatTexture = $this->dogFeatures['coat_texture'] ?? 'smooth';
      $build = $this->dogFeatures['build'] ?? 'medium';
      $distinctiveMarkings = $this->dogFeatures['distinctive_markings'] ?? 'none';
      $earType = $this->dogFeatures['ear_type'] ?? 'floppy';
      $eyeColor = $this->dogFeatures['eye_color'] ?? 'brown';
      $sizeEstimate = $this->dogFeatures['size_estimate'] ?? 'medium';
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

      Log::info("Current estimated age: {$estimatedAge} ({$currentAgeYears} years)");
      Log::info("Future projections: In 1 year = {$age1YearLater} years old, In 3 years = {$age3YearsLater} years old");
      Log::info("Using dog features - Color: {$coatColor}, Pattern: {$coatPattern}, Build: {$build}");

      // Helper function to describe aging details
      $getAgingDetails = function ($ageYears) {
        if ($ageYears < 1.5) {
          return "puppy to young adult stage, vibrant glossy coat at peak shine, perfectly clear bright eyes with no cloudiness, fully alert perked ears, highly energetic playful stance, developing muscle definition, zero gray hairs, youthful exuberance";
        } elseif ($ageYears < 3.5) {
          return "young adult in prime, glossy vibrant coat with maximum shine, crystal clear bright eyes, fully perked alert ears, athletic energetic stance with strong posture, well-developed muscles, zero to minimal gray hairs (none if under 3)";
        } elseif ($ageYears < 5.5) {
          return "prime adult stage, coat maintaining good luster with very subtle dulling, eyes still very bright and clear, alert ears, confident stance, peak muscle development, first few gray hairs starting to appear around muzzle edges (5-10% gray if over 4)";
        } elseif ($ageYears < 7.5) {
          return "mature adult stage, coat noticeably less lustrous with visible dulling, gray hairs visible around muzzle and eyebrows (20-30% gray coverage), eyes bright but with slight softening, ears still alert, slight facial lines beginning to show, more relaxed posture";
        } elseif ($ageYears < 9.5) {
          return "mature to senior transition, coat significantly duller and less vibrant, moderate gray/white coverage on face and muzzle (35-45% gray), eyes showing minor cloudiness, visible facial wrinkles developing, calmer demeanor, slight muscle softening";
        } else {
          return "senior stage, coat very dull with thin patches, extensive gray/white coverage especially on face, muzzle, and chest (50%+ gray), eyes noticeably cloudy, pronounced facial wrinkles and sagging, relaxed elderly posture, visible muscle loss";
        }
      };

      $getLifeStage = function ($ageYears) {
        if ($ageYears < 1.5) return "puppy/young adult";
        if ($ageYears < 4) return "young adult";
        if ($ageYears < 7) return "prime adult";
        if ($ageYears < 9) return "mature adult";
        return "senior";
      };

      // GENERATE 1-YEAR IMAGE FIRST
      try {
        Log::info("=== Generating 1_years simulation ===");

        $prompt1Year = "Professional full-body standing photograph of a {$sizeEstimate}-sized {$this->breed} dog at {$age1YearLater} years old ({$getLifeStage($age1YearLater)}), complete side profile showing entire body from nose tip to tail tip with all four paws visible. Specific identifying features: {$coatColor} coat with {$coatPattern} pattern, {$coatLength} {$coatTexture} fur, {$build} build, {$earType} ears, {$eyeColor} eyes" . ($distinctiveMarkings !== 'none' ? ", {$distinctiveMarkings}" : "") . ". Age-specific appearance at {$age1YearLater} years: {$getAgingDetails($age1YearLater)}. Outdoor natural lighting, complete full body visible head to tail to paws, professional dog photography, ultra-sharp focus, 4K quality";

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

        // UPDATE DATABASE IMMEDIATELY AFTER FIRST IMAGE
        $result->update(['simulation_data' => json_encode($simulationData)]);
        Log::info("✓ Generated 1_years simulation: {$simulationPath}");
        Log::info("✓ Database updated with 1_years image");
      } catch (\Exception $e) {
        Log::error("Failed to generate 1_years simulation: " . $e->getMessage());
        $simulationData['1_years'] = null;
      }

      // Wait between API calls (rate limiting)
      sleep(3);

      // GENERATE 3-YEAR IMAGE SECOND
      try {
        Log::info("=== Generating 3_years simulation ===");

        $prompt3Years = "Professional full-body standing photograph of the same {$sizeEstimate}-sized {$this->breed} dog now at {$age3YearsLater} years old ({$getLifeStage($age3YearsLater)}), complete side profile showing entire body from nose tip to tail tip with all four paws visible. Same identifying features: {$coatColor} coat (aged from scan) with {$coatPattern} pattern, {$coatLength} {$coatTexture} fur (age-affected), {$build} build (age-adjusted), {$earType} ears, {$eyeColor} eyes" . ($distinctiveMarkings !== 'none' ? ", same {$distinctiveMarkings}" : "") . ". Age progression at {$age3YearsLater} years: {$getAgingDetails($age3YearsLater)}. Same outdoor setting, complete full body visible head to tail to paws, soft natural lighting revealing age progression, realistic aging markers, 4K quality";

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

        Log::info("✓ Generated 3_years simulation: {$simulationPath}");
      } catch (\Exception $e) {
        Log::error("Failed to generate 3_years simulation: " . $e->getMessage());
        Log::error("Exception details: " . $e->getTraceAsString());
        $simulationData['3_years'] = null;
      }

      // Mark as complete
      $simulationData['status'] = 'complete';

      // Final database update
      $result->update(['simulation_data' => json_encode($simulationData)]);
      Log::info("✓ Final status: complete");
      Log::info("✓ Age simulation generation complete - Projected {$age1YearLater}y and {$age3YearsLater}y from current {$currentAgeYears}y");
    } catch (\Exception $e) {
      Log::error("Age simulation job FAILED: " . $e->getMessage());
      Log::error("Stack trace: " . $e->getTraceAsString());

      // Update status to failed
      $failedData = [
        '1_years' => null,
        '3_years' => null,
        'status' => 'failed'
      ];

      Results::where('id', $this->resultId)->update([
        'simulation_data' => json_encode($failedData)
      ]);
    }
  }
}
