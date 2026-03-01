<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResultController extends Controller
{
    /**
     * Fallback: /scan-results with no ID — redirect to scan page.
     * (No longer used as primary route, but kept as safety fallback)
     */
    public function index()
    {
        $lastScanId = session('last_scan_id');

        if ($lastScanId) {
            return redirect('/scan-results/' . $lastScanId);
        }

        return redirect('/scan')->with('error', [
            'message' => 'No recent scan found. Please scan a dog first.'
        ]);
    }

    /**
     * Show a specific scan result by scan_id.
     * Called after every successful scan via /scan-results/{scan_id}
     */
    public function show($scan_id)
    {
        $result = Results::where('scan_id', $scan_id)->first();

        if (!$result) {
            return redirect('/scan')->with('error', [
                'message' => 'Scan result not found. Please try scanning again.'
            ]);
        }

        // Build full image URL from object storage
        $baseUrl = rtrim(config('filesystems.disks.object-storage.url'), '/');
        $result->image = $baseUrl . '/' . $result->image;

        // Decode JSON fields so frontend gets arrays, not raw strings
        $result->top_predictions = is_string($result->top_predictions)
            ? json_decode($result->top_predictions, true)
            : ($result->top_predictions ?? []);

        $result->origin_history = is_string($result->origin_history)
            ? json_decode($result->origin_history, true)
            : $result->origin_history;

        $result->health_risks = is_string($result->health_risks)
            ? json_decode($result->health_risks, true)
            : $result->health_risks;

        return inertia('normal_user/scan-results', [
            'results' => $result,
        ]);
    }
}