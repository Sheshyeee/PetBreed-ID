<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SimulationStatusController extends Controller
{
    /**
     * Get simulation status - COMPLETELY REWRITTEN
     */
    public function getStatus(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');

            if (!$scanId) {
                Log::warning('⚠️ No scan_id provided');
                return $this->errorResponse('No scan_id provided', 404);
            }

            Log::info('🔍 Fetching simulation status', ['scan_id' => $scanId]);

            // ALWAYS fetch fresh from database - don't trust cache for image URLs
            $result = Results::where('scan_id', $scanId)->first();

            if (!$result) {
                Log::error('❌ Scan not found in database', ['scan_id' => $scanId]);
                return $this->errorResponse('Scan not found', 404);
            }

            // Log what we found in the database
            Log::info('✅ Result found in database', [
                'scan_id' => $scanId,
                'result_id' => $result->id,
                'breed' => $result->breed,
                'image_field' => $result->image,
                'created_at' => $result->created_at,
            ]);

            $responseData = $this->buildResponse($result, $scanId);

            // Light caching (3 seconds) to prevent hammering
            $cacheKey = "sim_status_{$scanId}";
            $cacheTTL = 3;
            Cache::put($cacheKey, $responseData, $cacheTTL);

            return response()->json($responseData)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        } catch (\Exception $e) {
            Log::error('❌ Simulation status error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'scan_id' => $request->input('scan_id'),
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Build response data - FETCH DIRECTLY FROM DATABASE
     */
    private function buildResponse($result, $scanId)
    {
        // Parse simulation data
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

        // Get the original image path from the database
        $originalImagePath = $result->image;

        // Build full URLs for all images
        $originalImageUrl = $this->buildFullImageUrl($originalImagePath, $baseUrl);
        $simulation1YearUrl = $this->buildFullImageUrl($simulationData['1_years'] ?? null, $baseUrl);
        $simulation3YearsUrl = $this->buildFullImageUrl($simulationData['3_years'] ?? null, $baseUrl);

        // Verify images exist
        $this->verifyImageExists($originalImagePath, 'original');
        $this->verifyImageExists($simulationData['1_years'] ?? null, '1_year');
        $this->verifyImageExists($simulationData['3_years'] ?? null, '3_years');

        // Comprehensive logging
        Log::info('📊 Response data built', [
            'scan_id' => $scanId,
            'status' => $status,
            'original_image_path' => $originalImagePath,
            'original_image_url' => $originalImageUrl,
            'simulation_1yr_path' => $simulationData['1_years'] ?? null,
            'simulation_1yr_url' => $simulation1YearUrl,
            'simulation_3yr_path' => $simulationData['3_years'] ?? null,
            'simulation_3yr_url' => $simulation3YearsUrl,
            'base_url' => $baseUrl,
        ]);

        return [
            'status' => $status,
            'simulations' => [
                '1_years' => $simulation1YearUrl,
                '3_years' => $simulation3YearsUrl,
            ],
            'original_image' => $originalImageUrl,
            'breed' => $result->breed,
            'scan_id' => $scanId,
            'timestamp' => now()->timestamp,
            'progress' => $this->calculateProgress($simulation1YearUrl, $simulation3YearsUrl),
            'breed_profile' => $simulationData['breed_profile'] ?? null,
        ];
    }

    /**
     * Build full image URL
     */
    private function buildFullImageUrl($path, $baseUrl)
    {
        // If path is null or empty, return null
        if (!$path || trim($path) === '') {
            Log::debug('⚠️ Empty image path');
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            Log::debug('✅ Already full URL', ['url' => $path]);
            return $path;
        }

        // Build full URL - ensure no double slashes
        $cleanPath = ltrim($path, '/');
        $cleanBaseUrl = rtrim($baseUrl, '/');
        $fullUrl = $cleanBaseUrl . '/' . $cleanPath;

        Log::debug('🔗 Built image URL', [
            'input_path' => $path,
            'base_url' => $baseUrl,
            'output_url' => $fullUrl,
        ]);

        return $fullUrl;
    }

    /**
     * Verify image exists in storage
     */
    private function verifyImageExists($path, $type)
    {
        if (!$path) {
            Log::debug("⏭️ Skipping {$type} - no path");
            return false;
        }

        try {
            $exists = Storage::disk('object-storage')->exists($path);

            if ($exists) {
                Log::info("✅ {$type} image EXISTS in storage", ['path' => $path]);
            } else {
                Log::warning("⚠️ {$type} image NOT FOUND in storage", ['path' => $path]);
            }

            return $exists;
        } catch (\Exception $e) {
            Log::error("❌ Failed to check {$type} image", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Calculate progress
     */
    private function calculateProgress($url1Year, $url3Years)
    {
        $completed = 0;
        if ($url1Year) $completed++;
        if ($url3Years) $completed++;

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
