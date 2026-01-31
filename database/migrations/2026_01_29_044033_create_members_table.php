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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code')->unique();
            $table->string('full_name');
            $table->string('nick_name')->nullable();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->date('birth_date');
            $table->string('age_group');
            $table->enum('gender', ['male', 'female']);
            $table->enum('status', ['active', 'inactive', 'moved'])->default('active');
            $table->enum('membership_type', ['anggota', 'pengurus'])->default('anggota');
            $table->string('qr_code_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
