<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exports\MemberImportErrorsExport;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Member;
use App\Services\Members\MemberImportParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

echo "===============================================================\n";
echo "=== PENGUJIAN KOMPREHENSIF EDGE CASES & FINISHING (FASE 6) ===\n";
echo "===============================================================\n\n";

// Set database ke sqlite in-memory
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
config(['cache.default' => 'array']);
DB::purge();
\Illuminate\Support\Facades\Cache::flush();

// Setup Schema
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

// Seed data
$group = Group::create(['name' => 'KELOMPOK MELATI']);
$ageGroupPraNikah = AgeGroup::create(['name' => 'Pra Nikah', 'min_age' => null, 'max_age' => null]);
$ageGroupDewasa = AgeGroup::create(['name' => 'Dewasa', 'min_age' => 20, 'max_age' => 40]);

$trashed = Member::create([
    'member_code' => 'M_TRASHED_099',
    'full_name' => 'ANGGOTA TERHAPUS',
    'group_id' => $group->id,
    'gender' => \App\Enums\Gender::MALE,
]);
$trashed->delete();

$parser = new MemberImportParser;

// -------------------------------------------------------------
// Skenario 1: Berkas Kosong & Hanya Header
// -------------------------------------------------------------
echo "[SKENARIO 1] Uji Berkas Kosong / Hanya Baris Header\n";
$emptyCsvPath = __DIR__.'/test_empty.csv';
$fp = fopen($emptyCsvPath, 'w');
fputcsv($fp, ['ID Anggota', 'Kelompok (Wajib)', 'Nama Lengkap (Wajib)', 'Nama Panggilan', 'Tanggal Lahir', 'L/P (Wajib)']);
fclose($fp);

$resEmpty = $parser->parse($emptyCsvPath);
unlink($emptyCsvPath);

$pass1 = ($resEmpty['total_rows'] === 0) && ($resEmpty['valid_count'] === 0) && ($resEmpty['invalid_count'] === 0);
echo "- Total Data: {$resEmpty['total_rows']} (Ekspektasi: 0)\n";
echo '- Status: '.($pass1 ? 'PASSED' : 'FAILED')."\n\n";

// -------------------------------------------------------------
// Skenario 2: Dataset Skala Besar (1.000 Baris Data Sintetis)
// -------------------------------------------------------------
echo "[SKENARIO 2] Uji Performa & Memori: 1.000 Baris Data\n";
$largeCsvPath = __DIR__.'/test_large.csv';
$fp = fopen($largeCsvPath, 'w');
fputcsv($fp, ['ID Anggota', 'Kelompok (Wajib)', 'Nama Lengkap (Wajib)', 'Nama Panggilan', 'Tanggal Lahir', 'L/P (Wajib)']);

for ($i = 1; $i <= 1000; $i++) {
    // 950 baris valid, 50 baris invalid (gender salah)
    $gender = ($i % 20 === 0) ? 'INVALID' : (($i % 2 === 0) ? 'L' : 'P');
    fputcsv($fp, ['', 'KELOMPOK MELATI', "ANGGOTA KE {$i}", "MEM{$i}", '15/01/1995', $gender]);
}
fclose($fp);

$memStart = memory_get_usage();
$timeStart = microtime(true);

$resLarge = $parser->parse($largeCsvPath);

$duration = round(microtime(true) - $timeStart, 3);
$memUsedMb = round((memory_get_usage() - $memStart) / 1024 / 1024, 2);
unlink($largeCsvPath);

$pass2 = ($resLarge['total_rows'] === 1000) && ($resLarge['valid_count'] === 950) && ($resLarge['invalid_count'] === 50) && ($duration < 5.0);
echo "- Total Data: {$resLarge['total_rows']} | Valid: {$resLarge['valid_count']} | Bermasalah: {$resLarge['invalid_count']}\n";
echo "- Waktu Eksekusi: {$duration} detik (Ekspektasi: < 5 detik)\n";
echo "- Tambahan Memori: {$memUsedMb} MB\n";
echo '- Status: '.($pass2 ? 'PASSED' : 'FAILED')."\n\n";

