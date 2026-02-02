<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Results;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        // Get ID from URL or Session
        $scanId = $request->query('id') ?? session('last_scan_id');

        if (!$scanId) {
            return redirect('/')->with('error', 'No scan found.');
        }

        $result = Results::where('scan_id', $scanId)->firstOrFail();

        return Inertia::render('normal_user/view-origin', [
            'results' => $result
        ]);
    }
}
