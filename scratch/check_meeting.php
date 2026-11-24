<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$m = App\Models\Meeting::where('uid', '01M7SC7JF8SFZ8WZXJ4GK0Y13K')->first();
if ($m) {
    print_r([
        'id' => $m->id,
        'uid' => $m->uid,
        'name' => $m->name,
        'group_id' => $m->group_id,
        'group_name' => $m->group?->name,
        'group_parent_id' => $m->group?->parent_id,
        'group_parent_name' => $m->group?->parent?->name,
        'meeting_date' => $m->meeting_date?->format('Y-m-d'),
        'start_time' => $m->start_time,
        'end_time' => $m->end_time,
        'checkin_open_time' => $m->checkin_open_time,
        'attendances_count' => $m->attendances()->count(),
    ]);
} else {
    echo "Meeting not found\n";
    $recent = App\Models\Meeting::latest()->limit(5)->get(['id', 'uid', 'name', 'group_id']);
    print_r($recent->toArray());
}

$users = App\Models\User::all(['id', 'name', 'email', 'role', 'group_id']);
print_r($users->toArray());
