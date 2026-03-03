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

  protected $result;

  public function __construct(Results $result)
  {
    $this->result = $result;
  }

  public function handle(): void
  {
    $result = $this->result;
    $this->updateStatus($result, 'generating');

    try {
      $breed = $result->breed ?? 'Unknown Dog';
      $profile = $this->getBreedAgingProfile($breed);

      // We generate both 1yr and 3yr simultaneously for consistency
      $prompts = [
        '1_years' => $this->generatePrompt($breed, '1 year older', $profile, $result->image),
        '3_years' => $this->generatePrompt($breed, '3 years older', $profile, $result->image),
      ];

      // Logic for Image Generation API calls would go here 
      // (Assuming your existing implementation uses Guzzle to hit the Gemini/Nano Banana endpoint)

      // SIMULATED MOCK PATHS (Replace with your actual API storage logic)
      $paths = [
        '1_years' => "simulations/{$result->scan_id}_1y.png",
        '3_years' => "simulations/{$result->scan_id}_3y.png",
      ];

      $this->updateStatus($result, 'complete', $paths, $profile);
    } catch (\Exception $e) {
      Log::error("Simulation Failed: " . $e->getMessage());
      $this->updateStatus($result, 'failed', [], [], $e->getMessage());
    }
  }

  /**
   * Generates a highly descriptive, aggressive prompt that forces the AI to execute
   * noticeable structural and textural changes to the dog.
   */
  private function generatePrompt(string $breed, string $ageLabel, array $profile, string $originalImg): string
  {
    $isOneYear = str_contains($ageLabel, '1');

    // Core instruction to force a noticeable transformation
    $baseInstruction = "IMAGE MODIFICATION COMMAND: You are tasked with aging the specific dog in the provided image by exactly {$ageLabel}. CRITICAL: You MUST create a STRIKING AND OBVIOUS VISUAL CHANGE while keeping the dog's core identity, markings, and the original background perfectly intact. Do not just apply a filter; alter the physical geometry of the dog.";

    // Dynamic physical and textural changes based on age milestone
    if ($isOneYear) {
      $structuralChanges = "STRUCTURAL SHIFT (Adolescent to Adult): {$profile['aging_1yr_body']}";
      $facialChanges = "FACIAL/COAT SHIFT: {$profile['aging_1yr_face']}";
    } else {
      $structuralChanges = "STRUCTURAL SHIFT (Full Maturity): {$profile['aging_3yr_body']}";
      $facialChanges = "FACIAL/COAT SHIFT: {$profile['aging_3yr_face']}";
    }

    return "{$baseInstruction} 
            Target Breed: {$breed}. 
            Original Image: {$originalImg}. 
            
            EXECUTE THESE TRANSFORMATIONS:
            1. {$structuralChanges}
            2. {$facialChanges}
            3. MANDATORY: The dog's overall silhouette must noticeably change to reflect this aging process. Expand the chest, alter the snout length, or change the coat volume exactly as instructed above. Make it undeniably older.";
  }

  /**
   * Detailed Breed Traits Database
   * Extensively rewritten to provide extreme, visually actionable instructions for the AI.
   */
  private function getBreedAgingProfile(string $breed): array
  {
    $b = strtolower($breed);

    // DEFAULT (Medium/Mixed breeds)
    $default = [
      'aging_1yr_body' => 'Visibly increase muscle mass. Widen the chest cavity by 15%. Legs should look thicker and fully developed, losing all lanky puppy proportions.',
      'aging_1yr_face' => 'Elongate the snout slightly. Remove all round puppy fat from the cheeks. Coat should appear denser and less fluffy.',
      'aging_3yr_body' => 'Thicken the neck significantly. The body should look dense, heavy, and fully settled into adult weight. Subtle drooping of skin under the neck.',
      'aging_3yr_face' => 'PROMINENT GRAYING: Add highly visible white and gray hairs covering the chin, muzzle, and dusting the eyebrows. The eyes should look deeper set and calmer.',
    ];

    // LARGE/MOLOSSER BREEDS (Boxers, Rotts, Mastiffs, Danes, Bulldogs)
    if ($this->mb($b, ['boxer', 'rottweiler', 'mastiff', 'dane', 'bully', 'bulldog', 'pitbull'])) {
      return [
        'aging_1yr_body' => 'DRAMATIC MASS INCREASE: Broaden the shoulders and expand the chest into a massive barrel shape. The dog must look significantly more muscular, powerful, and intimidating.',
        'aging_1yr_face' => 'Widen the skull and make the muzzle much blockier/squarer. Deepen the wrinkles on the forehead.',
        'aging_3yr_body' => 'HEAVY MATURITY: Add heavy, thick mass to the neck. The jowls (lips) and neck skin should noticeably sag downwards, showing gravity and age.',
        'aging_3yr_face' => 'HEAVY GRAYING: Paint distinct, stark white/frosty gray fur all over the muzzle, lips, and under the chin (Boxers/Rotts gray very visibly). Deepen the facial creases.',
      ];
    }

    // SHEPHERDS/WORKING/SPITZ (GSD, Malinois, Husky, Malamute, Collie)
    if ($this->mb($b, ['shepherd', 'malinois', 'husky', 'collie', 'malamute', 'akita'])) {
      return [
        'aging_1yr_body' => 'COAT EXPLOSION: The puppy fuzz must be completely replaced by a thick, coarse, harsh adult double-coat. Widen the chest and add noticeable lean muscle to the hindquarters.',
        'aging_1yr_face' => 'The snout must become highly elongated, sharp, and wolf-like. The ears must be perfectly erect, firm, and larger in proportion to the head.',
        'aging_3yr_body' => 'STURDY BUILD: The chest drops deeper. Develop a highly visible, thick "ruff" or mane of fur around the neck and shoulders.',
        'aging_3yr_face' => 'SILVERING: Lighten the dark mask on the face. Add a distinct dusting of silver/white guard hairs across the top of the head, eyebrows, and along the sides of the muzzle.',
      ];
    }

    // TOY/SMALL BREEDS (Poodle, Terrier, Chihuahua, Shih Tzu, Yorkie)
    if ($this->mb($b, ['poodle', 'terrier', 'yorkie', 'chi', 'shi', 'pug', 'maltese'])) {
      return [
        'aging_1yr_body' => 'TEXTURE OVERHAUL: Stop increasing physical height. Instead, drastically change the coat texture—make it noticeably longer, curlier, or wirier depending on the breed. The body becomes compact and solid.',
        'aging_1yr_face' => 'Facial features become sharper and less round. Eyes appear slightly smaller in proportion to the fully grown head. Grow the facial hair (beard/mustache) significantly if it is a long-haired breed.',
        'aging_3yr_body' => 'The coat loses its puppy shine, becoming highly textured, slightly coarse, and thick. The posture is rigid and alert.',
        'aging_3yr_face' => 'EXTREME FROSTING: The most noticeable change MUST be a heavy mask of white/gray fur completely surrounding the nose, mouth, and eyes. The fur colors should look slightly faded and mature.',
      ];
    }

    return $default;
  }

  private function mb(string $b, array $patterns): bool
  {
    foreach ($patterns as $p) {
      if (stripos($b, $p) !== false) return true;
    }
    return false;
  }

  private function updateStatus(Results $result, string $status, array $paths = [], array $profile = [], ?string $error = null): void
  {
    $simulationData = json_decode($result->simulation_data, true) ?? [];

    $newData = [
      'status'     => $status,
      '1_years'    => $paths['1_years'] ?? ($simulationData['1_years'] ?? null),
      '3_years'    => $paths['3_years'] ?? ($simulationData['3_years'] ?? null),
      'updated_at' => now()->toIso8601String(),
    ];

    if (!empty($profile)) $newData['breed_profile'] = $profile;
    if ($error) $newData['error'] = $error;

    $result->update(['simulation_data' => json_encode($newData)]);
  }
}
