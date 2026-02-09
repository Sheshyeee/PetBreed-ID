<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResultController extends Controller
{
    public function index()
    {
        $lastResult = Results::where('scan_id', session('last_scan_id'))->first();

        if ($lastResult) {
            // Build full URL from object storage
            $baseUrl = config('filesystems.disks.object-storage.url');
            $lastResult->image = $baseUrl . '/' . $lastResult->image;
        }

        return inertia('normal_user/scan-results', [
            'results' => $lastResult
        ]);
    }
}
