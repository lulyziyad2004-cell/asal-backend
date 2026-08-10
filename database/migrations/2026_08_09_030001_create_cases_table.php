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
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 64)->unique()->notNullable();
            $table->string('title', 255)->notNullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('client_id')->notNullable();
            $table->unsignedBigInteger('lawyer_id')->nullable();
            $table->unsignedBigInteger('consultant_id')->nullable();
            $table->string('court', 255)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('circuit_number', 64)->nullable();
            $table->string('poa_number', 64)->nullable();
            $table->timestamp('poa_expiry')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed', 'cancelled'])->default('open')->notNullable();
            $table->enum('category', ['criminal', 'commercial', 'civil', 'labor', 'family', 'corporate_governance', 'other'])->default('other');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lawyer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('consultant_id')->references('id')->on('users')->onDelete('set null');
            $table->index('client_id');
            $table->index('lawyer_id');
            $table->index('consultant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
