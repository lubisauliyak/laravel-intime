<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking for duplicate member names...\n\n";

// Find duplicate full_name entries (case-insensitive)
$duplicates = DB::table('members')
    ->select(DB::raw('LOWER(full_name) as name_lower'), DB::raw('COUNT(*) as count'))
    ->groupBy('name_lower')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->isEmpty()) {
    echo "✓ No duplicate member names found.\n";
} else {
    echo "⚠ Found " . $duplicates->count() . " duplicate name(s):\n\n";
    
    foreach ($duplicates as $duplicate) {
        $members = DB::table('members')
            ->select('id', 'member_code', 'full_name', 'nick_name', 'group_id', 'status', 'created_at')
            ->whereRaw('LOWER(full_name) = ?', [$duplicate->name_lower])
            ->orderBy('created_at')
            ->get();
        
        echo "Name: " . $members->first()->full_name . " ({$duplicate->count} occurrences)\n";
        echo str_repeat('-', 80) . "\n";
        
        foreach ($members as $member) {
            echo sprintf(
                "  ID: %d | Code: %s | Group ID: %d | Status: %s | Created: %s\n",
                $member->id,
                $member->member_code,
                $member->group_id,
                $member->status,
                $member->created_at
            );
        }
        echo "\n";
    }
}

// Also show potential duplicates (similar names)
echo "\n--- Potential Duplicates (Similar Names) ---\n\n";

$allMembers = DB::table('members')
    ->select('id', 'member_code', 'full_name', 'group_id')
    ->orderBy('full_name')
    ->get();

$potentialDuplicates = [];
$memberArray = $allMembers->toArray();

for ($i = 0; $i < count($memberArray); $i++) {
    for ($j = $i + 1; $j < count($memberArray); $j++) {
        $name1 = strtolower(trim($memberArray[$i]->full_name));
        $name2 = strtolower(trim($memberArray[$j]->full_name));
        
        // Check if one name contains the other (for abbreviated names, etc.)
        if ($name1 !== $name2 && 
            (strpos($name1, $name2) !== false || strpos($name2, $name1) !== false)) {
            $potentialDuplicates[] = [$memberArray[$i], $memberArray[$j]];
        }
    }
}

if (empty($potentialDuplicates)) {
    echo "No potential duplicates found.\n";
} else {
    foreach ($potentialDuplicates as $pair) {
        echo sprintf(
            "  - %s (ID: %d) \n    %s (ID: %d)\n\n",
            $pair[0]->full_name,
            $pair[0]->id,
            $pair[1]->full_name,
            $pair[1]->id
        );
    }
}

echo "\nDone!\n";
