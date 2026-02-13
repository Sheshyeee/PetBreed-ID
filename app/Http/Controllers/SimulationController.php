<?php

namespace App\Http\Controllers;

use App\Models\Results;
use App\Jobs\GenerateAgeSimulations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SimulationController extends Controller
{
    /**
     * Display age simulation view
     * Triggers async generation if not already started
     */
    public function index()
    {
        try {
            $scanId = session('last_scan_id');

            if (!$scanId) {
                return redirect('/')
                    ->with('error', 'No scan found. Please scan a dog first.');
            }

            $result = Results::where('scan_id', $scanId)->firstOrFail();

            // Trigger generation if not started
            $this->ensureSimulationStarted($result);

            $viewData = $this->prepareViewData($result);

            return inertia('normal_user/view-simulation', $viewData);
        } catch (\Exception $e) {
            Log::error('Simulation view error', [
                'error' => $e->getMessage(),
                'scan_id' => $scanId ?? 'unknown'
            ]);

            return redirect('/')
                ->with('error', 'Unable to load simulation data.');
        }
    }

    /**
     * Ensure simulation job is queued
     */
    private function ensureSimulationStarted($result)
    {
        $simulationData = json_decode($result->simulation_data, true);
        $status = $simulationData['status'] ?? null;

        // Only dispatch if not already generating or complete
        if (!in_array($status, ['generating', 'complete'])) {
            Log::info("Dispatching simulation job for {$result->scan_id}");

            GenerateAgeSimulations::dispatch(
                $result->id,
                $result->breed,
                $result->image
            )->onQueue('simulations');

            // Update status immediately
            $result->update([
                'simulation_data' => json_encode([
                    'status' => 'queued',
                    '1_years' => null,
                    '3_years' => null
                ])
            ]);
        }
    }

    /**
     * Prepare view data with full URLs
     */
    private function prepareViewData($result)
    {
        $simulationData = json_decode($result->simulation_data, true) ?? [];
        $baseUrl = config('filesystems.disks.object-storage.url');

        // Build original image URL - handle both storage paths and full URLs
        $originalImageUrl = $this->buildImageUrl($result->image, $baseUrl);

        Log::info('Preparing simulation view data', [
            'scan_id' => $result->scan_id,
            'raw_image_path' => $result->image,
            'built_url' => $originalImageUrl,
            'base_url' => $baseUrl
        ]);

        return [
            'scan_id' => $result->scan_id,
            'breed' => $result->breed,
            'original_image' => $originalImageUrl,
            'simulations' => [
                '1_years' => $this->buildFullUrl($simulationData['1_years'] ?? null, $baseUrl),
                '3_years' => $this->buildFullUrl($simulationData['3_years'] ?? null, $baseUrl),
            ],
            'simulation_status' => $simulationData['status'] ?? 'pending',
            'breed_profile' => $simulationData['breed_profile'] ?? null,
            'error' => $simulationData['error'] ?? null,
        ];
    }

    /**
     * Build proper image URL from path
     */
    private function buildImageUrl($path, $baseUrl)
    {
        if (!$path) {
            Log::warning('buildImageUrl called with null/empty path');
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            Log::info('Image path is already full URL', ['url' => $path]);
            return $path;
        }

        // If it's an object storage path (contains 'uploads/' or 'simulations/')
        if (str_contains($path, 'uploads/') || str_contains($path, 'simulations/')) {
            $fullUrl = $baseUrl . '/' . $path;
            Log::info('Building object storage URL', [
                'path' => $path,
                'base_url' => $baseUrl,
                'full_url' => $fullUrl
            ]);
            return $fullUrl;
        }

        // Otherwise, assume it's a public storage path (for backward compatibility)
        $assetUrl = asset('storage/' . $path);
        Log::info('Building public storage URL', [
            'path' => $path,
            'asset_url' => $assetUrl
        ]);
        return $assetUrl;
    }

    /**
     * Build full URL from path (for simulations)
     */
    private function buildFullUrl($path, $baseUrl)
    {
        return $path ? $baseUrl . '/' . $path : null;
    }

    /**
     * Force regenerate simulations (optional endpoint)
     */
    public function regenerate(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');

            if (!$scanId) {
                return response()->json(['error' => 'No scan_id provided'], 400);
            }

            $result = Results::where('scan_id', $scanId)->firstOrFail();

            // Clear cache
            Cache::forget("sim_status_{$scanId}");

            // Reset and redispatch
            $result->update([
                'simulation_data' => json_encode([
                    'status' => 'queued',
                    '1_years' => null,
                    '3_years' => null
                ])
            ]);

            GenerateAgeSimulations::dispatch(
                $result->id,
                $result->breed,
                $result->image
            )->onQueue('simulations');

            return response()->json([
                'success' => true,
                'message' => 'Regeneration started'
            ]);
        } catch (\Exception $e) {
            Log::error('Regeneration error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to regenerate'], 500);
        }
    }

    /**
     * Check simulation status (for polling)
     */
    public function checkStatus(Request $request)
    {
        try {
            $scanId = $request->input('scan_id');

            if (!$scanId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No scan_id provided'
                ], 400);
            }

            $result = Results::where('scan_id', $scanId)->firstOrFail();
            $simulationData = json_decode($result->simulation_data, true) ?? [];
            $baseUrl = config('filesystems.disks.object-storage.url');

            // Build proper image URLs
            $originalImageUrl = $this->buildImageUrl($result->image, $baseUrl);

            return response()->json([
                'success' => true,
                'status' => $simulationData['status'] ?? 'pending',
                'original_image' => $originalImageUrl,
                'simulations' => [
                    '1_years' => $this->buildFullUrl($simulationData['1_years'] ?? null, $baseUrl),
                    '3_years' => $this->buildFullUrl($simulationData['3_years'] ?? null, $baseUrl),
                ],
                'timestamp' => now()->timestamp
            ]);
        } catch (\Exception $e) {
            Log::error('Status check error', [
                'error' => $e->getMessage(),
                'scan_id' => $request->input('scan_id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch status'
            ], 500);
        }
    }
}
