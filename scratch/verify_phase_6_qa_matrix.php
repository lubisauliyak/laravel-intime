<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use App\Services\Members\MemberImportParser;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assert_it(string $name, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "\033[32m[PASS]\033[0m {$name}\n";
    } else {
        $failed++;
        echo "\033[31m[FAIL]\033[0m {$name} - {$details}\n";
    }
}

echo "\n======================================================\n";
echo "   FASE 6.2 - SPREADSHEET VARIATION QA MATRIX        \n";
echo "======================================================\n\n";

DB::beginTransaction();

try {
    $lvlDae = Level::where('code', 'DAE')->first() ?? Level::where('level_number', 3)->first() ?? Level::firstOrCreate(['code' => 'DAE'], ['name' => 'DAERAH', 'level_number' => 3]);
    $lvlDes = Level::where('code', 'DES')->first() ?? Level::where('level_number', 2)->first() ?? Level::firstOrCreate(['code' => 'DES'], ['name' => 'DESA', 'level_number' => 2]);
    $lvlKel = Level::where('code', 'KEL')->first() ?? Level::where('level_number', 1)->first() ?? Level::firstOrCreate(['code' => 'KEL'], ['name' => 'KELOMPOK', 'level_number' => 1]);

    // 1. Kelompok Lengkap: Desa 91 + Kelompok 82 (Prefix: 9182)
    $desA = Group::create(['name' => 'DESA QA MATRIX 91', 'level_id' => $lvlDes->id, 'code' => '91']);
    $kelA = Group::create(['name' => 'KELOMPOK QA MATRIX 82', 'level_id' => $lvlKel->id, 'parent_id' => $desA->id, 'code' => '82']);

    // 2. Kelompok Tanpa Kode: Desa null + Kelompok null
    $desB = Group::create(['name' => 'DESA TANPA KODE QA', 'level_id' => $lvlDes->id, 'code' => null]);
    $kelB = Group::create(['name' => 'KELOMPOK TANPA KODE QA', 'level_id' => $lvlKel->id, 'parent_id' => $desB->id, 'code' => null]);

    // 3. Kelompok Rantai Putus: Daerah 05 + Desa null + Kelompok 03
    $daeC = Group::create(['name' => 'DAERAH QA 05', 'level_id' => $lvlDae->id, 'code' => '05']);
    $desC = Group::create(['name' => 'DESA QA NULL', 'level_id' => $lvlDes->id, 'parent_id' => $daeC->id, 'code' => null]);
    $kelC = Group::create(['name' => 'KELOMPOK QA 03', 'level_id' => $lvlKel->id, 'parent_id' => $desC->id, 'code' => '03']);

    // Pre-create existing member for update test
    $existingMember = Member::create([
        'member_code' => '9182001',
        'full_name' => 'DATA LAMA SEBELUM UPDATE',
        'group_id' => $kelA->id,
        'birth_date' => '1990-05-15',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);

    // Matrix CSV content
    $csvContent = "nama_lengkap_wajib,kelompok_wajib,lp_wajib,id_anggota,tanggal_lahir\n" .
        "ANGGOTA AUTO ID,KELOMPOK QA MATRIX 82,L,,20/01/1998\n" .
        "ANGGOTA NO CODE,KELOMPOK TANPA KODE QA,P,,21/02/1997\n" .
        "DATA BARU UPDATE,KELOMPOK QA MATRIX 82,L,9182001,22/03/1996\n" .
        "ANGGOTA BROKEN CHAIN,KELOMPOK QA 03,L,,23/04/1995\n" .
        "ANGGOTA MANUAL BARU,KELOMPOK QA MATRIX 82,P,MANUAL-2026,24/05/1994\n" .
        "ANGGOTA FORMAT TGL,KELOMPOK QA MATRIX 82,L,,1992-12-31\n";

    $tempCsvPath = storage_path('app/public/test_matrix_fase6.csv');
    file_put_contents($tempCsvPath, $csvContent);

    $parser = new MemberImportParser();
    $result = $parser->parse($tempCsvPath);

    // Matrix 1: Baris tanpa ID di kelompok berkode lengkap
    $row1 = $result['all_rows'][0];
    assert_it('Matrix 1: Baris tanpa ID di kelompok lengkap valid', $row1['is_valid'] === true);
    assert_it('Matrix 1: Status ID adalah auto_id', $row1['id_status'] === 'auto_id');
    assert_it('Matrix 1: Prefix terhitung adalah 9182', $row1['calculated_prefix'] === '9182');

    // Matrix 2: Baris tanpa ID di kelompok tanpa kode sama sekali
    $row2 = $result['all_rows'][1];
    assert_it('Matrix 2: Baris di kelompok tanpa kode tetap valid', $row2['is_valid'] === true);
    assert_it('Matrix 2: Status ID adalah no_code', $row2['id_status'] === 'no_code');
    assert_it('Matrix 2: ID anggota dibiarkan kosong', empty($row2['member_code']));

    // Matrix 3: Baris dengan ID yang sudah ada (update)
    $row3 = $result['all_rows'][2];
    assert_it('Matrix 3: Baris dengan ID terdaftar valid', $row3['is_valid'] === true);
    assert_it('Matrix 3: Status ID adalah update', $row3['id_status'] === 'update');
    assert_it('Matrix 3: is_update bernilai true', $row3['is_update'] === true);

    // Matrix 4: Baris di kelompok yang rantai kodenya bolong
    $row4 = $result['all_rows'][3];
    assert_it('Matrix 4: Baris di kelompok rantai putus otomatis INVALID', $row4['is_valid'] === false);
    assert_it('Matrix 4: Status ID adalah broken_chain', $row4['id_status'] === 'broken_chain');
    assert_it('Matrix 4: Pesan error menyebutkan rantai kode terputus', str_contains($row4['error_message'] ?? '', 'Rantai kode terputus'));

    // Matrix 5: Baris dengan ID manual baru
    $row5 = $result['all_rows'][4];
    assert_it('Matrix 5: Baris dengan ID manual baru valid', $row5['is_valid'] === true);
    assert_it('Matrix 5: Status ID adalah manual_id', $row5['id_status'] === 'manual_id');
    assert_it('Matrix 5: ID manual dipertahankan', $row5['member_code'] === 'MANUAL-2026');

    // Matrix 6: Baris dengan format tanggal ISO (YYYY-MM-DD)
    $row6 = $result['all_rows'][5];
    assert_it('Matrix 6: Format tanggal ISO terbaca valid', $row6['is_valid'] === true);
    assert_it('Matrix 6: Tanggal terkonversi dengan benar', !empty($row6['birth_date']));

    if (file_exists($tempCsvPath)) {
        unlink($tempCsvPath);
    }

} finally {
    DB::rollBack();
}

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
}
