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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_name')->nullable();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name')->nullable();
            $table->string('event', 50); // created, updated, deleted, restored
            $table->string('description');
            $table->json('properties')->nullable(); // {old: {...}, attributes: {...}, ip: "...", user_agent: "..."}
            $table->timestamp('created_at')->nullable()->index();

            // Composite index for ultra-fast multi-tenancy group queries
            $table->index(['group_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
