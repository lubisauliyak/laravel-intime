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
        Schema::table('member_positions', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('position_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_positions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
