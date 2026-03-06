<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Get all members with group info
$allMembers = DB::table('members')
    ->leftJoin('groups', 'members.group_id', '=', 'groups.id')
    ->select('members.id', 'members.member_code', 'members.full_name', 'members.group_id', 'groups.name as group_name')
    ->orderBy('full_name')
    ->get();

$memberArray = $allMembers->toArray();

// Build group lookup
$groupLookup = [];
foreach ($memberArray as $m) {
    $groupLookup[$m->group_id] = $m->group_name ?? "Group {$m->group_id}";
}

echo "# Duplicate Member Names Report\n\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "---\n\n";

// Find duplicate full_name entries (case-insensitive)
$duplicates = DB::table('members')
    ->select(DB::raw('LOWER(full_name) as name_lower'), DB::raw('COUNT(*) as count'))
    ->groupBy('name_lower')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->isEmpty()) {
    echo "## ✓ No Exact Duplicate Names Found\n\n";
} else {
    echo "## ⚠ Exact Duplicate Names (" . $duplicates->count() . " name(s))\n\n";

    foreach ($duplicates as $duplicate) {
        $members = DB::table('members')
            ->select('id', 'member_code', 'full_name', 'group_id')
            ->whereRaw('LOWER(full_name) = ?', [$duplicate->name_lower])
            ->orderBy('created_at')
            ->get();

        echo "### \"" . $members->first()->full_name . "\" (" . $duplicate->count . " occurrences)\n\n";
        echo "| Member Code | Full Name | Group |\n";
        echo "|-------------|-----------|-------|\n";
        foreach ($members as $member) {
            $groupName = $groupLookup[$member->group_id] ?? "Group {$member->group_id}";
            echo "| {$member->member_code} | {$member->full_name} | {$groupName} |\n";
        }
        echo "\n";
    }
}

// Fuzzy matching using similar_text
echo "---\n\n";
echo "## Fuzzy Duplicates\n\n";

$fuzzyDuplicates = [];
$maxLengthDiff = 10; // Max length difference to consider

for ($i = 0; $i < count($memberArray); $i++) {
    for ($j = $i + 1; $j < count($memberArray); $j++) {
        $name1 = trim($memberArray[$i]->full_name);
        $name2 = trim($memberArray[$j]->full_name);
        $len1 = strlen($name1);
        $len2 = strlen($name2);
        $lengthDiff = abs($len1 - $len2);

        // Skip if length difference is too large
        if ($lengthDiff > $maxLengthDiff) continue;

        if ($name1 !== $name2 && $len1 > 0 && $len2 > 0) {
            similar_text($name1, $name2, $percent);
            
            $sameGroup = ($memberArray[$i]->group_id == $memberArray[$j]->group_id);
            
            // Different groups: only show if >= 77%
            // Same group: show if >= 70%
            $threshold = $sameGroup ? 70 : 77;
            
            if ($percent >= $threshold) {
                $fuzzyDuplicates[] = [
                    $memberArray[$i],
                    $memberArray[$j],
                    round($percent, 1),
                    $sameGroup
                ];
            }
        }
    }
}

if (empty($fuzzyDuplicates)) {
    echo "No fuzzy duplicates found.\n";
} else {
    echo "Found " . count($fuzzyDuplicates) . " potential fuzzy duplicate(s):\n\n";
    echo "> **Note:** Different groups require 77%+ similarity. Same group requires 70%+.\n\n";
    
    // Sort by group name of Member 1, then by similarity percentage (highest first)
    usort($fuzzyDuplicates, function($a, $b) {
        $group1 = $a[0]->group_name ?? "Group {$a[0]->group_id}";
        $group2 = $b[0]->group_name ?? "Group {$b[0]->group_id}";
        
        $groupCompare = strcmp($group1, $group2);
        if ($groupCompare !== 0) {
            return $groupCompare;
        }
        
        return $b[2] <=> $a[2];
    });
    
    echo "| Similarity | Same Group | Member 1 | Code 1 | Group 1 | Member 2 | Code 2 | Group 2 |\n";
    echo "|------------|------------|----------|--------|---------|----------|--------|---------|\n";
    
    foreach ($fuzzyDuplicates as $pair) {
        $sameGroupMark = $pair[3] ? "✅" : "❌";
        echo "| {$pair[2]}% | {$sameGroupMark} | {$pair[0]->full_name} | {$pair[0]->member_code} | " . 
             ($groupLookup[$pair[0]->group_id] ?? "Group {$pair[0]->group_id}") . " | " .
             "{$pair[1]->full_name} | {$pair[1]->member_code} | " .
             ($groupLookup[$pair[1]->group_id] ?? "Group {$pair[1]->group_id}") . " |\n";
    }
}

echo "\n---\n\n";
echo "## Summary\n\n";
echo "- **Exact duplicates:** " . $duplicates->count() . " name(s)\n";
echo "- **Fuzzy duplicates:** " . count($fuzzyDuplicates) . " pair(s)\n";

$sameGroupCount = 0;
foreach ($fuzzyDuplicates as $pair) {
    if ($pair[3]) {
        $sameGroupCount++;
    }
}
echo "- **Same-group pairs:** {$sameGroupCount}\n";
echo "- **Different-group pairs:** " . (count($fuzzyDuplicates) - $sameGroupCount) . "\n";

echo "\n*Report generated by check_duplicate_members.php*\n";
