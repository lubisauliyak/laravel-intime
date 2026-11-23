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
        Schema::table('groups', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('name');
            $table->foreignUlid('card_template_id')->nullable()->after('code')->constrained('card_templates')->nullOnDelete();

            $table->index('code');
            $table->index(['parent_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['card_template_id']);
            $table->dropIndex(['parent_id', 'code']);
            $table->dropIndex(['code']);
            $table->dropColumn(['card_template_id', 'code']);
        });
    }
};
