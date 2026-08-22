<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@asal.sa'],
            [
                'name' => 'مدير النظام',
                'email' => 'admin@asal.sa',
                'role' => 'admin',
                'password_hash' => Hash::make('12345678901'),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'مستخدم تجريبي',
                'email' => 'test@example.com',
                'role' => 'client',
                'password_hash' => Hash::make('12345678901'),
                'status' => 'active',
            ]
        );
    }
}
