<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index()
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        // Build base URL from object storage
        $baseUrl = config('filesystems.disks.object-storage.url');

        // Transform notifications to include full image URLs
        $notifications->getCollection()->transform(function ($notification) use ($baseUrl) {
            // Decode data JSON if it's a string
            $data = is_string($notification->data)
                ? json_decode($notification->data, true)
                : $notification->data;

            // If there's an image path in data, prepend base URL
            if (isset($data['image']) && !empty($data['image'])) {
                // Check if it's already a full URL
                if (!str_starts_with($data['image'], 'http://') && !str_starts_with($data['image'], 'https://')) {
                    $data['image'] = $baseUrl . '/' . $data['image'];
                }
            }

            // Update notification data with full URL
            $notification->data = $data;

            return $notification;
        });

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount()
    {
        $user = Auth::user();

        $count = Notification::where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('read', false)
                    ->orWhereNull('read');
            })
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        try {
            Log::info('Marking notification as read', [
                'notification_id' => $id,
                'user_id' => Auth::id()
            ]);

            $notification = Notification::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$notification) {
                Log::error('Notification not found or unauthorized', [
                    'notification_id' => $id,
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            Log::info('Before update', [
                'notification_id' => $notification->id,
                'read' => $notification->read,
                'read_at' => $notification->read_at
            ]);

            // Direct update instead of using method
            $notification->read = true;
            $notification->read_at = now();
            $saved = $notification->save();

            Log::info('After update', [
                'notification_id' => $notification->id,
                'read' => $notification->read,
                'read_at' => $notification->read_at,
                'save_result' => $saved
            ]);

            // Refresh to get latest data
            $notification->refresh();

            Log::info('After refresh', [
                'notification_id' => $notification->id,
                'read' => $notification->read,
                'read_at' => $notification->read_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'notification' => [
                    'id' => $notification->id,
                    'read' => $notification->read,
                    'read_at' => $notification->read_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            $userId = Auth::id();

            Log::info('Marking all notifications as read for user', [
                'user_id' => $userId
            ]);

            $updated = Notification::where('user_id', $userId)
                ->where(function ($query) {
                    $query->where('read', false)
                        ->orWhereNull('read');
                })
                ->update([
                    'read' => true,
                    'read_at' => now()
                ]);

            Log::info('Notifications marked as read', [
                'count' => $updated
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'count' => $updated
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy($id)
    {
        try {
            $notification = Notification::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting notification', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }
}
