<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Meeting;
use App\Models\Attendance;
use App\Models\Member;

$m = Meeting::latest('meeting_date')->first();
$targetGender = $m->target_gender;
$targetAgeGroups = (array) $m->target_age_groups;

$attendees = Attendance::where('meeting_id', $m->id)
    ->where('status', 'hadir')
    ->with('member.ageGroup')
    ->get();

$nonTarget = $attendees->filter(function($a) use ($targetGender, $targetAgeGroups) {
    $member = $a->member;
    if (!$member) return false;
    $genderMatch = ($targetGender === 'all' || $member->gender === $targetGender);
    $ageMatch = (empty($targetAgeGroups) || in_array($member->ageGroup->name ?? '', $targetAgeGroups));
    return !($genderMatch && $ageMatch);
});

echo "Meeting: " . $m->name . "\n";
echo "Target Info: Gender=$targetGender, Ages=" . implode(',', $targetAgeGroups) . "\n";
echo "Total Non-Target: " . $nonTarget->count() . "\n";
foreach ($nonTarget as $a) {
    echo "- " . $a->member->full_name . " (Gender: " . $a->member->gender . ", Usia: " . ($a->member->ageGroup->name ?? 'N/A') . ")\n";
}