// -------------------------------------------------------------
// Skenario 3: Penanganan Spasi Liar & Huruf Besar-Kecil Kelompok
// -------------------------------------------------------------
echo "[SKENARIO 3] Uji Spasi Liar (Trim) & Case-Insensitivity Nama Kelompok\n";
$groupCsvPath = __DIR__.'/test_group.csv';
$fp = fopen($groupCsvPath, 'w');
fputcsv($fp, ['ID Anggota', 'Kelompok (Wajib)', 'Nama Lengkap (Wajib)', 'Nama Panggilan', 'Tanggal Lahir', 'L/P (Wajib)']);
fputcsv($fp, ['', '  KELOMPOK MELATI  ', 'Budi Spasi Awal Akhir', '', '', 'L']);
fputcsv($fp, ['', 'kelompok melati', 'Siti Huruf Kecil', '', '', 'P']);
fputcsv($fp, ['', 'Kelompok Melati', 'Dewi Huruf Campuran', '', '', 'P']);
fclose($fp);

$resGroup = $parser->parse($groupCsvPath);
unlink($groupCsvPath);

$pass3 = ($resGroup['valid_count'] === 3) && ($resGroup['invalid_count'] === 0);
echo "- Total: {$resGroup['total_rows']} | Valid: {$resGroup['valid_count']} (Ekspektasi: 3)\n";
echo '- Status: '.($pass3 ? 'PASSED' : 'FAILED')."\n\n";

// -------------------------------------------------------------
// Skenario 4: Variasi Format Tanggal Lahir (String & Serial Excel)
// -------------------------------------------------------------
echo "[SKENARIO 4] Uji Variasi Format Tanggal Lahir & Tanggal Kosong\n";
$dateCsvPath = __DIR__.'/test_dates.csv';
$fp = fopen($dateCsvPath, 'w');
fputcsv($fp, ['ID Anggota', 'Kelompok (Wajib)', 'Nama Lengkap (Wajib)', 'Nama Panggilan', 'Tanggal Lahir', 'L/P (Wajib)']);
fputcsv($fp, ['', 'KELOMPOK MELATI', 'Format DD/MM/YYYY', '', '15/01/1995', 'L']);
fputcsv($fp, ['', 'KELOMPOK MELATI', 'Format YYYY-MM-DD', '', '1995-01-15', 'L']);
fputcsv($fp, ['', 'KELOMPOK MELATI', 'Format Serial Excel', '', '34714', 'L']); // 15 Jan 1995
fputcsv($fp, ['', 'KELOMPOK MELATI', 'Tanggal Kosong Default', '', '', 'P']);
fputcsv($fp, ['', 'KELOMPOK MELATI', 'Tanggal Tidak Valid', '', '32/13/2020', 'P']);
fclose($fp);

$resDates = $parser->parse($dateCsvPath);
unlink($dateCsvPath);

$pass4 = ($resDates['valid_count'] === 4) &&
         ($resDates['invalid_count'] === 1) &&
         ($resDates['valid_rows'][3]['age_group_id'] === $ageGroupPraNikah->id);
echo "- Valid: {$resDates['valid_count']} | Bermasalah: {$resDates['invalid_count']}\n";
echo "- Anggota Tanggal Kosong Dialokasikan ke: AgeGroup ID {$resDates['valid_rows'][3]['age_group_id']} (Ekspektasi: {$ageGroupPraNikah->id})\n";
echo '- Status: '.($pass4 ? 'PASSED' : 'FAILED')."\n\n";

// -------------------------------------------------------------
// Skenario 5: Proteksi ID Anggota Soft-Delete
// -------------------------------------------------------------
echo "[SKENARIO 5] Uji Penolakan ID Anggota Soft-Delete\n";
$trashCsvPath = __DIR__.'/test_trash.csv';
$fp = fopen($trashCsvPath, 'w');
fputcsv($fp, ['ID Anggota', 'Kelompok (Wajib)', 'Nama Lengkap (Wajib)', 'Nama Panggilan', 'Tanggal Lahir', 'L/P (Wajib)']);
fputcsv($fp, ['M_TRASHED_099', 'KELOMPOK MELATI', 'Coba Pakai ID Sampah', '', '', 'L']);
fclose($fp);

$resTrash = $parser->parse($trashCsvPath);
unlink($trashCsvPath);

$pass5 = ($resTrash['invalid_count'] === 1) &&
         str_contains($resTrash['invalid_rows'][0]['error_message'], 'berada di tong sampah');
