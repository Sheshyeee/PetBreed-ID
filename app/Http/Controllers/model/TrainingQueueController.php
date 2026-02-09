<?php

namespace App\Http\Controllers\model;

use App\Http\Controllers\Controller;
use App\Models\BreedCorrection;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        // 3. Get history with proper image URLs
        $corrections = BreedCorrection::latest()->paginate(10);

        // Build base URL from object storage
        $baseUrl = config('filesystems.disks.object-storage.url');

        // Transform corrections to include full image URLs
        $corrections->getCollection()->transform(function ($correction) use ($baseUrl) {
            $correction->image_path = $baseUrl . '/' . $correction->image_path;
            return $correction;
        });

        $stats = [
            'pending' => $totalPendingCount,
            'added' => BreedCorrection::count() // Use total count, not paginated count
        ];

        return inertia('model/training-queue', [
            'results' => $recentPendingResults,
            'corrections' => $corrections,
            'stats' => $stats
        ]);
    }
}
