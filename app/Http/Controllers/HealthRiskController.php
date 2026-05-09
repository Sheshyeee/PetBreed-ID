<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Results; 
use Inertia\Inertia;

class HealthRiskController extends Controller
{
    public function index(Request $request)
    {
        $scanId = $request->query('id') ?? session('last_scan_id');

        if (!$scanId) {
            return redirect('/')->with('error', 'No scan selected.');
        }

        $result = Results::where('scan_id', $scanId)->firstOrFail();

        return Inertia::render('normal_user/view-health-risk', [
        'results' => $result
        ]);
    }
}
