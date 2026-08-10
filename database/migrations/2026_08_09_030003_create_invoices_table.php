<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 64)->unique()->notNullable();
            $table->unsignedBigInteger('client_id')->notNullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->string('title', 255)->notNullable();
            $table->decimal('amount', 12, 2)->notNullable();
            $table->string('currency', 8)->default('SAR')->notNullable();
            $table->timestamp('due_date')->nullable();
            $table->enum('status', ['draft', 'unpaid', 'paid', 'overdue', 'failed', 'cancelled', 'refunded'])->default('draft')->notNullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
            $table->index('client_id');
            $table->index('case_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
