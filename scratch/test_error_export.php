<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exports\MemberImportErrorsExport;
use App\Services\Members\MemberImportParser;
use Maatwebsite\Excel\Facades\Excel;

echo "=== MEMULAI PENGUJIAN GENERATOR CSV DATA BERMASALAH (FASE 4) ===\n\n";

// Set database ke sqlite in-memory agar independen dan portabel
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
config(['cache.default' => 'array']);
\Illuminate\Support\Facades\DB::purge();
\Illuminate\Support\Facades\Cache::flush();

// Siapkan data invalid rows dummy
$dummyInvalidRows = [
    [
        'excel_row' => 4,
        'member_code' => null,
        'full_name' => '',
        'nick_name' => 'NoName',
        'group_name' => 'KELOMPOK MAWAR',
        'gender_label' => 'L',
        'birth_date' => '10/10/2000',
        'error_message' => '[Nama Lengkap]: Wajib diisi',
        'raw_data' => [
            'id_anggota' => '',
            'kelompok_wajib' => 'KELOMPOK MAWAR',
            'nama_lengkap_wajib' => '',
            'nama_panggilan' => 'NoName',
            'tanggal_lahir' => '10/10/2000',
            'lp_wajib' => 'L',
        ],
    ],
    [
        'excel_row' => 5,
        'member_code' => null,
        'full_name' => 'JOKO WIDODO',
        'nick_name' => '',
        'group_name' => 'KELOMPOK_FIKTIF',
        'gender_label' => 'L',
        'birth_date' => '01/01/1990',
        'error_message' => "[Kelompok]: Kelompok 'KELOMPOK_FIKTIF' tidak ditemukan di master data",
        'raw_data' => [
            'id_anggota' => '',
            'kelompok_wajib' => 'KELOMPOK_FIKTIF',
            'nama_lengkap_wajib' => 'JOKO WIDODO',
            'nama_panggilan' => '',
            'tanggal_lahir' => '01/01/1990',
            'lp_wajib' => 'L',
        ],
    ],
    [
        'excel_row' => 6,
        'member_code' => null,
        'full_name' => 'RINA MARLINA',
        'nick_name' => 'RINA',
        'group_name' => 'KELOMPOK MAWAR',
        'gender_label' => 'X',
        'birth_date' => '20/05/1998',
        'error_message' => "[Jenis Kelamin]: Format 'X' tidak valid. Wajib diisi 'L' atau 'P'",
        'raw_data' => [
            'id_anggota' => '',
            'kelompok_wajib' => 'KELOMPOK MAWAR',
            'nama_lengkap_wajib' => 'RINA MARLINA',
            'nama_panggilan' => 'RINA',
            'tanggal_lahir' => '20/05/1998',
            'lp_wajib' => 'X',
        ],
    ],
];

// Ekspor ke file CSV lokal
$exportPath = 'temp_test_errors.csv';
$fullExportPath = \Illuminate\Support\Facades\Storage::disk('local')->path($exportPath);

if (file_exists($fullExportPath)) {
    unlink($fullExportPath);
}

Excel::store(new MemberImportErrorsExport($dummyInvalidRows), $exportPath, 'local');

echo "File CSV berhasil digenerate di: {$fullExportPath}\n";

if (!file_exists($fullExportPath)) {
    echo "ERROR: File CSV tidak terbentuk!\n";
    exit(1);
}

// Baca konten CSV
$content = file_get_contents($fullExportPath);
echo "\n--- ISI BERKAS CSV ---\n";
echo $content . "\n";

$rows = array_map('str_getcsv', explode("\r\n", trim($content)));
$header = $rows[0];

echo "--- VALIDASI STRUKTUR HEADER ---\n";
echo "Jumlah Kolom Header: " . count($header) . " (Ekspektasi: 7)\n";
echo "Nama Kolom Terakhir: '" . trim($header[6]) . "' (Ekspektasi: 'Keterangan Error')\n";

$isHeaderValid = (count($header) === 7) && (trim($header[6]) === 'Keterangan Error');
$isRowCountValid = (count($rows) === 4); // 1 header + 3 data rows

if (!$isHeaderValid || !$isRowCountValid) {
    echo "ERROR: Struktur CSV tidak sesuai ekspektasi!\n";
    exit(1);
}

// Uji parser membaca ulang file ini
echo "\n--- PENGUJIAN RE-UPLOAD: MEMBER IMPORT PARSER MEMBACA ULANG CSV INI ---\n";
\Illuminate\Support\Facades\Schema::create('groups', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->timestamps();
    $table->softDeletes();
});
\Illuminate\Support\Facades\Schema::create('age_groups', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->integer('min_age')->nullable();
    $table->integer('max_age')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
\Illuminate\Support\Facades\Schema::create('members', function ($table) {
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
    $table->timestamps();
    $table->softDeletes();
});

\App\Models\Group::create(['name' => 'KELOMPOK MAWAR']);
\App\Models\AgeGroup::create(['name' => 'Pra Nikah', 'min_age' => null, 'max_age' => null]);

$parser = new MemberImportParser();
$reparseResult = $parser->parse($fullExportPath);

echo "Total Baris Dibaca Parser: " . $reparseResult['total_rows'] . " (Ekspektasi: 3)\n";
echo "Data Bermasalah Terdeteksi: " . $reparseResult['invalid_count'] . " (Ekspektasi: 3)\n";

// Bersihkan file uji
unlink($fullExportPath);

if ($reparseResult['total_rows'] === 3 && $reparseResult['invalid_count'] === 3) {
    echo "\n>>> STATUS: SEMUA TES FASE 4 BERHASIL 100%! <<<\n";
    exit(0);
} else {
    echo "\n>>> STATUS: ADA ASERSi RE-PARSE YANG GAGAL! <<<\n";
    exit(1);
}
