<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use App\Services\Members\MemberImportParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
echo "   FASE 5 - BULK IMPORT AUTO-ID & INTEGRATION TEST     \n";
echo "======================================================\n\n";

DB::beginTransaction();

try {
    $lvlDae = Level::where('code', 'DAE')->first() ?? Level::where('level_number', 3)->first() ?? Level::firstOrCreate(['code' => 'DAE'], ['name' => 'DAERAH', 'level_number' => 3]);
    $lvlDes = Level::where('code', 'DES')->first() ?? Level::where('level_number', 2)->first() ?? Level::firstOrCreate(['code' => 'DES'], ['name' => 'DESA', 'level_number' => 2]);
    $lvlKel = Level::where('code', 'KEL')->first() ?? Level::where('level_number', 1)->first() ?? Level::firstOrCreate(['code' => 'KEL'], ['name' => 'KELOMPOK', 'level_number' => 1]);

    // 1. Group Valid: Desa 55 + Kelompok 44 (prefix: 5544)
    $desValid = Group::create(['name' => 'DESA VALID 55', 'level_id' => $lvlDes->id, 'code' => '55']);
    $kelValid = Group::create(['name' => 'KELOMPOK VALID 44', 'level_id' => $lvlKel->id, 'parent_id' => $desValid->id, 'code' => '44']);

    // 2. Group Broken: Daerah 01 + Desa null + Kelompok 07 (broken chain)
    $daeBroken = Group::create(['name' => 'DAERAH BROKEN 01', 'level_id' => $lvlDae->id, 'code' => '01']);
    $desBroken = Group::create(['name' => 'DESA BROKEN NULL', 'level_id' => $lvlDes->id, 'parent_id' => $daeBroken->id, 'code' => null]);
    $kelBroken = Group::create(['name' => 'KELOMPOK BROKEN 07', 'level_id' => $lvlKel->id, 'parent_id' => $desBroken->id, 'code' => '07']);

    // 3. Group NoCode: Desa null + Kelompok null
    $desNoCode = Group::create(['name' => 'DESA KOSONG', 'level_id' => $lvlDes->id, 'code' => null]);
    $kelNoCode = Group::create(['name' => 'KELOMPOK KOSONG', 'level_id' => $lvlKel->id, 'parent_id' => $desNoCode->id, 'code' => null]);

    // Pre-create an existing member for testing update
    $existing = Member::create([
        'member_code' => '5544001',
        'full_name' => 'MEMBER EKSIS LAMA',
        'group_id' => $kelValid->id,
        'birth_date' => '1990-01-01',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);

    // Create a temporary CSV file for testing MemberImportParser
    $csvContent = "nama_lengkap_wajib,kelompok_wajib,lp_wajib,id_anggota,tanggal_lahir\n" .
        "ANTO OTOMATIS,KELOMPOK VALID 44,L,,15/01/1995\n" .
        "BUDI UPDATE,KELOMPOK VALID 44,L,5544001,16/02/1994\n" .
        "CITRA MANUAL,KELOMPOK VALID 44,P,MANUAL-888,17/03/1993\n" .
        "DEDI NOCODE,KELOMPOK KOSONG,L,,18/04/1992\n" .
        "EKO BROKEN,KELOMPOK BROKEN 07,L,,19/05/1991\n";

    $tempCsvPath = storage_path('app/public/test_import_fase5.csv');
    file_put_contents($tempCsvPath, $csvContent);

    // Run Parser
    $parser = new MemberImportParser();
    $result = $parser->parse($tempCsvPath);

    assert_it('Test 1: Total rows parsed is 5', $result['total_rows'] === 5);
    assert_it('Test 2: Valid rows count is 4', $result['valid_count'] === 4);
    assert_it('Test 3: Invalid rows count is 1', $result['invalid_count'] === 1);

    $rows = $result['all_rows'];

    // Row 1: Anto Otomatis
    assert_it('Test 4.1: Row 1 is auto_id', $rows[0]['id_status'] === 'auto_id');
    assert_it('Test 4.2: Row 1 prefix is 5544', $rows[0]['calculated_prefix'] === '5544');
    assert_it('Test 4.3: Row 1 is valid', $rows[0]['is_valid'] === true);

    // Row 2: Budi Update
    assert_it('Test 5.1: Row 2 is update', $rows[1]['id_status'] === 'update');
    assert_it('Test 5.2: Row 2 is_update is true', $rows[1]['is_update'] === true);

    // Row 3: Citra Manual
    assert_it('Test 6.1: Row 3 is manual_id', $rows[2]['id_status'] === 'manual_id');
    assert_it('Test 6.2: Row 3 member_code is MANUAL-888', $rows[2]['member_code'] === 'MANUAL-888');

    // Row 4: Dedi NoCode
    assert_it('Test 7.1: Row 4 is no_code', $rows[3]['id_status'] === 'no_code');
    assert_it('Test 7.2: Row 4 member_code is null', empty($rows[3]['member_code']));
    assert_it('Test 7.3: Row 4 is valid', $rows[3]['is_valid'] === true);

    // Row 5: Eko Broken (Broken Chain)
    assert_it('Test 8.1: Row 5 is broken_chain', $rows[4]['id_status'] === 'broken_chain');
    assert_it('Test 8.2: Row 5 is invalid', $rows[4]['is_valid'] === false);
    assert_it('Test 8.3: Row 5 has error message mentioning broken chain', str_contains($rows[4]['error_message'] ?? '', 'Rantai kode terputus'));

    // Test inserting the auto_id row into DB via MemberObserver
    $newMember1 = new Member([
        'full_name' => $rows[0]['full_name'],
        'group_id' => $rows[0]['group_id'],
        'gender' => $rows[0]['gender'],
        'birth_date' => $rows[0]['birth_date'],
        'member_code' => null,
        'status' => true,
        'is_permanent' => true,
    ]);
    $newMember1->save();

    // Since 5544001 already existed, next increment should be 5544002!
    assert_it('Test 9: Auto-ID member received next sequence 5544002', $newMember1->member_code === '5544002', 'Got: ' . ($newMember1->member_code ?? 'null'));
    assert_it('Test 10: Member is_permanent is true', $newMember1->is_permanent === true);

    // Clean up temporary file
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
