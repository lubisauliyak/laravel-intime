<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Group;
use App\Models\AgeGroup;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== MEMULAI PENGUJIAN EKSEKUSI DATABASE TRANSACTION (FASE 5) ===\n\n";

// Set database ke sqlite in-memory agar independen dan portabel
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
config(['cache.default' => 'array']);
DB::purge();
\Illuminate\Support\Facades\Cache::flush();

// Skema database uji
Schema::create('groups', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->timestamps();
    $table->softDeletes();
});
Schema::create('age_groups', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->integer('min_age')->nullable();
    $table->integer('max_age')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
Schema::create('members', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('member_code')->nullable()->unique();
    $table->string('full_name');
    $table->string('nick_name')->nullable();
    $table->foreignId('group_id')->nullable();
    $table->date('birth_date')->nullable();
    $table->integer('age')->nullable();
    $table->foreignId('age_group_id')->nullable();
    $table->string('gender');
    $table->boolean('status')->default(true);
    $table->string('membership_type')->default('anggota');
    $table->string('qr_code_path')->nullable();
    $table->string('profile_photo_path')->nullable();
    $table->string('membership_card_photo_path')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

$group = Group::create(['name' => 'KELOMPOK MAWAR']);
$ageGroup = AgeGroup::create(['name' => 'Dewasa', 'min_age' => 20, 'max_age' => 40]);

// Seed 1 anggota lama untuk uji update
$existing = Member::create([
    'member_code' => 'M_EXISTING_001',
    'full_name' => 'NAMA LAMA',
    'group_id' => $group->id,
    'gender' => \App\Enums\Gender::MALE,
]);

echo "Kondisi Awal Database:\n";
echo "- Total Member: " . Member::count() . "\n";
echo "- Member Eksisting: {$existing->member_code} ({$existing->full_name})\n\n";

// Siapkan data simulasi hasil parser (2 baris valid: 1 baru, 1 update; 1 baris invalid dilewati)
$simulatedValidRows = [
    [
        'member_code' => null, // Baru
        'full_name' => 'ANGGOTA BARU PERTAMA',
        'nick_name' => 'BARU',
        'group_id' => $group->id,
        'birth_date' => '1995-01-15',
        'age' => 31,
        'age_group_id' => $ageGroup->id,
        'gender' => \App\Enums\Gender::MALE->value,
        'membership_type' => 'anggota',
        'is_update' => false,
    ],
    [
        'member_code' => 'M_EXISTING_001', // Update
        'full_name' => 'NAMA BARU DIPERBARUI',
        'nick_name' => 'UPDATE',
        'group_id' => $group->id,
        'birth_date' => '1990-05-20',
        'age' => 36,
        'age_group_id' => $ageGroup->id,
        'gender' => \App\Enums\Gender::MALE->value,
        'membership_type' => 'anggota',
        'is_update' => true,
    ],
];

// 1. Uji Penyimpanan Berhasil
DB::beginTransaction();

$inserted = 0;
$updated = 0;

foreach ($simulatedValidRows as $row) {
    $data = [
        'full_name' => $row['full_name'],
        'nick_name' => $row['nick_name'],
        'group_id' => $row['group_id'],
        'birth_date' => $row['birth_date'],
        'age' => $row['age'],
        'age_group_id' => $row['age_group_id'],
        'gender' => $row['gender'],
        'status' => true,
        'membership_type' => $row['membership_type'],
    ];

    if (!empty($row['member_code']) && ($row['is_update'] ?? false)) {
        $found = Member::where('member_code', $row['member_code'])->first();
        if ($found) {
            $found->update($data);
            $updated++;
            continue;
        }
    }

    $data['member_code'] = !empty($row['member_code']) ? $row['member_code'] : null;
    $newM = new Member($data);
    $newM->save();
    $inserted++;
}

DB::commit();

echo "--- HASIL SETELAH COMMIT TRANSAKSI ---\n";
echo "Total Member Sekarang: " . Member::count() . " (Ekspektasi: 2)\n";
echo "Jumlah Insert Baru: {$inserted} (Ekspektasi: 1)\n";
echo "Jumlah Diperbarui: {$updated} (Ekspektasi: 1)\n";

$updatedRecord = Member::where('member_code', 'M_EXISTING_001')->first();
echo "Nama Member M_EXISTING_001 Setelah Update: '{$updatedRecord->full_name}' (Ekspektasi: 'NAMA BARU DIPERBARUI')\n\n";

// 2. Uji Rollback Transaksi Jika Error
echo "--- PENGUJIAN ATOMIC ROLLBACK JIKA TERJADI KESALAHAN ---\n";
try {
    DB::beginTransaction();
    $failMember = new Member([
        'full_name' => 'ANGGOTA GAGAL HARUS ROLLBACK',
        'group_id' => $group->id,
        'gender' => \App\Enums\Gender::MALE->value,
    ]);
    $failMember->save();

    // Picu error sengaja
    throw new \Exception("Simulasi error tak terduga!");

    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    echo "Pengecualian tertangkap: " . $e->getMessage() . " -> Transaksi sukses di-rollback!\n";
}

$countAfterRollback = Member::count();
echo "Total Member Setelah Rollback: {$countAfterRollback} (Ekspektasi: tetap 2)\n";

$isSuccess = (Member::count() === 2) &&
             ($inserted === 1) &&
             ($updated === 1) &&
             ($updatedRecord->full_name === 'NAMA BARU DIPERBARUI') &&
             ($countAfterRollback === 2);

if ($isSuccess) {
    echo "\n>>> STATUS: SEMUA PENGUJIAN FASE 5 BERHASIL DENGAN SEMPURNA! <<<\n";
    exit(0);
} else {
    echo "\n>>> STATUS: ADA ASERSi FASE 5 YANG GAGAL! <<<\n";
    exit(1);
}
