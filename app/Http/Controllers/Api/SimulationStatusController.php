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
     * Get the current simulation status
     * SIMPLE OPTIMIZATION: Just add 5-second cache to reduce DB load
     */
    public function getStatus(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');

            Log::info("=== SIMULATION STATUS API CALLED ===");
            Log::info("Request scan_id: " . ($request->input('scan_id') ?? 'NULL'));
            Log::info("Session scan_id: " . (session('last_scan_id') ?? 'NULL'));
            Log::info("Using scan_id: " . ($scanId ?? 'NULL'));

            if (!$scanId) {
                Log::warning("No scan_id in request or session");
                return response()->json([
                    'status' => 'failed',
                    'simulations' => [
                        '1_years' => null,
                        '3_years' => null,
                    ],
                    'message' => 'No scan_id provided'
                ], 404);
            }

            // OPTIMIZATION: Cache the result for 5 seconds to reduce DB queries
            // This helps when frontend is polling every 2 seconds
            $cacheKey = "simulation_status_{$scanId}";

            $responseData = Cache::remember($cacheKey, 5, function () use ($scanId) {
                $result = Results::where('scan_id', $scanId)->first();

                if (!$result) {
                    return null; // Will be handled below
                }

                // Decode simulation data
                $simulationData = json_decode($result->simulation_data, true);

                Log::info("Raw simulation_data from DB: " . $result->simulation_data);

                // Ensure proper structure
                if (!$simulationData || !is_array($simulationData)) {
                    Log::warning("Invalid simulation_data, using defaults");
                    $simulationData = [
                        '1_years' => null,
                        '3_years' => null,
                        'status' => 'pending',
                    ];
                }

                // Build base URL from object storage
                $baseUrl = config('filesystems.disks.object-storage.url');

                // Extract status and simulations WITH FULL URLS
                $status = $simulationData['status'] ?? 'pending';
                $simulations = [
                    '1_years' => $simulationData['1_years']
                        ? $baseUrl . '/' . $simulationData['1_years']
                        : null,
                    '3_years' => $simulationData['3_years']
                        ? $baseUrl . '/' . $simulationData['3_years']
                        : null,
                ];

                // Also include original image with full URL
                $originalImage = $baseUrl . '/' . $result->image;

                Log::info("✓ Returning status: {$status}");
                Log::info("✓ 1_years: " . ($simulations['1_years'] ? 'EXISTS' : 'NULL'));
                Log::info("✓ 3_years: " . ($simulations['3_years'] ? 'EXISTS' : 'NULL'));

                return [
                    'status' => $status,
                    'simulations' => $simulations,
                    'original_image' => $originalImage,
                    'scan_id' => $scanId,
                    'timestamp' => now()->timestamp,
                    'has_1_year' => !is_null($simulations['1_years']),
                    'has_3_years' => !is_null($simulations['3_years']),
                ];
            });

            if (!$responseData) {
                Log::error("Result not found for scan_id: {$scanId}");
                return response()->json([
                    'status' => 'failed',
                    'simulations' => [
                        '1_years' => null,
                        '3_years' => null,
                    ],
                    'message' => 'Scan not found'
                ], 404);
            }

            return response()->json($responseData)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Simulation status API error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'failed',
                'simulations' => [
                    '1_years' => null,
                    '3_years' => null,
                ],
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
