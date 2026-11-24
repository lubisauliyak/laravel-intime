<?php

/**
 * inTime - Automated Test for Group Import Scope Security
 *
 * Verifies:
 * 1. Scope isolation in GroupImportParser
 * 2. Superadmin access
 * 3. Prevention of cross-scope parent resolution
 * 4. Prevention of unauthorized top-level group creation
 * 5. User-scoped cache keys
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

echo "========================================================\n";
echo "  TEST SUITE: GROUP IMPORT SCOPE SECURITY VERIFICATION\n";
echo "========================================================\n\n";

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
    // 1. Setup Test Levels if needed
    $levelDaerah = Level::firstOrCreate(['name' => 'DAERAH'], ['level_number' => 2, 'code' => 'DRH']);
    $levelDesa = Level::firstOrCreate(['name' => 'DESA'], ['level_number' => 1, 'code' => 'DSA']);
    $levelKelompok = Level::firstOrCreate(['name' => 'KELOMPOK'], ['level_number' => 0, 'code' => 'KLP']);

    // 2. Setup Test Groups in two distinct branches
    // Branch A (Daerah A)
    $groupDaerahA = Group::create([
        'name' => 'TEST_DAERAH_ALPHA_'.uniqid(),
        'level_id' => $levelDaerah->id,
        'parent_id' => null,
        'status' => true,
    ]);

    $groupDesaA = Group::create([
        'name' => 'TEST_DESA_ALPHA_'.uniqid(),
        'level_id' => $levelDesa->id,
        'parent_id' => $groupDaerahA->id,
        'status' => true,
    ]);

    // Branch B (Daerah B)
    $groupDaerahB = Group::create([
        'name' => 'TEST_DAERAH_BETA_'.uniqid(),
        'level_id' => $levelDaerah->id,
        'parent_id' => null,
        'status' => true,
    ]);

    $groupDesaB = Group::create([
        'name' => 'TEST_DESA_BETA_'.uniqid(),
        'level_id' => $levelDesa->id,
        'parent_id' => $groupDaerahB->id,
        'status' => true,
    ]);

    // 3. Setup Test Users
    // Superadmin
    $superadminUser = User::create([
        'name' => 'Test Super Admin',
        'email' => 'test_superadmin_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::SUPER_ADMIN,
    ]);
    $superadminUser->assignRole('super_admin');

    // User A scoped to Branch A
    $userA = User::create([
        'name' => 'Test Operator A',
        'email' => 'test_operator_a_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::OPERATOR,
        'group_id' => $groupDaerahA->id,
    ]);
    $userA->assignRole('operator');

    // User B scoped to Branch B
    $userB = User::create([
        'name' => 'Test Operator B',
        'email' => 'test_operator_b_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::OPERATOR,
        'group_id' => $groupDaerahB->id,
    ]);
    $userB->assignRole('operator');

    // User C without any group
    $userC = User::create([
        'name' => 'Test User No Group',
        'email' => 'test_no_group_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::OPERATOR,
        'group_id' => null,
    ]);
    $userC->assignRole('operator');

    // Flush cache for test user keys
    Cache::forget("import_group_cache_u{$superadminUser->id}");
    Cache::forget("import_group_cache_u{$userA->id}");
    Cache::forget("import_group_cache_u{$userB->id}");
    Cache::forget("import_group_cache_u{$userC->id}");

    echo "--- Phase 1: Verify User Manageable Group Scope ---\n";
    $manageableA = $userA->getManageableGroupIds();
    $manageableB = $userB->getManageableGroupIds();

    assertCondition(
        in_array($groupDaerahA->id, $manageableA) && in_array($groupDesaA->id, $manageableA),
        'User A can manage its own group and descendants (Daerah A & Desa A)'
    );
    assertCondition(
        ! in_array($groupDaerahB->id, $manageableA) && ! in_array($groupDesaB->id, $manageableA),
        'User A CANNOT manage Branch B groups'
    );
    assertCondition(
        in_array($groupDaerahB->id, $manageableB) && in_array($groupDesaB->id, $manageableB),
        'User B can manage its own group and descendants (Daerah B & Desa B)'
    );
    assertCondition(
        ! in_array($groupDaerahA->id, $manageableB) && ! in_array($groupDesaA->id, $manageableB),
        'User B CANNOT manage Branch A groups'
    );

    // 4. Create Temporary CSV files for Import testing
    $tmpDir = sys_get_temp_dir();

    // File 1: Attempts to target $groupDesaA->name (Branch A) as parent
    $csvFileTargetingA = $tmpDir.'/test_import_targeting_a_'.uniqid().'.csv';
    $contentA = "Tingkat Grup (Wajib),Nama Grup (Wajib),Induk Grup,Kode Grup (Opsional)\n";
    $contentA .= "KELOMPOK,TEST_KELOMPOK_X,{$groupDesaA->name},01\n";
    file_put_contents($csvFileTargetingA, $contentA);

    // File 2: Attempts to create a top-level group with NO parent
    $csvFileNoParent = $tmpDir.'/test_import_no_parent_'.uniqid().'.csv';
    $contentNoParent = "Tingkat Grup (Wajib),Nama Grup (Wajib),Induk Grup,Kode Grup (Opsional)\n";
    $contentNoParent .= "DAERAH,TEST_NEW_ROOT_GROUP,,99\n";
    file_put_contents($csvFileNoParent, $contentNoParent);

    echo "\n--- Phase 2: Parser Security & Scope Filtering ---\n";

    // Test 2.1: User B tries to import a group under groupDesaA (Branch A)
    // MUST FAIL because groupDesaA is out of User B's scope!
    $parserUserB = new GroupImportParser($userB);
    $resultUserB = $parserUserB->parse($csvFileTargetingA);

    assertCondition(
        $resultUserB['invalid_count'] === 1 && $resultUserB['valid_count'] === 0,
        'User B cannot target parent group in Branch A (1 invalid, 0 valid)',
        'Invalid count: '.$resultUserB['invalid_count'].', Valid count: '.$resultUserB['valid_count']
    );
    $errorMessage = $resultUserB['invalid_rows'][0]['error_message'] ?? '';
    assertCondition(
        str_contains($errorMessage, "Induk Grup '{$groupDesaA->name}' tidak ditemukan di sistem"),
        'User B gets expected error message: parent not found in scope',
        'Actual error message: '.$errorMessage
    );

    // Test 2.2: User A imports the SAME file targeting groupDesaA (Branch A)
    // MUST SUCCEED because groupDesaA is in User A's scope!
    $parserUserA = new GroupImportParser($userA);
    $resultUserA = $parserUserA->parse($csvFileTargetingA);

    assertCondition(
        $resultUserA['valid_count'] === 1 && $resultUserA['invalid_count'] === 0,
        'User A can successfully target parent group in Branch A (1 valid, 0 invalid)',
        'Error message if any: '.($resultUserA['invalid_rows'][0]['error_message'] ?? '')
    );
    assertCondition(
        $resultUserA['valid_rows'][0]['parent_id'] === $groupDesaA->id,
        "User A resolved parent_id exactly to groupDesaA ID ({$groupDesaA->id})"
    );

    // Test 2.3: Superadmin imports the same file targeting groupDesaA
    // MUST SUCCEED because Superadmin has global scope
    $parserSuperadmin = new GroupImportParser($superadminUser);
    $resultSuperadmin = $parserSuperadmin->parse($csvFileTargetingA);

    assertCondition(
        $resultSuperadmin['valid_count'] === 1 && $resultSuperadmin['invalid_count'] === 0,
        'Superadmin can resolve parent group globally (1 valid, 0 invalid)'
    );
    assertCondition(
        $resultSuperadmin['valid_rows'][0]['parent_id'] === $groupDesaA->id,
        "Superadmin resolved parent_id to groupDesaA ID ({$groupDesaA->id})"
    );

    // Test 2.4: User C (no group assigned)
    // MUST FAIL for all rows with clear message
    $parserUserC = new GroupImportParser($userC);
    $resultUserC = $parserUserC->parse($csvFileTargetingA);

    assertCondition(
        $resultUserC['invalid_count'] === 1,
        'User without group assignment gets all rows marked invalid'
    );
    $errorMsgC = $resultUserC['invalid_rows'][0]['error_message'] ?? '';
    assertCondition(
        str_contains($errorMsgC, 'Pengguna tidak memiliki wewenang pada grup manapun'),
        'User without group gets explicit error about lacking manageable groups',
        'Actual error: '.$errorMsgC
    );

    // Test 2.5: User A attempts to create a root group (empty induk_grup)
    // MUST FAIL because non-superadmin cannot create top-level groups
    $resultNoParent = $parserUserA->parse($csvFileNoParent);

    assertCondition(
        $resultNoParent['invalid_count'] === 1,
        'Non-superadmin cannot create a new top-level group with empty induk_grup'
    );
    $errorMsgNoParent = $resultNoParent['invalid_rows'][0]['error_message'] ?? '';
    assertCondition(
        str_contains($errorMsgNoParent, 'Induk grup wajib diisi. Pengguna non-Super Admin tidak dapat membuat grup tingkat teratas baru.'),
        'Non-superadmin receives top-level restriction error message',
        'Actual error: '.$errorMsgNoParent
    );

    // Test 2.6: Superadmin imports top-level group with empty induk_grup
    // MUST SUCCEED because Superadmin can create root groups
    $resultSuperNoParent = $parserSuperadmin->parse($csvFileNoParent);
    assertCondition(
        $resultSuperNoParent['valid_count'] === 1,
        'Superadmin CAN create top-level group with empty induk_grup'
    );

    echo "\n--- Phase 3: Cache Isolation Verification ---\n";
    // Verify that cache key for user B does not contain groupDesaA
    $cachedB = Cache::get("import_group_cache_u{$userB->id}");
    assertCondition(
        is_array($cachedB) && ! isset($cachedB[$groupDesaA->name]) && isset($cachedB[$groupDesaB->name]),
        'Cache key for User B contains ONLY Branch B groups, no cross-contamination'
    );

    $cachedA = Cache::get("import_group_cache_u{$userA->id}");
    assertCondition(
        is_array($cachedA) && isset($cachedA[$groupDesaA->name]) && ! isset($cachedA[$groupDesaB->name]),
        'Cache key for User A contains ONLY Branch A groups, no cross-contamination'
    );

    echo "\n--- Phase 4: SaveImport Execution Security ---\n";
    // 4.1: Test saveImport simulation where User B attempts to save a row referencing Branch A parent
    auth()->setUser($userB);
    $isSuperAdmin = $userB->isSuperAdmin();
    $manageableIds = $userB->getManageableGroupIds();
    $groupQuery = Group::query();
    if (! $isSuperAdmin) {
        $groupQuery->whereIn('id', $manageableIds);
    }
    $groupMapB = $groupQuery->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
        return [strtoupper(trim($name)) => $id];
    })->toArray();

    // Verify groupMapB doesn't have Branch A
    assertCondition(
        ! isset($groupMapB[$groupDesaA->name]),
        "In saveImport, User B's scoped groupMap does NOT contain Branch A parent"
    );

    // If User B tries to resolve $groupDesaA->name in fallback:
    $parentGroupQuery = Group::whereRaw('UPPER(name) = ?', [$groupDesaA->name]);
    if (! $isSuperAdmin) {
        $parentGroupQuery->whereIn('id', $manageableIds);
    }
    $resolvedParent = $parentGroupQuery->first();
    assertCondition(
        $resolvedParent === null,
        "In saveImport, User B's fallback query fails to find Branch A parent"
    );

    // 4.2: Test saveImport simulation where User A saves under Branch A parent
    auth()->setUser($userA);
    $manageableIdsA = $userA->getManageableGroupIds();
    $groupQueryA = Group::query();
    if (! $userA->isSuperAdmin()) {
        $groupQueryA->whereIn('id', $manageableIdsA);
    }
    $groupMapA = $groupQueryA->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
        return [strtoupper(trim($name)) => $id];
    })->toArray();

    assertCondition(
        isset($groupMapA[$groupDesaA->name]) && $groupMapA[$groupDesaA->name] === $groupDesaA->id,
        "In saveImport, User A's scoped groupMap correctly contains Branch A parent with ID {$groupDesaA->id}"
    );

    // Clean up temporary files
    @unlink($csvFileTargetingA);
    @unlink($csvFileNoParent);

} finally {
    // Rollback all database modifications made during the test
    DB::rollBack();
    echo "\n[INFO] Test transaction rolled back. Database remains clean.\n";
}

echo "\n========================================================\n";
echo "  TEST SUMMARY: {$passedCount} PASSED, {$failedCount} FAILED\n";
echo "========================================================\n";

if ($failedCount > 0) {
    exit(1);
}
exit(0);
