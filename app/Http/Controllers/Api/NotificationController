<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(50)->get();
        return response()->json($notifications);
    }

    public function markRead($id, Request $request): JsonResponse
    {
        $notification = Notification::find($id);
        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        if ($notification->recipient_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->update(['is_read' => 'yes']);
        return response()->json(['message' => 'Notification marked as read']);
    }

    public function destroy($id, Request $request): JsonResponse
    {
        $notification = Notification::find($id);
        if (!$notification) {
            return response()->json(['error' => 'Notification not found'], 404);
        }

        if ($notification->recipient_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->delete();
        return response()->json(['message' => 'Notification deleted successfully']);
    }
}