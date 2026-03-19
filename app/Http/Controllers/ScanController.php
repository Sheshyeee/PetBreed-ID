<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    public function index()
    {
        // ── Top 5 most-scanned breeds ────────────────────────────────────────
        $topBreeds = Results::selectRaw('breed, COUNT(*) as scan_count, ROUND(CAST(AVG(confidence) AS numeric), 1) as avg_confidence')
            ->whereNotNull('breed')
            ->where('breed', '!=', '')
            ->groupBy('breed')
            ->orderByDesc('scan_count')
            ->limit(3)
            ->get()
            ->map(fn($r) => [
                'breed'          => $r->breed,
                'scan_count'     => (int) $r->scan_count,
                'avg_confidence' => (float) $r->avg_confidence,
            ])
            ->toArray();

        $maxCount = $topBreeds[0]['scan_count'] ?? 1;
        foreach ($topBreeds as &$b) {
            $b['bar_width'] = round(($b['scan_count'] / $maxCount) * 100);
        }
        unset($b);

        // ── Global statistics ────────────────────────────────────────────────
        $allResults    = Results::all();
        $totalScans    = $allResults->count();
        $verifiedCount = Results::where('pending', 'verified')->count();
        $avgConfidence = $totalScans > 0 ? round($allResults->avg('confidence'), 1) : 0;

        $globalStats = [
            'total_scans' => number_format($totalScans),
            'verified'    => number_format($verifiedCount),
            'avg_score'   => $avgConfidence . '%',
            'uptime'      => '99.9%',
        ];

        // ── Pending appointments for the logged-in user ──────────────────────
        $userId = Auth::id();

        $pendingAppointments = Appointment::where('user_id', $userId)
            ->where('status', 'pending')
            ->with('result:id,scan_id,breed')
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'breed'            => optional($a->result)->breed ?? 'your dog',
                'appointment_date' => $a->appointment_date instanceof \Carbon\Carbon
                    ? $a->appointment_date->format('M d, Y')
                    : $a->appointment_date,
                'appointment_time' => $a->appointment_time,
                'vet_name'         => $a->vet_name,
                'reason'           => $a->reason,
            ])
            ->values()
            ->toArray();

        return inertia('normal_user/scan', [
            'topBreeds'           => $topBreeds,
            'globalStats'         => $globalStats,
            'pendingAppointments' => $pendingAppointments,
        ]);
    }
}
