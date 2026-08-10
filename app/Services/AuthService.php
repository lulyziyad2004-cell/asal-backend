<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * Register a new user
     */
    public function register(array $data): User
    {
        // Check if email already exists
        if (User::where('email', $data['email'])->exists()) {
            throw new \Exception('البريد الإلكتروني مسجل مسبقاً');
        }

        // Only admins can create privileged accounts
        $currentUser = auth('sanctum')->user();
        if ($data['role'] !== 'client' && (!$currentUser || $currentUser->role !== 'admin')) {
            throw new \Exception('فقط المدير يستطيع إنشاء حسابات لهذا الدور');
        }

        $user = User::create([
            'open_id' => 'local_' . Str::lower($data['email']),
            'email' => Str::lower($data['email']),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'password_hash' => Hash::make($data['password']),
            'specialty' => $data['specialty'] ?? null,
            'city' => $data['city'] ?? null,
            'login_method' => 'local',
            'status' => 'active',
            'last_signed_in' => now(),
        ]);

        return $user;
    }

    /**
     * Login user with email and password
     */
    public function login(string $email, string $password): User
    {
        $user = User::where('email', Str::lower($email))->first();

        if (!$user) {
            throw new \Exception('البريد الإلكتروني غير موجود');
        }

        if (!$user->password_hash) {
            throw new \Exception('لا يمكن تسجيل الدخول لهذا الحساب حالياً');
        }

        if (!Hash::check($password, $user->password_hash)) {
            throw new \Exception('كلمة المرور غير صحيحة');
        }

        if ($user->status !== 'active') {
            throw new \Exception('هذا الحساب معطّل، تواصل مع الإدارة');
        }

        $user->update(['last_signed_in' => now()]);

        return $user;
    }

    /**
     * Create API token for user
     */
    public function createToken(User $user, string $tokenName = 'api-token'): string
    {
        return $user->createToken($tokenName)->plainTextToken;
    }

    /**
     * Logout user (revoke all tokens)
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Set password for user (admin only)
     */
    public function setPassword(User $user, string $password): void
    {
        $user->update(['password_hash' => Hash::make($password)]);
    }
}
