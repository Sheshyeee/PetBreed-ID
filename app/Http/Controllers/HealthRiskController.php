<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Results; // Ensure you import your model
use Inertia\Inertia;

class HealthRiskController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get the Scan ID from the URL query (?id=...) or fallback to the session
        $scanId = $request->query('id') ?? session('last_scan_id');

        if (!$scanId) {
            // If no ID is found, redirect back to home or scan page
            return redirect('/')->with('error', 'No scan selected.');
        }

        // 2. Fetch the record from the database
        $result = Results::where('scan_id', $scanId)->firstOrFail();

        // 3. Return the view with the fetched data
        return Inertia::render('normal_user/view-health-risk', [
            'results' => $result
        ]);
    }
}
