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
                Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->notNullable();
            $table->enum('category', ['contract', 'memo', 'poa', 'hearing_related', 'other'])->default('other')->notNullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->unsignedBigInteger('hearing_id')->nullable();
            $table->unsignedBigInteger('uploader_id')->notNullable();
            $table->enum('uploader_role', ['admin', 'lawyer', 'consultant', 'client'])->notNullable();
            $table->string('file_name', 255)->notNullable();
            $table->string('file_key', 512)->notNullable();
            $table->string('file_url', 1024)->notNullable();
            $table->string('mime_type', 128)->nullable();
            $table->integer('size_bytes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
            $table->foreign('uploader_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('case_id');
            $table->index('uploader_id');
            $table->index('category');
        });;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
