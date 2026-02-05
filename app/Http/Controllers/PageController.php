<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    public function header()
    {
        return inertia('normal_user/header');
    }

    public function scanhistory()
    {
        $user = Auth::user();

        $scans = Results::where('user_id', $user->id)
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($scan) {
                return [
                    'id' => $scan->id,
                    'scan_id' => $scan->scan_id,
                    'image' => asset('storage/' . $scan->image),
                    'breed' => $scan->breed,
                    'confidence' => round($scan->confidence, 0),
                    'date' => $scan->created_at->format('M d, Y'),
                    'status' => $scan->pending, // ✓ This is correct
                ];
            });

        return inertia('normal_user/scan-history', [
            'mockScans' => $scans,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ]
        ]);
    }
}
