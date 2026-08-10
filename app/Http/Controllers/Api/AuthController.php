<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'city' => $user->city,
            'specialty' => $user->specialty,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Register new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:128',
            'email' => 'required|email|max:320',
            'password' => 'required|string|min:8|max:128',
            'phone' => 'nullable|string|max:32',
            'role' => 'required|in:admin,lawyer,consultant,client',
            'specialty' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->authService->register($validator->validated());
            $token = $this->authService->createToken($user);

            AuditLog::create([
                'actor_id' => auth('sanctum')->id() ?? $user->id,
                'actor_role' => auth('sanctum')->user()?->role ?? $user->role,
                'action' => 'user.register',
                'target_type' => 'user',
                'target_id' => $user->id,
                'details' => $user->email . ' — ' . $user->role,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'تم التسجيل بنجاح',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->authService->login($request->email, $request->password);
            $token = $this->authService->createToken($user);

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'action' => 'user.login',
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->authService->logout($user);
        }

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    /**
     * Set password (admin only)
     */
    public function setPassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string|min:8|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $targetUser = User::findOrFail($request->user_id);
            $this->authService->setPassword($targetUser, $request->password);

            return response()->json(['message' => 'تم تحديث كلمة المرور بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
