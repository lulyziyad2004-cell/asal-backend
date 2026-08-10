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
                Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->string('action', 128)->notNullable();
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('details')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
            $table->index('actor_id');
            $table->index('action');
            $table->index('target_type');
        });;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
