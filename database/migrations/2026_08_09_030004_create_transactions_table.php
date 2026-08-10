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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->notNullable();
            $table->unsignedBigInteger('client_id')->notNullable();
            $table->string('pay_tabs_tran_ref', 255)->nullable();
            $table->string('pay_tabs_ref', 255)->nullable();
            $table->decimal('amount', 12, 2)->notNullable();
            $table->string('currency', 8)->default('SAR')->notNullable();
            $table->enum('status', ['pending', 'captured', 'failed', 'cancelled', 'refunded'])->default('pending')->notNullable();
            $table->string('payment_method', 64)->nullable();
            $table->text('redirect_url')->nullable();
            $table->enum('callback_verified', ['yes', 'no', 'na'])->default('na')->notNullable();
            $table->text('failure_reason')->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('invoice_id');
            $table->index('client_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
