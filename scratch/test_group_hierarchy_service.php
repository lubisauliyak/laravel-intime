<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use App\Services\Groups\GroupHierarchyService;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assert_it(string $name, bool $condition, string $details = '')
{
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
echo "   GROUP HIERARCHY SERVICE - AUTOMATED UNIT TESTS      \n";
echo "======================================================\n\n";

$service = new GroupHierarchyService;

DB::beginTransaction();

try {
    // 1. Setup Levels using existing records in DB or fallback
    $lvlKab = Level::where('code', 'KAB')->first() ?? Level::where('level_number', 4)->first() ?? Level::firstOrCreate(['code' => 'KAB'], ['name' => 'KABUPATEN', 'level_number' => 4]);
    $lvlDae = Level::where('code', 'DAE')->first() ?? Level::where('level_number', 3)->first() ?? Level::firstOrCreate(['code' => 'DAE'], ['name' => 'DAERAH', 'level_number' => 3]);
    $lvlDes = Level::where('code', 'DES')->first() ?? Level::where('level_number', 2)->first() ?? Level::firstOrCreate(['code' => 'DES'], ['name' => 'DESA', 'level_number' => 2]);
    $lvlKel = Level::where('code', 'KEL')->first() ?? Level::where('level_number', 1)->first() ?? Level::firstOrCreate(['code' => 'KEL'], ['name' => 'KELOMPOK', 'level_number' => 1]);

    // 2. Setup Template
    $template = CardTemplate::create([
        'name' => 'Template KTA Resmi 2026',
        'is_active' => true,
    ]);

    // ── SCENARIO A: Valid Continuous Chain (User Example: Desa=10, Kelompok=07) ──
    $kabA = Group::create(['name' => 'KABUPATEN SIDOARJO', 'level_id' => $lvlKab->id, 'code' => null]);
    $daeA = Group::create(['name' => 'DAERAH SIDOARJO TENGAH', 'level_id' => $lvlDae->id, 'parent_id' => $kabA->id, 'code' => null]);
    $desA = Group::create(['name' => 'DESA GEDANGAN 1', 'level_id' => $lvlDes->id, 'parent_id' => $daeA->id, 'code' => '10']);
    $kelA = Group::create(['name' => 'KELOMPOK SAWOTRATAP TIMUR', 'level_id' => $lvlKel->id, 'parent_id' => $desA->id, 'code' => '07']);

    $resA = $service->validateChainIntegrity($kelA);
    assert_it('Scenario A: Chain is valid', $resA['is_valid'] === true);
    assert_it('Scenario A: Status is valid', $resA['status'] === 'valid');
    assert_it('Scenario A: Prefix is 1007', $resA['prefix'] === '1007', 'Got: '.($resA['prefix'] ?? 'null'));
    assert_it('Scenario A: getCalculatedPrefix returns 1007', $service->getCalculatedPrefix($kelA) === '1007');

    // ── SCENARIO B: Broken Chain in Middle (Daerah=01, Desa=null, Kelompok=07) ──
    $daeB = Group::create(['name' => 'DAERAH UTARA', 'level_id' => $lvlDae->id, 'code' => '01']);
    $desB = Group::create(['name' => 'DESA SEDATI', 'level_id' => $lvlDes->id, 'parent_id' => $daeB->id, 'code' => null]);
    $kelB = Group::create(['name' => 'KELOMPOK PEPE', 'level_id' => $lvlKel->id, 'parent_id' => $desB->id, 'code' => '07']);

    $resB = $service->validateChainIntegrity($kelB);
    assert_it('Scenario B: Middle gap is invalid', $resB['is_valid'] === false);
    assert_it('Scenario B: Status is broken_chain', $resB['status'] === 'broken_chain');
    assert_it('Scenario B: Broken group is Desa Sedati', $resB['broken_group']?->id === $desB->id);
    assert_it('Scenario B: getCalculatedPrefix returns null', $service->getCalculatedPrefix($kelB) === null);

    // ── SCENARIO C: Broken Chain at Leaf (Daerah=01, Desa=10, Kelompok=null) ──
    $kelC = Group::create(['name' => 'KELOMPOK TANPA KODE', 'level_id' => $lvlKel->id, 'parent_id' => $desA->id, 'code' => null]);
    $resC = $service->validateChainIntegrity($kelC);
    assert_it('Scenario C: Leaf gap is invalid', $resC['is_valid'] === false);
    assert_it('Scenario C: Broken group is Kelompok', $resC['broken_group']?->id === $kelC->id);

    // ── SCENARIO D: All Empty Chain (Safety Net -> no_code) ──
    $kabD = Group::create(['name' => 'KAB KOSONG', 'level_id' => $lvlKab->id, 'code' => null]);
    $daeD = Group::create(['name' => 'DAE KOSONG', 'level_id' => $lvlDae->id, 'parent_id' => $kabD->id, 'code' => null]);
    $desD = Group::create(['name' => 'DES KOSONG', 'level_id' => $lvlDes->id, 'parent_id' => $daeD->id, 'code' => null]);
    $kelD = Group::create(['name' => 'KEL KOSONG', 'level_id' => $lvlKel->id, 'parent_id' => $desD->id, 'code' => null]);

    $resD = $service->validateChainIntegrity($kelD);
    assert_it('Scenario D: All empty is valid', $resD['is_valid'] === true);
    assert_it('Scenario D: Status is no_code', $resD['status'] === 'no_code');
    assert_it('Scenario D: Prefix is null', $resD['prefix'] === null);
    assert_it('Scenario D: Member code generation returns null', $service->generateNextMemberCode($kelD) === null);

    // ── SCENARIO E: Sequential Member Code Generation (Next Increment) ──
    // E1: Fresh prefix (8899) starting from 001
    $desE = Group::create(['name' => 'DESA TEST', 'level_id' => $lvlDes->id, 'code' => '88']);
    $kelE = Group::create(['name' => 'KELOMPOK TEST', 'level_id' => $lvlKel->id, 'parent_id' => $desE->id, 'code' => '99']);

    $codeE1 = $service->generateNextMemberCode($kelE);
    assert_it('Scenario E1: 1st code for fresh prefix is 8899001', $codeE1 === '8899001', 'Got: '.($codeE1 ?? 'null'));

    $mE1 = Member::create([
        'member_code' => $codeE1,
        'full_name' => 'ANGGOTA FRESH 1',
        'group_id' => $kelE->id,
        'birth_date' => '1995-05-01',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);

    $codeE2 = $service->generateNextMemberCode($kelE);
    assert_it('Scenario E2: 2nd code for fresh prefix is 8899002', $codeE2 === '8899002', 'Got: '.($codeE2 ?? 'null'));

    $mE2 = Member::create([
        'member_code' => $codeE2,
        'full_name' => 'ANGGOTA FRESH 2',
        'group_id' => $kelE->id,
        'birth_date' => '1996-06-01',
        'gender' => \App\Enums\Gender::FEMALE,
        'status' => true,
    ]);

    // Test with existing 999 to verify flexible digit expansion to 1000
    Member::create([
        'member_code' => '8899999',
        'full_name' => 'ANGGOTA 999',
        'group_id' => $kelE->id,
        'birth_date' => '1990-01-01',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);
    $codeE1000 = $service->generateNextMemberCode($kelE);
    assert_it('Scenario E3: Code expands to 88991000 after 999', $codeE1000 === '88991000', 'Got: '.($codeE1000 ?? 'null'));

    // Test with soft-deleted member (should not collide with soft-deleted)
    $mE2->delete();
    // Latest member is 8899999, so next is still 88991000 (already tested).
    // Now create 88991000 and soft-delete it:
    $mE1000 = Member::create([
        'member_code' => $codeE1000,
        'full_name' => 'ANGGOTA 1000',
        'group_id' => $kelE->id,
        'birth_date' => '1992-01-01',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);
    $mE1000->delete();
    $codeE1001 = $service->generateNextMemberCode($kelE);
    assert_it('Scenario E4: Next code is 88991001 even after 1000 was soft deleted', $codeE1001 === '88991001', 'Got: '.($codeE1001 ?? 'null'));

    // E5: Existing prefix in real DB (1007 has members up to 1007033)
    $nextExistingCode = $service->generateNextMemberCode($kelA);
    assert_it('Scenario E5: Accurately continues sequence on existing 1007 data (1007034)', $nextExistingCode === '1007034', 'Got: '.($nextExistingCode ?? 'null'));

    // ── SCENARIO F: Template Inheritance & Strict Locking ──
    // Set template on Daerah Sidoarjo Tengah (daeA)
    $daeA->update(['card_template_id' => $template->id]);

    // Check on Desa (child of Daerah)
    $tmplDesA = $service->getEffectiveCardTemplate($desA);
    assert_it('Scenario F1: Desa inherits Daerah template', $tmplDesA['template']?->id === $template->id);
    assert_it('Scenario F1: Desa template is locked', $tmplDesA['is_locked'] === true);
    assert_it('Scenario F1: Desa inherited_from is Daerah', $tmplDesA['inherited_from']?->id === $daeA->id);

    // Check on Kelompok (grandchild of Daerah)
    $tmplKelA = $service->getEffectiveCardTemplate($kelA);
    assert_it('Scenario F2: Kelompok inherits Daerah template', $tmplKelA['template']?->id === $template->id);
    assert_it('Scenario F2: Kelompok template is locked', $tmplKelA['is_locked'] === true);
    assert_it('Scenario F2: Kelompok inherited_from is Daerah', $tmplKelA['inherited_from']?->id === $daeA->id);

    // Check on Group with no ancestor template, but having its own template
    $kelD->update(['card_template_id' => $template->id]);
    $tmplKelD = $service->getEffectiveCardTemplate($kelD);
    assert_it('Scenario F3: Self-defined template is NOT locked', $tmplKelD['is_locked'] === false);
    assert_it('Scenario F3: Self-defined inherited_from is null', $tmplKelD['inherited_from'] === null);

} finally {
    // Rollback to keep database completely clean
    DB::rollBack();
}

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
}
