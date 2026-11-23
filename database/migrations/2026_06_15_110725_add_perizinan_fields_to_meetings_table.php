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
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('is_perizinan_active')->default(false)->after('target_age_groups');
            $table->text('perizinan_pembuka')->nullable()->after('is_perizinan_active');
            $table->text('perizinan_pernyataan')->nullable()->after('perizinan_pembuka');
            $table->text('perizinan_penutup')->nullable()->after('perizinan_pernyataan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'is_perizinan_active',
                'perizinan_pembuka',
                'perizinan_pernyataan',
                'perizinan_penutup',
            ]);
        });
    }
};
