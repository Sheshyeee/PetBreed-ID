<?php

namespace App\Http\Controllers;

use App\Models\Results;
use App\Jobs\GenerateAgeSimulations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SimulationController extends Controller
{
    public function index()
    {
        try {
            $scanId = session('last_scan_id');

            if (!$scanId) {
                return redirect('/')->with('error', 'No scan found. Please scan a dog first.');
            }

            $result = Results::where('scan_id', $scanId)->firstOrFail();

            $this->ensureSimulationStarted($result);

            $viewData = $this->prepareViewData($result);

            return inertia('normal_user/view-simulation', $viewData);
        } catch (\Exception $e) {
            Log::error('Simulation view error', ['error' => $e->getMessage(), 'scan_id' => $scanId ?? 'unknown']);
            return redirect('/')->with('error', 'Unable to load simulation data.');
        }
    }

    private function ensureSimulationStarted($result)
    {
        $simulationData = json_decode($result->simulation_data, true);
        $status = $simulationData['status'] ?? null;

        if (!in_array($status, ['generating', 'complete'])) {
            Log::info("🚀 Dispatching transformation for {$result->scan_id}");

            GenerateAgeSimulations::dispatch($result->id, $result->breed, $result->image)->onQueue('simulations');

            $result->update(['simulation_data' => json_encode(['status' => 'queued', '1_years' => null, '3_years' => null])]);
        }
    }

    private function prepareViewData($result)
    {
        $simulationData = json_decode($result->simulation_data, true) ?? [];
        $baseUrl = config('filesystems.disks.object-storage.url');

        if (!$result->image) {
            Log::error('❌ NO IMAGE PATH', ['scan_id' => $result->scan_id, 'result' => $result->toArray()]);
        }

        $originalImageUrl = $result->image ? ($baseUrl . '/' . $result->image) : null;

        Log::info('🖼️ View Data', [
            'scan_id' => $result->scan_id,
            'image_db' => $result->image,
            'url' => $originalImageUrl,
            'null?' => $originalImageUrl === null ? 'YES!' : 'NO'
        ]);

        return [
            'scan_id' => $result->scan_id,
            'breed' => $result->breed,
            'original_image' => $originalImageUrl,
            'simulations' => [
                '1_years' => $this->buildUrl($simulationData['1_years'] ?? null, $baseUrl),
                '3_years' => $this->buildUrl($simulationData['3_years'] ?? null, $baseUrl),
            ],
            'simulation_status' => $simulationData['status'] ?? 'pending',
            'breed_profile' => $simulationData['breed_profile'] ?? null,
            'error' => $simulationData['error'] ?? null,
        ];
    }

    private function buildUrl($path, $baseUrl)
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return $baseUrl . '/' . $path;
    }

    public function regenerate(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');
            if (!$scanId) return response()->json(['error' => 'No scan_id'], 400);

            $result = Results::where('scan_id', $scanId)->firstOrFail();
            Cache::forget("sim_status_{$scanId}");
            $result->update(['simulation_data' => json_encode(['status' => 'queued', '1_years' => null, '3_years' => null])]);
            GenerateAgeSimulations::dispatch($result->id, $result->breed, $result->image)->onQueue('simulations');

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Regen error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function checkStatus(Request $request)
    {
        try {
            $scanId = $request->input('scan_id');
            if (!$scanId) return response()->json(['success' => false, 'error' => 'No scan_id'], 400);

            $result = Results::where('scan_id', $scanId)->firstOrFail();
            $simulationData = json_decode($result->simulation_data, true) ?? [];
            $baseUrl = config('filesystems.disks.object-storage.url');
            $originalImageUrl = $result->image ? ($baseUrl . '/' . $result->image) : null;

            return response()->json([
                'success' => true,
                'status' => $simulationData['status'] ?? 'pending',
                'original_image' => $originalImageUrl,
                'simulations' => [
                    '1_years' => $this->buildUrl($simulationData['1_years'] ?? null, $baseUrl),
                    '3_years' => $this->buildUrl($simulationData['3_years'] ?? null, $baseUrl),
                ],
                'timestamp' => now()->timestamp
            ]);
        } catch (\Exception $e) {
            Log::error('Status error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed'], 500);
        }
    }
}
