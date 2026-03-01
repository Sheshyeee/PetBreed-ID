<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    public function index()
    {
        // ── Top 5 most-scanned breeds (from the Results table) ──────────────
        $topBreeds = Results::selectRaw('breed, COUNT(*) as scan_count, ROUND(AVG(confidence), 1) as avg_confidence')
            ->whereNotNull('breed')
            ->where('breed', '!=', '')
            ->groupBy('breed')
            ->orderByDesc('scan_count')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'breed'          => $r->breed,
                'scan_count'     => (int) $r->scan_count,
                'avg_confidence' => (float) $r->avg_confidence,
            ])
            ->toArray();

        // Normalise bar widths relative to the top breed
        $maxCount = $topBreeds[0]['scan_count'] ?? 1;
        foreach ($topBreeds as &$b) {
            $b['bar_width'] = round(($b['scan_count'] / $maxCount) * 100);
        }
        unset($b);

        // ── Global statistics ────────────────────────────────────────────────
        $allResults   = Results::all();
        $totalScans   = $allResults->count();
        $verifiedCount = Results::where('pending', 'verified')->count();
        $avgConfidence = $totalScans > 0
            ? round($allResults->avg('confidence'), 1)
            : 0;

        $globalStats = [
            'total_scans'    => number_format($totalScans),
            'verified'       => number_format($verifiedCount),
            'avg_score'      => $avgConfidence . '%',
            'uptime'         => '99.9%',           // static / from monitoring
        ];

        return inertia('normal_user/scan', [
            'topBreeds'   => $topBreeds,
            'globalStats' => $globalStats,
        ]);
    }
}
