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

        $recentPendingResults = Results::whereNotIn('scan_id', $correctedScanIds)
            ->latest()
            ->take(6)
            ->get();

        $totalPendingCount = Results::whereNotIn('scan_id', $correctedScanIds)->count();

        $corrections = BreedCorrection::latest()->paginate(10);

        $baseUrl = config('filesystems.disks.object-storage.url');

        $corrections->getCollection()->transform(function ($correction) use ($baseUrl) {
    $correction->image_path = $baseUrl . '/' . $correction->image_path;

    $result = Results::where('scan_id', $correction->scan_id)
        ->select('id')
        ->first();
    $correction->result_id = $result?->id;

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
