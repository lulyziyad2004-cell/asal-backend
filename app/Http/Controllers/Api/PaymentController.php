<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice = Invoice::find($request->invoice_id);
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        if ($user->role === 'client' && $invoice->client_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if pending transaction exists
        $existing = $invoice->transactions()->where('status', 'pending')->first();
        if ($existing) {
            return response()->json([
                'redirect_url' => $existing->redirect_url,
                'transaction_id' => $existing->id,
            ]);
        }

        // Create a pending transaction
        $transactionRef = 'TXN-' . Str::upper(Str::random(10));
        $transaction = Transaction::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'pay_tabs_tran_ref' => $transactionRef,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => 'pending',
            'redirect_url' => config('app.url') . '/dashboard/invoices',
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role,
            'action' => 'payment.create_session',
            'target_type' => 'transaction',
            'target_id' => $transaction->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'redirect_url' => $transaction->redirect_url,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function status($invoiceId, Request $request): JsonResponse
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $user = $request->user();
        if ($user->role === 'client' && $invoice->client_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $transactions = $invoice->transactions()->get();
        return response()->json($transactions);
    }

    public function callback(Request $request): JsonResponse
    {
        // This would handle PayTabs webhook callback
        // For now, just return success
        return response()->json(['success' => true]);
    }
}