<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan kolom uid ke tabel members, groups, meetings
        Schema::table('members', function (Blueprint $table) {
            $table->ulid('uid')->nullable()->unique()->after('id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->ulid('uid')->nullable()->unique()->after('id');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->ulid('uid')->nullable()->unique()->after('id');
        });

        // 2. Backfill ULID untuk data yang sudah ada
        \App\Models\Member::withTrashed()->whereNull('uid')->get()->each(function ($item) {
            $item->uid = (string) Str::ulid();
            $item->saveQuietly();
        });

        \App\Models\Group::withTrashed()->whereNull('uid')->get()->each(function ($item) {
            $item->uid = (string) Str::ulid();
            $item->saveQuietly();
        });

        \App\Models\Meeting::withTrashed()->whereNull('uid')->get()->each(function ($item) {
            $item->uid = (string) Str::ulid();
            $item->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('uid');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('uid');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
