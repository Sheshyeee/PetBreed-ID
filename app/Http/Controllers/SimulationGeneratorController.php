<?php

namespace App\Http\Controllers;

use App\Models\Results;
use App\Services\AgeSimulationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimulationGeneratorController extends Controller
{
    /**
     * Generate simulations asynchronously via API call
     * This is called AFTER the initial scan completes
     */
    public function generate(Request $request)
    {
        try {
            $scanId = $request->input('scan_id');

            if (!$scanId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing scan_id'
                ], 400);
            }

            $result = Results::where('scan_id', $scanId)->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scan not found'
                ], 404);
            }

            // Check if already generated
            $simulationData = json_decode($result->simulation_data, true) ?? [];

            if (isset($simulationData['status']) && $simulationData['status'] === 'complete') {
                return response()->json([
                    'success' => true,
                    'status' => 'complete',
                    'simulations' => [
                        '1_years' => $simulationData['1_years'] ?? null,
                        '3_years' => $simulationData['3_years'] ?? null,
                    ]
                ]);
            }

            // Mark as generating
            $simulationData = [
                '1_years' => null,
                '3_years' => null,
                'status' => 'generating'
            ];
            $result->update(['simulation_data' => json_encode($simulationData)]);

            // Extract dog features from simulation_data
            $dogFeatures = $simulationData['dog_features'] ?? [];

            // Generate simulations
            $simulationService = new AgeSimulationService();
            $generatedData = $simulationService->generateSimulations($result->breed, $dogFeatures);

            // Update database
            $result->update(['simulation_data' => json_encode($generatedData)]);

            return response()->json([
                'success' => true,
                'status' => $generatedData['status'],
                'simulations' => [
                    '1_years' => $generatedData['1_years'] ?? null,
                    '3_years' => $generatedData['3_years'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Simulation generation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => 'Failed to generate simulations'
            ], 500);
        }
    }

    /**
     * Check simulation status
     */
    public function status(Request $request)
    {
        try {
            $scanId = $request->input('scan_id') ?? session('last_scan_id');

            if (!$scanId) {
                return response()->json(['error' => 'No scan ID provided'], 400);
            }

            $result = Results::where('scan_id', $scanId)->first();

            if (!$result) {
                return response()->json(['error' => 'Scan not found'], 404);
            }

            $simulationData = json_decode($result->simulation_data, true) ?? [];

            return response()->json([
                'success' => true,
                'status' => $simulationData['status'] ?? 'pending',
                'simulations' => [
                    '1_years' => $simulationData['1_years'] ?? null,
                    '3_years' => $simulationData['3_years'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Simulation status check error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to check status'], 500);
        }
    }
}
