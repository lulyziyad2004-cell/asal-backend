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
                'open_id' => 'admin-asal',
                'login_method' => 'password',
                'role' => 'admin',
                'password_hash' => Hash::make('12345678901'),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'fahad@asal.sa'],
            [
                'name' => 'المحامي فهد القحطاني',
                'email' => 'fahad@asal.sa',
                'open_id' => 'lawyer-fahad',
                'login_method' => 'password',
                'role' => 'lawyer',
                'phone' => '0551234567',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'malik@asal.sa'],
            [
                'name' => 'المستشار د. عبدالملك',
                'email' => 'malik@asal.sa',
                'open_id' => 'consultant-malik',
                'login_method' => 'password',
                'role' => 'consultant',
                'phone' => '0559876543',
                'specialty' => 'أنظمة الشركات والتحكيم',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'client@afaq.sa'],
            [
                'name' => 'شركة آفاق المستقبل',
                'email' => 'client@afaq.sa',
                'open_id' => 'client-afaq',
                'login_method' => 'password',
                'role' => 'client',
                'phone' => '0114567890',
                'status' => 'active',
            ]
        );
    }
}
