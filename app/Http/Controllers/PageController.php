<?php

namespace App\Http\Controllers;

use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
                // Build URL manually
                $imageUrl = config('filesystems.disks.object-storage.url') . '/' . $scan->image;

                return [
                    'id' => $scan->id,
                    'scan_id' => $scan->scan_id,
                    'image' => $imageUrl,
                    'breed' => $scan->breed,
                    'confidence' => round($scan->confidence, 0),
                    'date' => $scan->created_at->format('M d, Y'),
                    'status' => $scan->pending,
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

    public function deleteScan($id)
    {
        $user = Auth::user();

        // Find the scan and ensure it belongs to the authenticated user
        $scan = Results::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$scan) {
            return back()->with('error', 'Scan not found or you do not have permission to delete it.');
        }

        try {
            // Delete the image from storage if it exists
            if ($scan->image && Storage::disk('object-storage')->exists($scan->image)) {
                Storage::disk('object-storage')->delete($scan->image);
            }

            // Delete the scan record
            $scan->delete();

            return back()->with('success', 'Scan deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete scan. Please try again.');
        }
    }
}