echo '- Error Message: '.$resTrash['invalid_rows'][0]['error_message']."\n";
echo '- Status: '.($pass5 ? 'PASSED' : 'FAILED')."\n\n";

// -------------------------------------------------------------
// Skenario 6: Simulasi Loop Perbaikan Nyata (Export -> Fix -> Re-Upload)
// -------------------------------------------------------------
echo "[SKENARIO 6] Uji Loop Perbaikan Nyata (Error -> Export CSV -> Edit Excel -> Re-Upload)\n";
// Tahap 1: Ada 2 data error
$initialErrors = [
    [
        'excel_row' => 2,
        'member_code' => '',
        'full_name' => 'Budi Rusak',
        'nick_name' => '',
        'group_name' => 'KELOMPOK_SALAH',
        'gender_label' => 'L',
        'birth_date' => '',
        'error_message' => "[Kelompok]: Kelompok 'KELOMPOK_SALAH' tidak ditemukan di master data",
        'raw_data' => [
            'id_anggota' => '',
            'kelompok_wajib' => 'KELOMPOK_SALAH',
            'nama_lengkap_wajib' => 'Budi Rusak',
            'nama_panggilan' => '',
            'tanggal_lahir' => '',
            'lp_wajib' => 'L',
        ],
    ],
    [
        'excel_row' => 3,
        'member_code' => '',
        'full_name' => 'Siti Gender Salah',
        'nick_name' => '',
        'group_name' => 'KELOMPOK MELATI',
        'gender_label' => 'SALAH',
        'birth_date' => '10/05/1992',
        'error_message' => "[Jenis Kelamin]: Format 'SALAH' tidak valid. Wajib diisi 'L' atau 'P'",
        'raw_data' => [
            'id_anggota' => '',
            'kelompok_wajib' => 'KELOMPOK MELATI',
            'nama_lengkap_wajib' => 'Siti Gender Salah',
            'nama_panggilan' => '',
            'tanggal_lahir' => '10/05/1992',
            'lp_wajib' => 'SALAH',
        ],
    ],
];

// Tahap 2: Ekspor CSV Data Bermasalah
$loopExportPath = \Illuminate\Support\Facades\Storage::disk('local')->path('loop_error_test.csv');
Excel::store(new MemberImportErrorsExport($initialErrors), 'loop_error_test.csv', 'local');

// Tahap 3: Simulasi pengguna membuka file di Excel dan memperbaiki sel
$csvRows = array_map('str_getcsv', explode("\r\n", trim(file_get_contents($loopExportPath))));
$csvRows[1][1] = 'KELOMPOK MELATI'; // Memperbaiki nama kelompok
$csvRows[2][5] = 'P';               // Memperbaiki jenis kelamin

// Tulis kembali hasil edit pengguna (kolom Keterangan Error tetap ada!)
$fp = fopen($loopExportPath, 'w');
foreach ($csvRows as $r) {
    fputcsv($fp, $r);
}
fclose($fp);

// Tahap 4: Pengguna mengunggah file hasil perbaikan tersebut
$resLoop = $parser->parse($loopExportPath);
unlink($loopExportPath);

$pass6 = ($resLoop['total_rows'] === 2) && ($resLoop['valid_count'] === 2) && ($resLoop['invalid_count'] === 0);
echo "- Sebelum Perbaikan: 2 Error\n";
echo "- Setelah Diedit & Diunggah Ulang: Total {$resLoop['total_rows']} | Valid: {$resLoop['valid_count']} | Error: {$resLoop['invalid_count']}\n";
echo '- Status: '.($pass6 ? 'PASSED' : 'FAILED')."\n\n";

// -------------------------------------------------------------
// Evaluasi Keseluruhan
// -------------------------------------------------------------
echo "===============================================================\n";
$allPassed = $pass1 && $pass2 && $pass3 && $pass4 && $pass5 && $pass6;

if ($allPassed) {
    echo ">>> HASIL AKHIR: 6 DARI 6 SKENARIO EDGE CASES 100% SUKSES! <<<\n";
    echo "===============================================================\n";
    exit(0);
} else {
    echo ">>> HASIL AKHIR: ADA SKENARIO YANG GAGAL! <<<\n";
    echo "===============================================================\n";
    exit(1);
}
