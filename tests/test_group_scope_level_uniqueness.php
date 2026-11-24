<?php

/**
 * inTime - Automated Test Suite: Group Name Uniqueness per Scope & Level
 *
 * Verifies:
 * 1. Duplicate group name on SAME level within SAME scope is REJECTED.
 * 2. Same group name on DIFFERENT level within SAME scope is ALLOWED.
 * 3. Same group name on SAME level in DIFFERENT scope is ALLOWED.
 * 4. Editing group with unchanged name is ALLOWED (ignorable ID).
 * 5. Bulk Import Grup enforces level-based name uniqueness in file & DB.
 * 6. Bulk Import Anggota unambiguously resolves kelompok in user's scope.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Level;
use App\Models\User;
use App\Services\Groups\GroupImportParser;
use App\Services\Members\MemberImportParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

echo "====================================================================\n";
echo "  TEST SUITE: GROUP SCOPE & LEVEL NAME UNIQUENESS VERIFICATION      \n";
echo "====================================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function assertCondition(bool $condition, string $testName, string $failureDetails = ''): void
{
    global $passedCount, $failedCount;
    if ($condition) {
        echo ' [PASS] '.$testName."\n";
        $passedCount++;
    } else {
        echo ' [FAIL] '.$testName."\n";
        if ($failureDetails) {
            echo '        Details: '.$failureDetails."\n";
        }
        $failedCount++;
    }
}

DB::beginTransaction();

try {
    // 1. Setup Levels
    $levelDaerah = Level::firstOrCreate(['name' => 'DAERAH'], ['level_number' => 3, 'code' => 'DRH']);
    $levelDesa = Level::firstOrCreate(['name' => 'DESA'], ['level_number' => 2, 'code' => 'DSA']);
    $levelKelompok = Level::firstOrCreate(['name' => 'KELOMPOK'], ['level_number' => 1, 'code' => 'KLP']);

    // 2. Setup Hierarchy Branches
    // Branch 1: Daerah Alpha
    $daerahAlpha = Group::create([
        'name' => 'DAERAH_ALPHA_'.uniqid(),
        'level_id' => $levelDaerah->id,
        'parent_id' => null,
        'status' => true,
    ]);

    $desaAlpha1 = Group::create([
        'name' => 'DESA_ALPHA_ONE_'.uniqid(),
        'level_id' => $levelDesa->id,
        'parent_id' => $daerahAlpha->id,
        'status' => true,
    ]);

    $desaAlpha2 = Group::create([
        'name' => 'DESA_ALPHA_TWO_'.uniqid(),
        'level_id' => $levelDesa->id,
        'parent_id' => $daerahAlpha->id,
        'status' => true,
    ]);

    // Pre-existing Kelompok in Desa Alpha 1: KELOMPOK_MAWAR
    $kelompokMawarAlpha1 = Group::create([
        'name' => 'KELOMPOK_MAWAR',
        'level_id' => $levelKelompok->id,
        'parent_id' => $desaAlpha1->id,
        'code' => 'MWR1',
        'status' => true,
    ]);

    // Branch 2: Daerah Beta (Separate Scope)
    $daerahBeta = Group::create([
        'name' => 'DAERAH_BETA_'.uniqid(),
        'level_id' => $levelDaerah->id,
        'parent_id' => null,
        'status' => true,
    ]);

    $desaBeta1 = Group::create([
        'name' => 'DESA_BETA_ONE_'.uniqid(),
        'level_id' => $levelDesa->id,
        'parent_id' => $daerahBeta->id,
        'status' => true,
    ]);

    // 3. Setup Users
    $userAlpha = User::create([
        'name' => 'Operator Alpha',
        'email' => 'operator_alpha_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::OPERATOR,
        'group_id' => $daerahAlpha->id,
    ]);
    $userAlpha->assignRole('operator');

    $userBeta = User::create([
        'name' => 'Operator Beta',
        'email' => 'operator_beta_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::OPERATOR,
        'group_id' => $daerahBeta->id,
    ]);
    $userBeta->assignRole('operator');

    echo "--- Skenario 1: Model Helper & Form Validation (Group::isNameDuplicateInScope) ---\n";

    // 1.1: Sama nama, sama level, parent berbeda dalam 1 scope Daerah Alpha -> HARUS DUPLIKAT (True)
    $isDupSameLevelSameScope = Group::isNameDuplicateInScope(
        'KELOMPOK_MAWAR',
        $levelKelompok->id,
        $desaAlpha2->id,
        null,
        $userAlpha
    );
    assertCondition(
        $isDupSameLevelSameScope === true,
        "Duplicate detected for same name ('KELOMPOK_MAWAR') & same level (Kelompok) across different parents in same scope"
    );

    // 1.2: Sama nama, level berbeda (Desa vs Kelompok) dalam 1 scope Daerah Alpha -> TIDAK DUPLIKAT (False)
    $isDupDiffLevelSameScope = Group::isNameDuplicateInScope(
        'KELOMPOK_MAWAR',
        $levelDesa->id,
        $daerahAlpha->id,
        null,
        $userAlpha
    );
    assertCondition(
        $isDupDiffLevelSameScope === false,
        "Same name ('KELOMPOK_MAWAR') on DIFFERENT level (Desa vs Kelompok) in same scope is ALLOWED"
    );

    // 1.3: Sama nama, sama level, tetapi di scope berbeda (Daerah Beta) -> TIDAK DUPLIKAT (False)
    $isDupDiffScope = Group::isNameDuplicateInScope(
        'KELOMPOK_MAWAR',
        $levelKelompok->id,
        $desaBeta1->id,
        null,
        $userBeta
    );
    assertCondition(
        $isDupDiffScope === false,
        "Same name ('KELOMPOK_MAWAR') & same level in DIFFERENT scope (Daerah Beta) is ALLOWED"
    );

    // 1.4: Edit kelompok yang sama tanpa mengubah nama (ignoreId) -> TIDAK DUPLIKAT (False)
    $isDupSelfEdit = Group::isNameDuplicateInScope(
        'KELOMPOK_MAWAR',
        $levelKelompok->id,
        $desaAlpha1->id,
        $kelompokMawarAlpha1->id,
        $userAlpha
    );
    assertCondition(
        $isDupSelfEdit === false,
        "Editing existing record ('KELOMPOK_MAWAR') with self ignoreId is ALLOWED"
    );

    echo "\n--- Skenario 2: Bulk Import Grup (GroupImportParser) ---\n";

    $tmpDir = sys_get_temp_dir();

    // 2.1: File dengan 2 baris sama nama & sama level di bawah parent berbeda
    $csvFileInternalDup = $tmpDir.'/test_import_internal_dup_'.uniqid().'.csv';
    $contentInternal = "Tingkat Grup (Wajib),Nama Grup (Wajib),Induk Grup,Kode Grup (Opsional)\n";
    $contentInternal .= "KELOMPOK,KELOMPOK_MELATI,{$desaAlpha1->name},01\n";
    $contentInternal .= "KELOMPOK,KELOMPOK_MELATI,{$desaAlpha2->name},02\n";
    file_put_contents($csvFileInternalDup, $contentInternal);

    $parserAlpha = new GroupImportParser($userAlpha);
    $resInternal = $parserAlpha->parse($csvFileInternalDup);

    assertCondition(
        $resInternal['valid_count'] === 1 && $resInternal['invalid_count'] === 1,
        'Internal duplicate in file on same level: 1 valid, 1 invalid',
        "Valid: {$resInternal['valid_count']}, Invalid: {$resInternal['invalid_count']}"
    );
    $errInternal = $resInternal['invalid_rows'][0]['error_message'] ?? '';
    assertCondition(
        str_contains($errInternal, "sudah digunakan pada tingkat 'KELOMPOK' di baris 2"),
        'Second duplicate row gets informative duplicate message referencing previous row',
        'Actual error: '.$errInternal
    );

    // 2.2: File mencoba membuat KELOMPOK_MAWAR di bawah Desa Alpha 2 (sudah ada di DB di bawah Desa Alpha 1)
    $csvFileDbConflict = $tmpDir.'/test_import_db_conflict_'.uniqid().'.csv';
    $contentDbConflict = "Tingkat Grup (Wajib),Nama Grup (Wajib),Induk Grup,Kode Grup (Opsional)\n";
    $contentDbConflict .= "KELOMPOK,KELOMPOK_MAWAR,{$desaAlpha2->name},88\n";
    file_put_contents($csvFileDbConflict, $contentDbConflict);

    $resDbConflict = $parserAlpha->parse($csvFileDbConflict);
    assertCondition(
        $resDbConflict['invalid_count'] === 1,
        "Importing duplicate group name ('KELOMPOK_MAWAR') on same level under different parent is REJECTED"
    );
    $errDbConflict = $resDbConflict['invalid_rows'][0]['error_message'] ?? '';
    assertCondition(
        str_contains($errDbConflict, "sudah terdaftar pada tingkat 'KELOMPOK' di bawah induk lain"),
        'Error message identifies cross-parent conflict on same level in scope',
        'Actual error: '.$errDbConflict
    );

    // 2.3: File dengan nama sama tetapi level berbeda (DESA_SEROJA dan KELOMPOK_SEROJA) -> Keduanya valid!
    $csvFileDiffLevel = $tmpDir.'/test_import_diff_level_'.uniqid().'.csv';
    $contentDiffLevel = "Tingkat Grup (Wajib),Nama Grup (Wajib),Induk Grup,Kode Grup (Opsional)\n";
    $contentDiffLevel .= "DESA,GRUP_SEROJA,{$daerahAlpha->name},10\n";
    $contentDiffLevel .= "KELOMPOK,GRUP_SEROJA,GRUP_SEROJA,01\n";
    file_put_contents($csvFileDiffLevel, $contentDiffLevel);

    $resDiffLevel = $parserAlpha->parse($csvFileDiffLevel);
    assertCondition(
        $resDiffLevel['valid_count'] === 2 && $resDiffLevel['invalid_count'] === 0,
        "Same name ('GRUP_SEROJA') on different levels (Desa & Kelompok) is 100% VALID",
        "Valid count: {$resDiffLevel['valid_count']}, Invalid count: {$resDiffLevel['invalid_count']}"
    );

    // 2.4: Operator Beta mengimpor KELOMPOK_MAWAR di Daerah Beta (beda scope) -> Valid!
    $csvFileBetaMawar = $tmpDir.'/test_import_beta_mawar_'.uniqid().'.csv';
    $contentBeta = "Tingkat Grup (Wajib),Nama Grup (Wajib),Induk Grup,Kode Grup (Opsional)\n";
    $contentBeta .= "KELOMPOK,KELOMPOK_MAWAR,{$desaBeta1->name},01\n";
    file_put_contents($csvFileBetaMawar, $contentBeta);

    $parserBeta = new GroupImportParser($userBeta);
    $resBeta = $parserBeta->parse($csvFileBetaMawar);
    assertCondition(
        $resBeta['valid_count'] === 1 && $resBeta['invalid_count'] === 0,
        "Same name ('KELOMPOK_MAWAR') imported by Operator Beta in separate scope is VALID"
    );

    echo "\n--- Skenario 3: Bulk Import Anggota (MemberImportParser) ---\n";

    // 3.1: Operator Alpha mengimpor anggota ke KELOMPOK_MAWAR
    $csvMemberAlpha = $tmpDir.'/test_import_member_alpha_'.uniqid().'.csv';
    $contentMemAlpha = "ID Anggota,Kelompok (Wajib),Nama Lengkap (Wajib),Nama Panggilan,Tanggal Lahir,L/P (Wajib)\n";
    $contentMemAlpha .= ",KELOMPOK_MAWAR,AHMAD MAULANA,AHMAD,10/05/1998,L\n";
    file_put_contents($csvMemberAlpha, $contentMemAlpha);

    $memberParserAlpha = new MemberImportParser($userAlpha);
    $resMemAlpha = $memberParserAlpha->parse($csvMemberAlpha);

    assertCondition(
        $resMemAlpha['valid_count'] === 1,
        'Member import by Operator Alpha targeting KELOMPOK_MAWAR is VALID'
    );
    assertCondition(
        $resMemAlpha['valid_rows'][0]['group_id'] === $kelompokMawarAlpha1->id,
        "Member import resolved group_id exactly to kelompokMawarAlpha1 ID ({$kelompokMawarAlpha1->id})"
    );

    // 3.2: Buat Desa dengan nama KELOMPOK_DAHLIA dan Kelompok dengan nama KELOMPOK_DAHLIA di bawah Daerah Alpha
    $desaDahlia = Group::create([
        'name' => 'DAHLIA',
        'level_id' => $levelDesa->id,
        'parent_id' => $daerahAlpha->id,
        'code' => 'DHL',
        'status' => true,
    ]);
    $kelompokDahlia = Group::create([
        'name' => 'DAHLIA',
        'level_id' => $levelKelompok->id,
        'parent_id' => $desaDahlia->id,
        'code' => '01',
        'status' => true,
    ]);

    // Operator Alpha mengimpor anggota dengan kelompok 'DAHLIA'
    $csvMemberDahlia = $tmpDir.'/test_import_member_dahlia_'.uniqid().'.csv';
    $contentMemDahlia = "ID Anggota,Kelompok (Wajib),Nama Lengkap (Wajib),Nama Panggilan,Tanggal Lahir,L/P (Wajib)\n";
    $contentMemDahlia .= ",DAHLIA,SITI AMINAH,SITI,12/08/2000,P\n";
    file_put_contents($csvMemberDahlia, $contentMemDahlia);

    Cache::forget("member_import_group_map_u{$userAlpha->id}");
    $userAlpha->flushManageableGroupCache();
    $memberParserDahlia = new MemberImportParser($userAlpha);
    $resMemDahlia = $memberParserDahlia->parse($csvMemberDahlia);

    assertCondition(
        $resMemDahlia['valid_count'] === 1,
        "Member import with identical name on Desa & Kelompok ('DAHLIA') is VALID",
        'Error: '.($resMemDahlia['invalid_rows'][0]['error_message'] ?? 'none').' | GroupID resolved: '.var_export($resMemDahlia['all_rows'][0]['group_id'] ?? null, true)
    );
    if (! empty($resMemDahlia['valid_rows'])) {
        assertCondition(
            $resMemDahlia['valid_rows'][0]['group_id'] === $kelompokDahlia->id,
            "Member import unambiguously prioritized KELOMPOK (level 1) over DESA (level 2) [Resolved ID: {$kelompokDahlia->id}]"
        );
    }

    // 3.3: Operator Beta mencoba mengimpor anggota ke kelompok milik Daerah Alpha (di luar scope)
    $csvMemberBetaCross = $tmpDir.'/test_import_member_cross_'.uniqid().'.csv';
    $contentMemBetaCross = "ID Anggota,Kelompok (Wajib),Nama Lengkap (Wajib),Nama Panggilan,Tanggal Lahir,L/P (Wajib)\n";
    $contentMemBetaCross .= ",{$desaAlpha1->name},BUDI SANTOSO,BUDI,01/01/1995,L\n";
    file_put_contents($csvMemberBetaCross, $contentMemBetaCross);

    $memberParserBeta = new MemberImportParser($userBeta);
    $resMemBetaCross = $memberParserBeta->parse($csvMemberBetaCross);

    assertCondition(
        $resMemBetaCross['invalid_count'] === 1,
        'Member import by Operator Beta targeting group in Daerah Alpha is REJECTED'
    );
    $errMemBeta = $resMemBetaCross['invalid_rows'][0]['error_message'] ?? '';
    assertCondition(
        str_contains($errMemBeta, 'tidak ditemukan di master data'),
        "Error message confirms group outside user's scope is invisible",
        'Actual error: '.$errMemBeta
    );

    // Clean up temporary files
    @unlink($csvFileInternalDup);
    @unlink($csvFileDbConflict);
    @unlink($csvFileDiffLevel);
    @unlink($csvFileBetaMawar);
    @unlink($csvMemberAlpha);
    @unlink($csvMemberDahlia);
    @unlink($csvMemberBetaCross);

} finally {
    // Rollback test database transaction
    DB::rollBack();
    echo "\n[INFO] Test transaction rolled back. Database remains clean.\n";
}

echo "\n====================================================================\n";
echo "  TEST SUMMARY: {$passedCount} PASSED, {$failedCount} FAILED        \n";
echo "====================================================================\n";

if ($failedCount > 0) {
    exit(1);
}
exit(0);
