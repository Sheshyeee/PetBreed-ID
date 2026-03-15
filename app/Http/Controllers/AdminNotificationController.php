<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use Illuminate\Http\Request;
use App\Models\Results;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    /**
     * Return all appointment-response notifications for the admin.
     * Called by the bell icon every 30 seconds.
     */
    public function index()
    {
        $notifications = AdminNotification::latest()
            ->take(30)
            ->get()
            ->map(function ($n) {
                // Resolve the result's numeric id so the frontend can
                // navigate to /model/review-dog/{result_id}
                $result = Results::where('scan_id', $n->scan_id)
                    ->select('id')
                    ->first();

                return [
                    'id'               => $n->id,
                    'type'             => $n->type,
                    'message'          => $n->message,
                    'breed'            => $n->breed,
                    'scan_id'          => $n->scan_id,
                    'result_id'        => $result?->id,   // ← ADD THIS
                    'appointment_date' => $n->appointment_date,
                    'appointment_time' => $n->appointment_time,
                    'vet_name'         => $n->vet_name,
                    'rejection_reason' => $n->rejection_reason,
                    'is_read'          => (bool) $n->is_read,
                    'created_at'       => $n->created_at->toISOString(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => AdminNotification::where('is_read', false)->count(),
        ]);
    }

    /** Mark a single notification as read. */
    public function markRead($id)
    {
        AdminNotification::where('id', $id)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    /** Mark all notifications as read. */
    public function markAllRead()
    {
        AdminNotification::where('is_read', false)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }
}
