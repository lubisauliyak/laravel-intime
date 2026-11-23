<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->renameColumn('is_perizinan_active', 'is_permission_template_active');
            $table->renameColumn('perizinan_pembuka', 'permission_opening_text');
            $table->renameColumn('perizinan_pernyataan', 'permission_statement_text');
            $table->renameColumn('perizinan_penutup', 'permission_closing_text');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('is_permission_enabled')->default(false)->after('is_permission_template_active');
            $table->boolean('is_presence_enabled')->default(false)->after('is_permission_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['is_presence_enabled', 'is_permission_enabled']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->renameColumn('is_permission_template_active', 'is_perizinan_active');
            $table->renameColumn('permission_opening_text', 'perizinan_pembuka');
            $table->renameColumn('permission_statement_text', 'perizinan_pernyataan');
            $table->renameColumn('permission_closing_text', 'perizinan_penutup');
        });
    }
};
