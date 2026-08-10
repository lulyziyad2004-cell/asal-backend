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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('open_id', 64)->unique()->notNullable();
            $table->string('name')->nullable();
            $table->string('email', 320)->unique()->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('login_method', 64)->nullable();
            $table->enum('role', ['admin', 'lawyer', 'consultant', 'client'])->default('client')->notNullable();
            $table->string('password_hash', 255)->nullable();
            $table->text('specialty')->nullable();
            $table->text('case_number')->nullable();
            $table->string('city', 128)->nullable();
            $table->text('avatar_key')->nullable();
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active')->notNullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('last_signed_in')->useCurrent();
            
            $table->index('role');
            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
