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
                Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_id')->notNullable();
            $table->enum('recipient_role', ['admin', 'lawyer', 'consultant', 'client'])->nullable();
            $table->string('title', 255)->notNullable();
            $table->text('message')->notNullable();
            $table->enum('type', ['info', 'success', 'warning', 'error'])->default('info')->notNullable();
            $table->enum('is_read', ['yes', 'no'])->default('no')->notNullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('recipient_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('recipient_id');
            $table->index('is_read');
        });;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
