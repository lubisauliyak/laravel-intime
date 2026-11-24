<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Group;
use App\Models\Member;
use App\Services\Groups\GroupHierarchyService;
use Illuminate\Support\Facades\DB;

echo "\n======================================================\n";
echo "   FASE 6.3 - PERFORMANCE & QUERY PLAN BENCHMARK      \n";
echo "======================================================\n\n";

$service = app(GroupHierarchyService::class);

// Cleanup any leftovers from prior runs
Member::where('member_code', 'like', '889977%')->forceDelete();
Group::whereIn('code', ['88', '99', '77'])->delete();

// Setup test hierarchy
$daerah = Group::create(['name' => 'Bench Daerah', 'code' => '88', 'depth' => 0]);
$desa = Group::create(['name' => 'Bench Desa', 'code' => '99', 'parent_id' => $daerah->id, 'depth' => 1]);
$kel = Group::create(['name' => 'Bench Kelompok', 'code' => '77', 'parent_id' => $desa->id, 'depth' => 2]);

// Seed 10 members in Kelompok
for ($i = 1; $i <= 10; $i++) {
    Member::create([
        'group_id' => $kel->id,
        'member_code' => '889977'.str_pad($i, 3, '0', STR_PAD_LEFT),
        'full_name' => 'Bench Member '.$i,
        'gender' => 'male',
        'is_active' => true,
    ]);
}

// 1. Prefix Calculation Benchmark (1,000 iterations)
$start = microtime(true);
$iterations = 1000;
for ($i = 0; $i < $iterations; $i++) {
    $service->getCalculatedPrefix($kel);
}
$prefixDuration = (microtime(true) - $start) * 1000; // ms
$avgPrefix = $prefixDuration / $iterations;

echo sprintf("[BENCHMARK 1] Prefix Calculation (1,000 runs): Total = %.2f ms | Avg = %.4f ms / call\n", $prefixDuration, $avgPrefix);
if ($avgPrefix < 5.0) {
    echo '  -> PASS: Prefix calculation is blazingly fast (< 5ms target, actual: '.round($avgPrefix, 4)."ms)\n";
} else {
    echo "  -> WARN: Prefix calculation exceeded 5ms\n";
}

// 2. Chain Integrity Validation Benchmark (1,000 iterations)
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $service->validateChainIntegrity($kel);
}
$chainDuration = (microtime(true) - $start) * 1000;
$avgChain = $chainDuration / $iterations;

echo sprintf("[BENCHMARK 2] Chain Integrity Check (1,000 runs): Total = %.2f ms | Avg = %.4f ms / call\n", $chainDuration, $avgChain);
if ($avgChain < 5.0) {
    echo '  -> PASS: Chain integrity check is blazingly fast (< 5ms target, actual: '.round($avgChain, 4)."ms)\n";
} else {
    echo "  -> WARN: Chain integrity check exceeded 5ms\n";
}

// 3. Atomic Next Code Generation Benchmark (50 sequential transactional generations)
$genCount = 50;
$start = microtime(true);
DB::beginTransaction();
try {
    for ($i = 0; $i < $genCount; $i++) {
        $service->generateNextMemberCode($kel);
    }
} finally {
    DB::rollBack();
}
$genDuration = (microtime(true) - $start) * 1000;
$avgGen = $genDuration / $genCount;

echo sprintf("[BENCHMARK 3] Atomic Next Code Generation (50 runs with lock): Total = %.2f ms | Avg = %.2f ms / call\n", $genDuration, $avgGen);
if ($avgGen < 20.0) {
    echo '  -> PASS: Atomic code generation is well within target (< 20ms target, actual: '.round($avgGen, 2)."ms)\n";
} else {
    echo "  -> WARN: Atomic code generation exceeded 20ms\n";
}

// 4. EXPLAIN Query Plan Inspection
echo "\n[QUERY PLAN INSPECTION]\n";

// Explain member_code lookup
$explainMember = DB::select("EXPLAIN SELECT member_code FROM members WHERE member_code LIKE '889977%' ORDER BY member_code DESC LIMIT 1");
echo "1. Member Code Query Plan:\n";
foreach ($explainMember as $row) {
    $rowArr = (array) $row;
    echo "   Table: {$rowArr['table']} | Type: {$rowArr['type']} | Possible Keys: ".($rowArr['possible_keys'] ?? 'NULL').' | Key: '.($rowArr['key'] ?? 'NULL')." | Rows: {$rowArr['rows']}\n";
}

// Explain group parent lookup
$explainGroup = DB::select("EXPLAIN SELECT * FROM `groups` WHERE parent_id = {$daerah->id} AND code = '99'");
echo "2. Group Parent + Code Query Plan:\n";
foreach ($explainGroup as $row) {
    $rowArr = (array) $row;
    echo "   Table: {$rowArr['table']} | Type: {$rowArr['type']} | Possible Keys: ".($rowArr['possible_keys'] ?? 'NULL').' | Key: '.($rowArr['key'] ?? 'NULL').' | Key_Len: '.($rowArr['key_len'] ?? 'NULL')."\n";
}

// Cleanup
Member::where('group_id', $kel->id)->forceDelete();
$kel->delete();
$desa->delete();
$daerah->delete();

echo "\n======================================================\n";
echo "BENCHMARK COMPLETED SUCCESSFULLY\n";
echo "======================================================\n";
