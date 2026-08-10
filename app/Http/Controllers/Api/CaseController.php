<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = LegalCase::query();

        if ($user->role === 'admin') {
            // Admins see all cases
        } elseif ($user->role === 'lawyer') {
            $query->where('lawyer_id', $user->id);
        } elseif ($user->role === 'consultant') {
            $query->where('consultant_id', $user->id);
        } else {
            $query->where('client_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function show($id, Request $request): JsonResponse
    {
        $case = LegalCase::find($id);
        if (!$case) {
            return response()->json(['error' => 'Case not found'], 404);
        }

        $user = $request->user();
        if ($user->role !== 'admin' && $case->client_id !== $user->id && $case->lawyer_id !== $user->id && $case->consultant_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($case->load(['client', 'lawyer', 'consultant', 'hearings', 'invoices', 'documents']));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'lawyer', 'consultant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:2|max:255',
            'description' => 'nullable|string|max:5000',
            'client_id' => 'required|exists:users,id',
            'court' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:128',
            'circuit_number' => 'nullable|string|max:64',
            'category' => 'nullable|in:criminal,commercial,civil,labor,family,corporate_governance,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $caseNumber = 'ASL-' . date('Y') . '-' . Str::upper(Str::random(8));

        $case = LegalCase::create([
            'case_number' => $caseNumber,
            'title' => $request->title,
            'description' => $request->description,
            'client_id' => $request->client_id,
            'court' => $request->court,
            'city' => $request->city,
            'circuit_number' => $request->circuit_number,
            'category' => $request->category ?? 'other',
            'status' => 'open',
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'case.create',
            'target_type' => 'case',
            'target_id' => $case->id,
            'details' => $request->title,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['id' => $case->id, 'case_number' => $caseNumber], 201);
    }

    public function update($id, Request $request): JsonResponse
    {
        $case = LegalCase::find($id);
        if (!$case) {
            return response()->json(['error' => 'Case not found'], 404);
        }

        $user = $request->user();
        if (!in_array($user->role, ['admin', 'lawyer', 'consultant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:open,in_progress,closed,cancelled',
            'title' => 'nullable|string|min:2|max:255',
            'lawyer_id' => 'nullable|exists:users,id',
            'consultant_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $case->update($request->only(['status', 'title', 'lawyer_id', 'consultant_id']));

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'case.update',
            'target_type' => 'case',
            'target_id' => $case->id,
            'details' => json_encode($request->only(['status', 'title', 'lawyer_id', 'consultant_id'])),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Case updated successfully']);
    }
}
