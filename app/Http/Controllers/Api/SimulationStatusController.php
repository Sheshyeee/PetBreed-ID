<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SimulationStatusController extends Controller
{
    /**
     * Get simulation status with smart caching
     */
    public function getStatus(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');

            if (!$scanId) {
                return $this->errorResponse('No scan_id provided', 404);
            }

            $cacheKey = "sim_status_{$scanId}";
            $responseData = Cache::get($cacheKey);

            if (!$responseData) {
                $result = Results::where('scan_id', $scanId)->first();

                if (!$result) {
                    return $this->errorResponse('Scan not found', 404);
                }

                $responseData = $this->buildResponse($result, $scanId);

                // Adaptive caching based on status
                $status = $responseData['status'];
                $cacheTTL = match ($status) {
                    'complete' => 300,
                    'failed' => 60,
                    default => 3
                };

                Cache::put($cacheKey, $responseData, $cacheTTL);
            }

            return response()->json($responseData)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        } catch (\Exception $e) {
            Log::error('Simulation status error', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Build response - FIXED to match ResultController pattern
     */
    private function buildResponse($result, $scanId)
    {
        $simulationData = json_decode($result->simulation_data, true);

        if (!is_array($simulationData)) {
            $simulationData = [
                '1_years' => null,
                '3_years' => null,
                'status' => 'pending',
            ];
        }

        $baseUrl = config('filesystems.disks.object-storage.url');
        $status = $simulationData['status'] ?? 'pending';

        // CRITICAL: Build original image URL EXACTLY like ResultController
        $originalImageUrl = $baseUrl . '/' . $result->image;

        $simulations = [
            '1_years' => $this->buildImageUrl($simulationData['1_years'] ?? null, $baseUrl),
            '3_years' => $this->buildImageUrl($simulationData['3_years'] ?? null, $baseUrl),
        ];

        Log::info('🖼️ API Response Image URLs', [
            'scan_id' => $scanId,
            'result_image_path' => $result->image,
            'original_url' => $originalImageUrl,
            'base_url' => $baseUrl,
        ]);

        return [
            'status' => $status,
            'simulations' => $simulations,
            'original_image' => $originalImageUrl,
            'breed' => $result->breed,
            'scan_id' => $scanId,
            'timestamp' => now()->timestamp,
            'progress' => $this->calculateProgress($simulations),
            'breed_profile' => $simulationData['breed_profile'] ?? null,
        ];
    }

    /**
     * Build image URL
     */
    private function buildImageUrl($path, $baseUrl)
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $baseUrl . '/' . $path;
    }

    /**
     * Calculate progress
     */
    private function calculateProgress($simulations)
    {
        $completed = 0;
        if ($simulations['1_years']) $completed++;
        if ($simulations['3_years']) $completed++;

        return [
            'completed' => $completed,
            'total' => 2,
            'percentage' => ($completed / 2) * 100
        ];
    }

    /**
     * Error response
     */
    private function errorResponse($message, $code)
    {
        return response()->json([
            'status' => 'failed',
            'simulations' => [
                '1_years' => null,
                '3_years' => null,
            ],
            'message' => $message
        ], $code);
    }
}
