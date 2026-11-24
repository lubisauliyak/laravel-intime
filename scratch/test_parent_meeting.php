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

// Cari atau buat dummy meeting tingkat parent (group 1: Sidoarjo Tengah)
$parentMeeting = Meeting::where('group_id', 1)->first();
if (! $parentMeeting) {
    $parentMeeting = new Meeting;
    $parentMeeting->name = 'Pengajian Akbar Daerah Sidoarjo Tengah';
    $parentMeeting->group_id = 1;
    $parentMeeting->meeting_date = now()->toDateString();
    $parentMeeting->start_time = now()->setTime(19, 0);
    $parentMeeting->end_time = now()->setTime(21, 0);
    $parentMeeting->checkin_open_time = now()->setTime(18, 30);
    $parentMeeting->save();
}

echo "Testing Parent Meeting: {$parentMeeting->name} (Group ID: 1)\n\n";

$childAdmin = User::where('email', 'admin@gedangansatu.com')->first(); // Group 7 (Child of 1)
auth()->login($childAdmin);

$canView = $childAdmin->can('view', $parentMeeting) ? 'YES' : 'NO';
$canDetails = MeetingAttendanceDetails::canAccess(['record' => $parentMeeting->uid]) ? 'YES' : 'NO';
$canStats = MeetingStats::canAccess(['record' => $parentMeeting->uid]) ? 'YES' : 'NO';
$canSelfService = ManageSelfService::canAccess(['record' => $parentMeeting->uid]) ? 'YES' : 'NO';
$canLog = MeetingActivityLog::canAccess(['record' => $parentMeeting->uid]) ? 'YES' : 'NO';

echo "[Child Admin: Admin Gedangan 1 (Group 7)]\n";
echo "  - can view parent meeting: {$canView}\n";
echo "  - can access Attendance Details: {$canDetails}\n";
echo "  - can access Stats: {$canStats}\n";
echo "  - can access Self Service (should be NO): {$canSelfService}\n";
echo "  - can access Activity Log (should be NO): {$canLog}\n\n";
