<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use App\Services\Groups\GroupHierarchyService;
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
echo "   FASE 6.1 - CONCURRENCY & SEQUENCE ATOMICITY TEST    \n";
echo "======================================================\n\n";

DB::beginTransaction();

try {
    $lvlDes = Level::where('code', 'DES')->first() ?? Level::where('level_number', 2)->first() ?? Level::firstOrCreate(['code' => 'DES'], ['name' => 'DESA', 'level_number' => 2]);
    $lvlKel = Level::where('code', 'KEL')->first() ?? Level::where('level_number', 1)->first() ?? Level::firstOrCreate(['code' => 'KEL'], ['name' => 'KELOMPOK', 'level_number' => 1]);

    $des = Group::create(['name' => 'DESA CONCURRENCY', 'level_id' => $lvlDes->id, 'code' => '44']);
    $kel = Group::create(['name' => 'KELOMPOK CONCURRENCY', 'level_id' => $lvlKel->id, 'parent_id' => $des->id, 'code' => '33']);

    $service = app(GroupHierarchyService::class);
    $generatedCodes = [];

    // Rapid sequential generation of 20 members
    for ($i = 1; $i <= 20; $i++) {
        $member = new Member([
            'full_name' => "Anggota Konkurensi {$i}",
            'group_id' => $kel->id,
            'birth_date' => '1995-01-01',
            'gender' => \App\Enums\Gender::MALE,
            'status' => true,
        ]);
        $member->save();
        $generatedCodes[] = $member->member_code;
    }

    assert_it('Test 1: Exactly 20 codes generated', count($generatedCodes) === 20);
    assert_it('Test 2: All 20 codes are 100% unique', count(array_unique($generatedCodes)) === 20);
    assert_it('Test 3: First code is 4433001', $generatedCodes[0] === '4433001', 'Got: ' . ($generatedCodes[0] ?? 'null'));
    assert_it('Test 4: Last code is 4433020', $generatedCodes[19] === '4433020', 'Got: ' . ($generatedCodes[19] ?? 'null'));

    // Check sequence continuity
    $isSequential = true;
    for ($i = 0; $i < 20; $i++) {
        $expected = '4433' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
        if ($generatedCodes[$i] !== $expected) {
            $isSequential = false;
            break;
        }
    }
    assert_it('Test 5: Sequence is strictly consecutive from 001 to 020', $isSequential);

    // Soft-delete member #5 and #10
    Member::where('member_code', '4433005')->delete();
    Member::where('member_code', '4433010')->delete();

    // Next generated code must be 4433021 (no reuse/collision)
    $nextMember = new Member([
        'full_name' => 'Anggota Setelah Soft Delete',
        'group_id' => $kel->id,
        'birth_date' => '1995-01-01',
        'gender' => \App\Enums\Gender::MALE,
        'status' => true,
    ]);
    $nextMember->save();

    assert_it('Test 6: Next code after soft delete is 4433021', $nextMember->member_code === '4433021', 'Got: ' . ($nextMember->member_code ?? 'null'));

} finally {
    DB::rollBack();
}

echo "\n======================================================\n";
echo "SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================\n";

if ($failed > 0) {
    exit(1);
}
