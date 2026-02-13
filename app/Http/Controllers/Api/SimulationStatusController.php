<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SimulationStatusController extends Controller
{
    public function getStatus(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');
            if (!$scanId) return $this->errorResponse('No scan_id', 404);

            $cacheKey = "sim_status_{$scanId}";
            $responseData = Cache::get($cacheKey);

            if (!$responseData) {
                $result = Results::where('scan_id', $scanId)->first();
                if (!$result) return $this->errorResponse('Scan not found', 404);

                $responseData = $this->buildResponse($result, $scanId);

                $cacheTTL = match ($responseData['status']) {
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
            Log::error('API status error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Server error', 500);
        }
    }

    private function buildResponse($result, $scanId)
    {
        $simulationData = json_decode($result->simulation_data, true) ?? ['1_years' => null, '3_years' => null, 'status' => 'pending'];
        $baseUrl = config('filesystems.disks.object-storage.url');

        if (!$result->image) {
            Log::error('❌ API: NO IMAGE', ['scan_id' => $scanId, 'result' => $result->toArray()]);
        }

        $originalImageUrl = $result->image ? ($baseUrl . '/' . $result->image) : null;

        Log::info('🖼️ API Response', [
            'scan_id' => $scanId,
            'image' => $result->image,
            'url' => $originalImageUrl,
            'null?' => $originalImageUrl === null ? 'YES!' : 'NO'
        ]);

        return [
            'status' => $simulationData['status'] ?? 'pending',
            'simulations' => [
                '1_years' => $this->buildUrl($simulationData['1_years'] ?? null, $baseUrl),
                '3_years' => $this->buildUrl($simulationData['3_years'] ?? null, $baseUrl),
            ],
            'original_image' => $originalImageUrl,
            'breed' => $result->breed,
            'scan_id' => $scanId,
            'timestamp' => now()->timestamp,
            'progress' => $this->calculateProgress($simulationData),
            'breed_profile' => $simulationData['breed_profile'] ?? null,
        ];
    }

    private function buildUrl($path, $baseUrl)
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return $baseUrl . '/' . $path;
    }

    private function calculateProgress($data)
    {
        $completed = 0;
        if ($data['1_years'] ?? null) $completed++;
        if ($data['3_years'] ?? null) $completed++;
        return ['completed' => $completed, 'total' => 2, 'percentage' => ($completed / 2) * 100];
    }

    private function errorResponse($message, $code)
    {
        return response()->json([
            'status' => 'failed',
            'simulations' => ['1_years' => null, '3_years' => null],
            'message' => $message
        ], $code);
    }
}
