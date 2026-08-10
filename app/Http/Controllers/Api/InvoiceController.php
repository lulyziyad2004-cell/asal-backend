<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Invoice::query();

        if ($user->role !== 'admin') {
            $query->where('client_id', $user->id);
        }

        return response()->json($query->latest()->get());
    }

    public function show($id, Request $request): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $user = $request->user();
        if ($user->role !== 'admin' && $invoice->client_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($invoice->load(['client', 'case', 'transactions']));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'lawyer'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'case_id' => 'nullable|exists:cases,id',
            'title' => 'required|string|min:2|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:8',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoiceNumber = 'INV-' . date('Y') . '-' . Str::upper(Str::random(8));

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'client_id' => $request->client_id,
            'case_id' => $request->case_id,
            'title' => $request->title,
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'SAR',
            'due_date' => $request->due_date,
            'status' => 'draft',
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'invoice.create',
            'target_type' => 'invoice',
            'target_id' => $invoice->id,
            'details' => $invoiceNumber,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['id' => $invoice->id, 'invoice_number' => $invoiceNumber], 201);
    }

    public function cancel($id, Request $request): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $user = $request->user();
        if (!in_array($user->role, ['admin', 'lawyer'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoice->update(['status' => 'cancelled']);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'invoice.cancel',
            'target_type' => 'invoice',
            'target_id' => $invoice->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Invoice cancelled successfully']);
    }

    public function refund($id, Request $request): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        if ($invoice->status !== 'paid') {
            return response()->json(['error' => 'Only paid invoices can be refunded'], 400);
        }

        $invoice->update(['status' => 'refunded']);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role,
            'action' => 'invoice.refund',
            'target_type' => 'invoice',
            'target_id' => $invoice->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Invoice refunded successfully']);
    }
}