<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Member;
use App\Services\Members\MemberImportParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

echo "=== MEMULAI PENGUJIAN MEMBER IMPORT PARSER (ISOLATED SQLITE MEMORY) ===\n\n";

// Set database ke sqlite in-memory agar independen dan portabel
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]]);
config(['cache.default' => 'array']);

\Illuminate\Support\Facades\DB::purge();
\Illuminate\Support\Facades\Cache::flush();

// Buat skema minimal untuk pengujian
Schema::create('groups', function (Blueprint $table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('age_groups', function (Blueprint $table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->integer('min_age')->nullable();
    $table->integer('max_age')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

Schema::create('members', function (Blueprint $table) {
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

// Seed master data
$group = Group::create(['name' => 'KELOMPOK MAWAR']);
$ageGroupPraNikah = AgeGroup::create(['name' => 'Pra Nikah', 'min_age' => null, 'max_age' => null]);
$ageGroupDewasa = AgeGroup::create(['name' => 'Dewasa', 'min_age' => 20, 'max_age' => 40]);

// Seed data anggota soft deleted dan anggota aktif
$trashedMember = Member::create([
    'member_code' => 'M_TRASHED_001',
    'full_name' => 'ANGGOTA TERHAPUS',
    'group_id' => $group->id,
    'gender' => \App\Enums\Gender::MALE,
]);
$trashedMember->delete(); // Soft delete

$activeMember = Member::create([
    'member_code' => 'M_ACTIVE_002',
    'full_name' => 'ANGGOTA AKTIF LAMA',
    'group_id' => $group->id,
    'gender' => \App\Enums\Gender::FEMALE,
]);

echo "Master data berhasil disiapkan:\n";
echo "- Kelompok: {$group->name} (ID: {$group->id})\n";
echo "- AgeGroup: {$ageGroupPraNikah->name}, {$ageGroupDewasa->name}\n";
echo "- Member Trashed: {$trashedMember->member_code}\n";
echo "- Member Active: {$activeMember->member_code}\n\n";

// Siapkan file CSV pengujian
$csvPath = __DIR__.'/sample_test_import.csv';
$csvData = [
    ['ID Anggota', 'Kelompok (Wajib)', 'Nama Lengkap (Wajib)', 'Nama Panggilan', 'Tanggal Lahir', 'L/P (Wajib)', 'Keterangan Error'],
    ['', 'KELOMPOK MAWAR', 'Budi Santoso', 'Budi', '15/01/1995', 'L', ''], // Valid 1: Umur ~31 -> Dewasa
    ['M_ACTIVE_002', 'kelompok mawar', 'Siti Aminah Diperbarui', 'Siti', '', 'P', 'Contoh keterangan error yang harus diabaikan'], // Valid 2: Update member, umur null -> Pra Nikah
    ['', 'KELOMPOK MAWAR', '', 'NoName', '10/10/2000', 'L', ''], // Invalid 1: Nama kosong
    ['', 'KELOMPOK_TIDAK_ADA', 'Joko Widodo', '', '01/01/1990', 'L', ''], // Invalid 2: Kelompok tidak ditemukan
    ['', 'KELOMPOK MAWAR', 'Rina Marlina', 'Rina', '20/05/1998', 'X', ''], // Invalid 3: Gender tidak valid
    ['', 'KELOMPOK MAWAR', 'Dewi Sartika', 'Dewi', '32/13/2020', 'P', ''], // Invalid 4: Tanggal lahir tidak valid
    ['M_TRASHED_001', 'KELOMPOK MAWAR', 'Anggota Sampah', 'Sampah', '12/12/1992', 'L', ''], // Invalid 5: ID di tong sampah
];

$fp = fopen($csvPath, 'w');
foreach ($csvData as $line) {
    fputcsv($fp, $line);
}
fclose($fp);

$parser = new MemberImportParser;
$result = $parser->parse($csvPath);

echo "--- HASIL PARSING ---\n";
echo 'Total Baris Terbaca: '.$result['total_rows']." (Ekspektasi: 7)\n";
echo 'Data Valid: '.$result['valid_count']." (Ekspektasi: 2)\n";
echo 'Data Bermasalah: '.$result['invalid_count']." (Ekspektasi: 5)\n\n";

echo "--- DETAIL DATA BERMASALAH ---\n";
foreach ($result['invalid_rows'] as $inv) {
    echo '[Baris Excel '.$inv['excel_row'].'] '.($inv['full_name'] ?: '<Nama Kosong>').' => '.$inv['error_message']."\n";
}

echo "\n--- DETAIL DATA VALID ---\n";
foreach ($result['valid_rows'] as $v) {
    echo '[Baris Excel '.$v['excel_row'].'] '.$v['full_name'].' (Update: '.($v['is_update'] ? 'YA' : 'TIDAK').', Grup: '.$v['group_id'].', Gender: '.$v['gender'].', Usia: '.($v['age'] ?? 'null').', AgeGroup: '.$v['age_group_id'].")\n";
}

// Bersihkan file uji
if (file_exists($csvPath)) {
    unlink($csvPath);
}

// Validasi asersi
$isSuccess = ($result['total_rows'] === 7) &&
             ($result['valid_count'] === 2) &&
             ($result['invalid_count'] === 5) &&
             ($result['valid_rows'][1]['is_update'] === true) &&
             ($result['valid_rows'][0]['age_group_id'] === $ageGroupDewasa->id) &&
             ($result['valid_rows'][1]['age_group_id'] === $ageGroupPraNikah->id);

if ($isSuccess) {
    echo "\n>>> STATUS: SEMUA TES BERHASIL DENGAN SEMPURNA! <<<\n";
    exit(0);
} else {
    echo "\n>>> STATUS: ADA HASIL YANG TIDAK SESUAI EKPEKTASI! <<<\n";
    exit(1);
}
