<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use Illuminate\Http\Request;
use App\Models\Results;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::latest()
            ->take(30)
            ->get()
            ->map(function ($n) {
                $result = Results::where('scan_id', $n->scan_id)
                    ->select('id')
                    ->first();

                return [
                    'id'               => $n->id,
                    'type'             => $n->type,
                    'message'          => $n->message,
                    'breed'            => $n->breed,
                    'scan_id'          => $n->scan_id,
                    'result_id'        => $result?->id,
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

    public function markRead($id)
    {
        AdminNotification::where('id', $id)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        AdminNotification::where('is_read', false)->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    /** Delete a single notification. */
    public function destroy($id)
    {
        AdminNotification::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
