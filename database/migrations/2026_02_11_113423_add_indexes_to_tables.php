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
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->index('gender');
            $table->index('status');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->index('meeting_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropIndex(['status']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['meeting_date']);
        });
    }
};
