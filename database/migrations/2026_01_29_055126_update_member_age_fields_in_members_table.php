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
            $table->dropColumn('age_group');
            $table->integer('age')->after('birth_date')->nullable();
            $table->foreignId('age_group_id')->after('age')->nullable()->constrained('age_groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['age_group_id']);
            $table->dropColumn(['age', 'age_group_id']);
            $table->string('age_group')->after('birth_date');
        });
    }
};
