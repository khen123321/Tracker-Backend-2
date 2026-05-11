<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Fetch all notifications for the authenticated user.
     */
    public function index()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // ✨ THE FIX: Laravel automatically knows to check notifiable_id and notifiable_type!
            // It also automatically sorts them by newest first.
            $notifications = $user->notifications;

            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            
            // Find the notification specifically belonging to this user
            $notification = $user->notifications()->findOrFail($id);
            
            // ✨ THE FIX: Use Laravel's built-in markAsRead helper
            $notification->markAsRead();

            return response()->json(['message' => 'Marked as read']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Notification not found'], 404);
        }
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            
            // ✨ THE FIX: One line replaces the whole manual update query!
            $user->unreadNotifications->markAsRead();

            return response()->json(['message' => 'All marked as read']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}