<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimulationStatusController extends Controller
{
    /**
     * Get the current simulation status for the user's last scan
     */
    public function getStatus(Request $request)
    {
        try {
            $scanId = session('last_scan_id');

            Log::info("=== SIMULATION STATUS API CALLED ===");
            Log::info("Session scan_id: " . ($scanId ?? 'NULL'));
            Log::info("All session data: " . json_encode(session()->all()));

            if (!$scanId) {
                Log::warning("No scan_id in session");
                return response()->json([
                    'status' => 'failed',
                    'simulations' => [
                        '1_years' => null,
                        '3_years' => null,
                    ],
                    'message' => 'No scan found in session'
                ], 404);
            }

            $result = Results::where('scan_id', $scanId)->first();

            if (!$result) {
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

            // Decode simulation data
            $simulationData = json_decode($result->simulation_data, true);

            Log::info("Raw simulation_data from DB: " . $result->simulation_data);
            Log::info("Decoded simulation_data: " . json_encode($simulationData));

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
            Log::info("Object storage base URL: " . $baseUrl);

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

            Log::info("API Response - Status: {$status}");
            Log::info("API Response - 1_years path: " . ($simulationData['1_years'] ?? 'NULL'));
            Log::info("API Response - 1_years URL: " . ($simulations['1_years'] ?? 'NULL'));
            Log::info("API Response - 3_years path: " . ($simulationData['3_years'] ?? 'NULL'));
            Log::info("API Response - 3_years URL: " . ($simulations['3_years'] ?? 'NULL'));
            Log::info("API Response - original_image: {$originalImage}");

            // CRITICAL: Add timestamp to prevent caching
            $responseData = [
                'status' => $status,
                'simulations' => $simulations,
                'original_image' => $originalImage,
                'scan_id' => $scanId,
                'timestamp' => now()->timestamp, // Force fresh data
                'has_1_year' => !is_null($simulations['1_years']),
                'has_3_years' => !is_null($simulations['3_years']),
            ];

            Log::info("Final API Response: " . json_encode($responseData));

            return response()->json($responseData)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
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
