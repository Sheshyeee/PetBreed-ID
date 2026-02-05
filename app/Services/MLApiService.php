<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Response;

class MLApiService
{
  private string $baseUrl;
  private int $timeout;

  public function __construct()
  {
    // Get ML API URL from environment
    $this->baseUrl = rtrim(env('PYTHON_ML_API_URL', 'http://localhost:8001'), '/');
    $this->timeout = (int) env('PYTHON_ML_API_TIMEOUT', 60);

    Log::info('MLApiService initialized', ['base_url' => $this->baseUrl]);
  }

  /**
   * Predict dog breed from image
   * 
   * @param UploadedFile|string $image - UploadedFile or path to image
   * @return array
   */
  public function predictBreed($image): array
  {
    try {
      Log::info('🔮 Sending prediction request to ML API');

      // Prepare the file for upload
      if ($image instanceof UploadedFile) {
        $filePath = $image->getRealPath();
        $fileName = $image->getClientOriginalName();
        $mimeType = $image->getMimeType();
      } else {
        // Image is a file path
        $filePath = $image;
        $fileName = basename($image);
        $mimeType = mime_content_type($image);
      }

      // Send multipart request to ML API
      /** @var Response $response */
      $response = Http::timeout($this->timeout)
        ->attach('file', file_get_contents($filePath), $fileName)
        ->post($this->baseUrl . '/predict');

      if ($response->failed()) {
        Log::error('ML API prediction failed', [
          'status' => $response->status(),
          'body' => $response->body()
        ]);

        return [
          'success' => false,
          'error' => 'ML API returned error: ' . $response->status(),
          'details' => $response->json()
        ];
      }

      $data = $response->json();

      Log::info('✓ ML API prediction successful', [
        'breed' => $data['breed'] ?? 'unknown',
        'confidence' => $data['confidence'] ?? 0,
        'is_memory_match' => $data['is_memory_match'] ?? false
      ]);

      return [
        'success' => true,
        'method' => $data['is_memory_match'] ? 'memory' : 'model',
        'breed' => $data['breed'],
        'confidence' => $data['confidence'],
        'top_predictions' => $this->formatTopPredictions($data['top_5'] ?? []),
        'metadata' => [
          'is_memory_match' => $data['is_memory_match'] ?? false,
          'memory_info' => $data['memory_info'] ?? [],
          'learning_stats' => $data['learning_stats'] ?? []
        ]
      ];
    } catch (\Exception $e) {
      Log::error('ML API prediction exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Learn/correct a breed prediction
   * 
   * @param UploadedFile|string $image
   * @param string $correctBreed
   * @return array
   */
  public function learnBreed($image, string $correctBreed): array
  {
    try {
      Log::info('📚 Sending learn request to ML API', ['breed' => $correctBreed]);

      // Prepare the file for upload
      if ($image instanceof UploadedFile) {
        $filePath = $image->getRealPath();
        $fileName = $image->getClientOriginalName();
      } else {
        $filePath = $image;
        $fileName = basename($image);
      }

      // Send multipart request with breed name
      /** @var Response $response */
      $response = Http::timeout($this->timeout)
        ->attach('file', file_get_contents($filePath), $fileName)
        ->post($this->baseUrl . '/learn', [
          'breed' => $correctBreed
        ]);

      if ($response->failed()) {
        Log::error('ML API learn failed', [
          'status' => $response->status(),
          'body' => $response->body()
        ]);

        return [
          'success' => false,
          'error' => 'ML API returned error: ' . $response->status()
        ];
      }

      $data = $response->json();

      Log::info('✓ ML API learn successful', [
        'status' => $data['status'] ?? 'unknown',
        'breed' => $data['breed'] ?? $correctBreed
      ]);

      return [
        'success' => true,
        'status' => $data['status'], // 'added', 'updated', or 'skipped'
        'message' => $data['message'],
        'breed' => $data['breed']
      ];
    } catch (\Exception $e) {
      Log::error('ML API learn exception', [
        'error' => $e->getMessage()
      ]);

      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get memory/learning statistics
   * 
   * @return array
   */
  public function getMemoryStats(): array
  {
    try {
      /** @var Response $response */
      $response = Http::timeout(10)->get($this->baseUrl . '/memory/stats');

      if ($response->failed()) {
        return [
          'success' => false,
          'error' => 'Failed to get memory stats'
        ];
      }

      return [
        'success' => true,
        'data' => $response->json()
      ];
    } catch (\Exception $e) {
      Log::error('ML API stats exception', ['error' => $e->getMessage()]);

      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Clear all learned references (admin only)
   * 
   * @return array
   */
  public function clearMemory(): array
  {
    try {
      /** @var Response $response */
      $response = Http::timeout(10)->delete($this->baseUrl . '/memory/clear');

      if ($response->failed()) {
        return [
          'success' => false,
          'error' => 'Failed to clear memory'
        ];
      }

      return [
        'success' => true,
        'message' => $response->json()['message'] ?? 'Memory cleared'
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Health check for ML API
   * 
   * @return bool
   */
  public function isHealthy(): bool
  {
    try {
      /** @var Response $response */
      $response = Http::timeout(5)->get($this->baseUrl . '/');

      if ($response->successful()) {
        $data = $response->json();
        return $data['status'] === 'healthy' && $data['model_loaded'] === true;
      }

      return false;
    } catch (\Exception $e) {
      Log::warning('ML API health check failed', ['error' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * Format top predictions for Laravel response
   * 
   * @param array $predictions
   * @return array
   */
  private function formatTopPredictions(array $predictions): array
  {
    $formatted = [];

    foreach ($predictions as $pred) {
      $formatted[] = [
        'breed' => $pred['breed'] ?? 'Unknown',
        'confidence' => round(($pred['confidence'] ?? 0) * 100, 2) // Convert to percentage
      ];
    }

    // Ensure we have exactly 5 predictions
    while (count($formatted) < 5) {
      $formatted[] = [
        'breed' => 'Other Breeds',
        'confidence' => 0
      ];
    }

    return array_slice($formatted, 0, 5);
  }
}
