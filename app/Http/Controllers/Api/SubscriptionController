<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json([
            ['plan' => 'free', 'name' => 'مجاني', 'price' => 0, 'features' => ['قضية واحدة', '5 مستندات', '3 فواتير']],
            ['plan' => 'monthly', 'name' => 'شهري', 'price' => 99, 'features' => ['5 قضايا', '50 مستندًا', '50 فاتورة', 'أولوية الدعم']],
            ['plan' => 'yearly', 'name' => 'سنوي', 'price' => 990, 'features' => ['قضايا غير محدودة', 'مستندات غير محدودة', 'فواتير غير محدودة', 'دعم مباشر']],
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscriptions()->where('status', 'active')->latest()->first();

        if (!$subscription) {
            $subscription = new Subscription(['plan' => 'free', 'status' => 'active']);
        }

        return response()->json($subscription);
    }

    public function myRecords(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->subscriptions()->latest()->get();
        return response()->json($subscriptions);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Cancel existing active subscription
        $existing = $user->subscriptions()->where('status', 'active')->first();
        if ($existing) {
            $existing->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        // Create new subscription
        $expiresAt = $request->plan === 'yearly' ? now()->addYear() : now()->addMonth();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => $request->plan,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return response()->json(['message' => 'Subscription upgraded successfully', 'subscription' => $subscription]);
    }
}