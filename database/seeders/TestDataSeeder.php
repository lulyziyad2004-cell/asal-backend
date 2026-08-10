<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LegalCase as CaseModel;
use App\Models\Invoice;
use App\Models\Hearing;
use App\Models\Notification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@asal.local',
            'phone' => '0501234567',
            'open_id' => 'admin_local',
            'login_method' => 'local',
            'role' => 'admin',
            'password_hash' => Hash::make('admin123'),
            'city' => 'Riyadh',
            'status' => 'active',
        ]);

        echo "✅ Admin created: admin@asal.local\n";

        // Create client user
        $client = User::create([
            'name' => 'Test Client',
            'email' => 'client@asal.local',
            'phone' => '0502345678',
            'open_id' => 'client_local',
            'login_method' => 'local',
            'role' => 'client',
            'password_hash' => Hash::make('client123'),
            'city' => 'Jeddah',
            'status' => 'active',
        ]);

        echo "✅ Client created: client@asal.local\n";

        // Create lawyer user
        $lawyer = User::create([
            'name' => 'Test Lawyer',
            'email' => 'lawyer@asal.local',
            'phone' => '0503456789',
            'open_id' => 'lawyer_local',
            'login_method' => 'local',
            'role' => 'lawyer',
            'password_hash' => Hash::make('lawyer123'),
            'specialty' => 'Commercial Law',
            'city' => 'Dammam',
            'status' => 'active',
        ]);

        echo "✅ Lawyer created: lawyer@asal.local\n";

        // Create consultant user
        $consultant = User::create([
            'name' => 'Test Consultant',
            'email' => 'consultant@asal.local',
            'phone' => '0504567890',
            'open_id' => 'consultant_local',
            'login_method' => 'local',
            'role' => 'consultant',
            'password_hash' => Hash::make('consultant123'),
            'specialty' => 'Legal Consultation',
            'city' => 'Riyadh',
            'status' => 'active',
        ]);

        echo "✅ Consultant created: consultant@asal.local\n";

        // Create test case
        $legalCase = CaseModel::create([
            'case_number' => 'ASL-2026-ABC12345',
            'title' => 'Commercial Dispute Case',
            'description' => 'Test case for system validation',
            'client_id' => $client->id,
            'lawyer_id' => $lawyer->id,
            'consultant_id' => $consultant->id,
            'court' => 'Commercial Court',
            'city' => 'Riyadh',
            'circuit_number' => '5',
            'category' => 'commercial',
            'status' => 'open',
        ]);

        echo "✅ Case created: " . $legalCase->case_number . "\n";

        // Create test invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-XYZ98765',
            'client_id' => $client->id,
            'case_id' => $legalCase->id,
            'title' => 'Legal Services - Initial Consultation',
            'amount' => 5000,
            'currency' => 'SAR',
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        echo "✅ Invoice created: " . $invoice->invoice_number . "\n";

        // Create test hearing
        $hearing = Hearing::create([
            'case_id' => $legalCase->id,
            'title' => 'First Court Hearing',
            'court' => 'Commercial Court',
            'city' => 'Riyadh',
            'circuit_number' => '5',
            'scheduled_at' => now()->addDays(14),
            'assigned_lawyer_id' => $lawyer->id,
            'status' => 'scheduled',
        ]);

        echo "✅ Hearing created: ID " . $hearing->id . "\n";

        // Create test notification
        $notification = Notification::create([
            'recipient_id' => $client->id,
            'recipient_role' => 'client',
            'title' => 'New Invoice',
            'message' => 'You have a new invoice for your case',
            'type' => 'info',
            'is_read' => 'no',
        ]);

        echo "✅ Notification created: ID " . $notification->id . "\n";

        echo "\n✅ All test data created successfully!\n";
        echo "\nTest Credentials:\n";
        echo "  Admin: admin@asal.local / admin123\n";
        echo "  Client: client@asal.local / client123\n";
        echo "  Lawyer: lawyer@asal.local / lawyer123\n";
        echo "  Consultant: consultant@asal.local / consultant123\n";
    }
}
