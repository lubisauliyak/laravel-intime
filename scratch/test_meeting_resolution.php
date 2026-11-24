<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Filament\Resources\Meetings\Pages\ManageSelfService;
use App\Filament\Resources\Meetings\Pages\MeetingActivityLog;
use App\Filament\Resources\Meetings\Pages\MeetingAttendanceDetails;
use App\Filament\Resources\Meetings\Pages\MeetingStats;
use App\Models\Meeting;
use App\Models\User;

$meetingUid = '01M7SC7JF8SFZ8WZXJ4GK0Y13K';
$meeting = Meeting::where('uid', $meetingUid)->firstOrFail();

echo "Testing Meeting: {$meeting->name} (Group: {$meeting->group->name}, Parent: {$meeting->group->parent?->name})\n\n";

$users = [
    'Superadmin' => User::where('role', 'super_admin')->first(),
    'Admin Gedangan 1 (Own Group)' => User::where('email', 'admin@gedangansatu.com')->first(),
    'Admin Sidoarjo Tengah (Parent Group)' => User::where('email', 'admin@sdatengah.com')->first(),
    'Operator Sidoarjo Tengah (Parent Operator)' => User::where('email', 'absenputra@sdatengah.com')->first(),
    'Admin Sruni (Separate Branch)' => User::where('email', 'admin@srunibarokah.com')->first(),
];

foreach ($users as $label => $u) {
    if (! $u) {
        echo "User {$label} not found, skipping.\n";

        continue;
    }

    auth()->login($u);

    $canView = $u->can('view', $meeting) ? 'YES' : 'NO';
    $canDetails = MeetingAttendanceDetails::canAccess(['record' => $meetingUid]) ? 'YES' : 'NO';
    $canStats = MeetingStats::canAccess(['record' => $meetingUid]) ? 'YES' : 'NO';
    $canSelfService = ManageSelfService::canAccess(['record' => $meetingUid]) ? 'YES' : 'NO';
    $canLog = MeetingActivityLog::canAccess(['record' => $meetingUid]) ? 'YES' : 'NO';

    echo "[{$label}]\n";
    echo "  - can view meeting: {$canView}\n";
    echo "  - can access Attendance Details: {$canDetails}\n";
    echo "  - can access Stats: {$canStats}\n";
    echo "  - can access Self Service: {$canSelfService}\n";
    echo "  - can access Activity Log: {$canLog}\n\n";
}
