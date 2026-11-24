<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
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
echo "       FASE 4 - MEMBER AUTO-ID & FORM VERIFICATION     \n";
echo "======================================================\n\n";

DB::beginTransaction();

try {
    $lvlDes = Level::where('code', 'DES')->first() ?? Level::where('level_number', 2)->first() ?? Level::firstOrCreate(['code' => 'DES'], ['name' => 'DESA', 'level_number' => 2]);
    $lvlKel = Level::where('code', 'KEL')->first() ?? Level::where('level_number', 1)->first() ?? Level::firstOrCreate(['code' => 'KEL'], ['name' => 'KELOMPOK', 'level_number' => 1]);

    // Setup group with valid chain (prefix 7766)
    $des = Group::create(['name' => 'DESA FASE4', 'level_id' => $lvlDes->id, 'code' => '77']);
    $kel = Group::create(['name' => 'KELOMPOK FASE4', 'level_id' => $lvlKel->id, 'parent_id' => $des->id, 'code' => '66']);

    // ── TEST 1: Auto-generate member_code when left empty ──
    $member1 = new Member([
        'full_name' => 'Budi Santoso',
        'group_id' => $kel->id,
        'birth_date' => '1995-01-01',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);
    $member1->save();

    assert_it('Test 1.1: 1st member code auto-generated', $member1->member_code === '7766001', 'Got: '.($member1->member_code ?? 'null'));

    $member2 = new Member([
        'full_name' => 'Siti Rahma',
        'group_id' => $kel->id,
        'birth_date' => '1996-02-02',
        'gender' => \App\Enums\Gender::FEMALE,
        'status' => true,
    ]);
    $member2->save();

    assert_it('Test 1.2: 2nd member code auto-incremented to 7766002', $member2->member_code === '7766002', 'Got: '.($member2->member_code ?? 'null'));

    // ── TEST 2: Preserve manual ID ──
    $memberManual = new Member([
        'member_code' => 'MANUAL-7788',
        'full_name' => 'Ahmad Manual',
        'group_id' => $kel->id,
        'birth_date' => '1990-03-03',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);
    $memberManual->save();

    assert_it('Test 2: Manual member_code preserved', $memberManual->member_code === 'MANUAL-7788', 'Got: '.($memberManual->member_code ?? 'null'));

    // ── TEST 3: Safety Net - Group without codes ──
    $desNoCode = Group::create(['name' => 'DESA TANPA KODE', 'level_id' => $lvlDes->id, 'code' => null]);
    $kelNoCode = Group::create(['name' => 'KELOMPOK TANPA KODE', 'level_id' => $lvlKel->id, 'parent_id' => $desNoCode->id, 'code' => null]);

    $memberNoCode = new Member([
        'full_name' => 'Zainal Tanpa ID',
        'group_id' => $kelNoCode->id,
        'birth_date' => '1992-04-04',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);
    $memberNoCode->save();

    assert_it('Test 3: Member in no-code group keeps member_code as NULL', $memberNoCode->member_code === null, 'Got: '.($memberNoCode->member_code ?? 'not null'));

    // ── TEST 4: ViewMember KTA template inheritance resolution ──
    $template = CardTemplate::create([
        'name' => 'Template Resmi Fase 4',
        'is_active' => true,
    ]);
    $des->update(['card_template_id' => $template->id]);

    $service = app(\App\Services\Groups\GroupHierarchyService::class);
    $resTmpl = $service->getEffectiveCardTemplate($kel);

    assert_it('Test 4.1: Kelompok inherits Desa template', $resTmpl['template']?->id === $template->id);
    assert_it('Test 4.2: Kelompok template is locked', $resTmpl['is_locked'] === true);

} finally {
    DB::rollBack();
}

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
}
