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

        if (!in_array($status, ['generating', 'complete'])) {
            Log::info("🚀 Dispatching world-class transformation for {$result->scan_id}");

            GenerateAgeSimulations::dispatch(
                $result->id,
                $result->breed,
                $result->image
            )->onQueue('simulations');

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
     * Prepare view data - FIXED to match ResultController pattern EXACTLY
     */
    private function prepareViewData($result)
    {
        $simulationData = json_decode($result->simulation_data, true) ?? [];
        $baseUrl = config('filesystems.disks.object-storage.url');

        // CRITICAL FIX: Build URL EXACTLY like ResultController does
        $originalImageUrl = $baseUrl . '/' . $result->image;

        Log::info('🖼️ Simulation View Data (matching ResultController pattern)', [
            'scan_id' => $result->scan_id,
            'result_image_path' => $result->image,
            'base_url' => $baseUrl,
            'final_url' => $originalImageUrl,
        ]);

        return [
            'scan_id' => $result->scan_id,
            'breed' => $result->breed,
            'original_image' => $originalImageUrl, // Now matches ResultController exactly
            'simulations' => [
                '1_years' => $this->buildSimulationUrl($simulationData['1_years'] ?? null, $baseUrl),
                '3_years' => $this->buildSimulationUrl($simulationData['3_years'] ?? null, $baseUrl),
            ],
            'simulation_status' => $simulationData['status'] ?? 'pending',
            'breed_profile' => $simulationData['breed_profile'] ?? null,
            'error' => $simulationData['error'] ?? null,
        ];
    }

    /**
     * Build simulation image URL
     */
    private function buildSimulationUrl($path, $baseUrl)
    {
        if (!$path) {
            return null;
        }

        // If already full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $baseUrl . '/' . $path;
    }

    /**
     * Force regenerate simulations
     */
    public function regenerate(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');

            if (!$scanId) {
                return response()->json(['error' => 'No scan_id provided'], 400);
            }

            $result = Results::where('scan_id', $scanId)->firstOrFail();

            Cache::forget("sim_status_{$scanId}");
            Cache::forget("simulation_status_{$scanId}");

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

            // Match ResultController pattern
            $originalImageUrl = $baseUrl . '/' . $result->image;

            return response()->json([
                'success' => true,
                'status' => $simulationData['status'] ?? 'pending',
                'original_image' => $originalImageUrl,
                'simulations' => [
                    '1_years' => $this->buildSimulationUrl($simulationData['1_years'] ?? null, $baseUrl),
                    '3_years' => $this->buildSimulationUrl($simulationData['3_years'] ?? null, $baseUrl),
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
