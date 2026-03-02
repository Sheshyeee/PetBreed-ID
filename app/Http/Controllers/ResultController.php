<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ResultController extends Controller
{
    public function index()
    {
        $lastResult = Results::where('scan_id', session('last_scan_id'))->first();

        if ($lastResult) {
            $baseUrl = config('filesystems.disks.object-storage.url');
            $lastResult->image = $baseUrl . '/' . $lastResult->image;
        }

        return inertia('normal_user/scan-results', [
            'results' => $lastResult
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();

        $result = Results::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $baseUrl = config('filesystems.disks.object-storage.url');
        $result->image = $baseUrl . '/' . $result->image;

        return inertia('normal_user/scan-results', [
            'results' => $result
        ]);
    }
}
