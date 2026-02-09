<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimulationController extends Controller
{
    /**
     * Display the age simulation view
     * Simulations are generated asynchronously when the page loads
     */
    public function index()
    {
        try {
            $scanId = session('last_scan_id');

            if (!$scanId) {
                return redirect('/')->with('error', 'No scan found. Please scan a dog first.');
            }

            $result = Results::where('scan_id', $scanId)->firstOrFail();

            $simulationData = json_decode($result->simulation_data, true) ?? [];

            // Build base URL from object storage
            $baseUrl = config('filesystems.disks.object-storage.url');

            $viewData = [
                'scan_id' => $result->scan_id,
                'breed' => $result->breed,
                'originalImage' => $baseUrl . '/' . $result->image, // Build full URL
                'simulations' => [
                    '1_years' => $simulationData['1_years']
                        ? $baseUrl . '/' . $simulationData['1_years']
                        : null,
                    '3_years' => $simulationData['3_years']
                        ? $baseUrl . '/' . $simulationData['3_years']
                        : null,
                ],
                'simulation_status' => $simulationData['status'] ?? 'pending',
            ];

            return inertia('normal_user/view-simulation', $viewData);
        } catch (\Exception $e) {
            Log::error('Simulation view error: ' . $e->getMessage());
            return redirect('/')->with('error', 'Unable to load simulation data.');
        }
    }
}
