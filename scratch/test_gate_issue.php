<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'admin@gedangansatu.com')->first();
$meeting = App\Models\Meeting::where('uid', '01M7SC7JF8SFZ8WZXJ4GK0Y13K')->first();

auth()->login($user);

function canAccessFixed(array $parameters = []): bool
{
    $user = auth()->user();
    if (! $user) {
        return false;
    }

    $record = $parameters['record'] ?? null;
    if (is_string($record) || is_int($record)) {
        $record = App\Models\Meeting::where('uid', $record)->orWhere('id', $record)->first();
    }

    if ($record instanceof App\Models\Meeting) {
        return $user->can('viewAttendanceDetails', $record);
    }

    return $user->can('viewAny', App\Models\Meeting::class);
}

echo 'Fixed canAccess with string record: '.(canAccessFixed(['record' => '01M7SC7JF8SFZ8WZXJ4GK0Y13K']) ? 'YES' : 'NO')."\n";
