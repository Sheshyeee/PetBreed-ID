<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
use App\Models\BreedCorrection;
use App\Models\Results;
use Illuminate\Http\Request;

class TrainingQueueController extends Controller
{
    public function index()
    {
        $correctedScanIds = BreedCorrection::pluck('scan_id');

        // 1. Get the actual "To Do" list (maybe just the recent 6 for display)
        $recentPendingResults = Results::whereNotIn('scan_id', $correctedScanIds)
            ->latest()
            ->take(6)
            ->get();

        // 2. Get the TOTAL count of pending items (The number you want in the stat card)
        $totalPendingCount = Results::whereNotIn('scan_id', $correctedScanIds)->count();

        // 3. Get history
        $corrections = BreedCorrection::latest()->get();

        $stats = [
            'pending' => $totalPendingCount, // <--- Correct Total Count
            'added' => $corrections->count()
        ];

        return inertia('model/training-queue', [
            'results' => $recentPendingResults,
            'corrections' => $corrections,
            'stats' => $stats
        ]);
    }
}
