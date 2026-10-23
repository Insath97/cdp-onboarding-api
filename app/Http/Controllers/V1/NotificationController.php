<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Notification Index', ['index', 'unreadCount']),
            new Middleware('permission:Notification Mark Read', ['markAsRead', 'markAllAsRead', 'destroy']),
        ];
    }

    /**
     * Display a paginated list of notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = (int) $request->get('per_page', 15);

            $query = $request->boolean('unread_only')
                ? $user->unreadNotifications()
                : $user->notifications();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('data', 'like', "%{$search}%");
            }

            $notifications = $query->paginate($perPage);
            $unreadCount   = $user->unreadNotifications()->count();

            $this->logActivity(
                'GET_NOTIFICATIONS',
                'Notification',
                'Retrieved user notifications list',
                ['unread_only' => $request->boolean('unread_only'), 'unread_count' => $unreadCount]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Notifications retrieved successfully',
                'data'    => [
                    'unread_count'  => $unreadCount,
                    'notifications' => $notifications,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve notifications',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get unread notification count for the badge counter.
     */
    public function unreadCount()
    {
        try {
            $unreadCount = auth()->user()->unreadNotifications()->count();

            return response()->json([
                'status'  => 'success',
                'message' => 'Unread notifications count retrieved',
                'data'    => [
                    'unread_count' => $unreadCount,
                ],
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve unread notifications count',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id)
    {
        try {
            $notification = auth()->user()->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->markAsRead();

            $this->logActivity(
                'MARK_NOTIFICATION_READ',
                'Notification',
                "Marked notification ID {$id} as read",
                ['notification_id' => $id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Notification marked as read successfully',
                'data'    => $notification->fresh(),
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to mark notification as read',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark all unread notifications for the authenticated user as read.
     */
    public function markAllAsRead()
    {
        try {
            $user = auth()->user();
            $user->unreadNotifications->markAsRead();

            $this->logActivity(
                'MARK_ALL_NOTIFICATIONS_READ',
                'Notification',
                'Marked all unread notifications as read',
                ['user_id' => $user->id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'All notifications marked as read successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to mark all notifications as read',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a notification.
     */
    public function destroy(string $id)
    {
        try {
            $notification = auth()->user()->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->delete();

            $this->logActivity(
                'DELETE_NOTIFICATION',
                'Notification',
                "Deleted notification ID {$id}",
                ['notification_id' => $id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Notification deleted successfully',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete notification',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
