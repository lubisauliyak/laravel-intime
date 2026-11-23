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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->dateTime('checkin_time');
            $table->enum('method', ['manual', 'qr_code'])->default('qr_code');
            $table->enum('attendance_type', ['wajib', 'opsional', 'istimewa'])->default('wajib');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            // Ensure a member can only have one attendance record per meeting
            $table->unique(['meeting_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
