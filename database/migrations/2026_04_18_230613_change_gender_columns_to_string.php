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
        Schema::table('members', function (Blueprint $table) {
            $table->string('gender')->change();
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->string('target_gender')->default('all')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->change();
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->enum('target_gender', ['all', 'male', 'female'])->default('all')->change();
        });
    }
};
