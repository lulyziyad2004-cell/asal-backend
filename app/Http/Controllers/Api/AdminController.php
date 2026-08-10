<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LegalCase;
use App\Models\Hearing;
use App\Models\Document;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\AuditLog;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'total_users' => User::count(),
            'total_clients' => User::where('role', 'client')->count(),
            'total_lawyers' => User::where('role', 'lawyer')->count(),
            'total_consultants' => User::where('role', 'consultant')->count(),
            'open_cases' => LegalCase::where('status', 'open')->count(),
            'in_progress_cases' => LegalCase::where('status', 'in_progress')->count(),
            'total_documents' => Document::count(),
            'unread_messages' => Message::where('is_read', 'no')->count(),
            'total_revenue' => Transaction::where('status', 'captured')->sum('amount'),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $users = User::when($request->role, function ($q) use ($request) {
            return $q->where('role', $request->role);
        })->get();

        return response()->json($users);
    }

    public function disableUser($id, Request $request): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->update(['status' => 'suspended']);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role,
            'action' => 'user.disable',
            'target_type' => 'user',
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'User disabled successfully']);
    }

    public function setUserRole($id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:admin,lawyer,consultant,client',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->update(['role' => $request->role]);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role,
            'action' => 'user.role_change',
            'target_type' => 'user',
            'target_id' => $user->id,
            'details' => $request->role,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'User role updated successfully']);
    }

    public function suspendUser($id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'suspended' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->update(['status' => $request->suspended ? 'suspended' : 'active']);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role,
            'action' => $request->suspended ? 'user.suspend' : 'user.activate',
            'target_type' => 'user',
            'target_id' => $user->id,
            'details' => $request->suspended ? 'suspended' : 'active',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => $request->suspended ? 'User suspended successfully' : 'User activated successfully']);
    }

    public function deleteUser($id, Request $request): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($request->user()->id === $user->id) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }

        $user->delete();

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role,
            'action' => 'user.delete',
            'target_type' => 'user',
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::latest()->limit(100)->get();
        return response()->json($logs);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = Transaction::latest()->get();
        return response()->json($transactions);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $subscriptions = Subscription::latest()->get();
        return response()->json($subscriptions);
    }
}