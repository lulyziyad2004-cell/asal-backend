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
        Schema::create('hearings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->notNullable();
            $table->string('title', 255)->notNullable();
            $table->string('court', 255)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('circuit_number', 64)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['scheduled', 'postponed', 'done', 'cancelled'])->default('scheduled')->notNullable();
            $table->text('defense_notes')->nullable();
            $table->text('requirements')->nullable();
            $table->unsignedBigInteger('assigned_lawyer_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
            $table->foreign('assigned_lawyer_id')->references('id')->on('users')->onDelete('set null');
            $table->index('case_id');
            $table->index('assigned_lawyer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hearings');
    }
};
