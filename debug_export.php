<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Member;
use App\Models\User;
use App\Exports\GlobalAttendanceReportExport;

$user = User::where('email', 'admin@intime.test')->first() ?? User::first();
echo "Testing as User: {$user->name} (Role: " . ($user->roles->first()->name ?? 'No Role') . ")\n";

$filters = [
    'group_id' => null,
    'meeting_date' => [
        'from' => null,
        'until' => null,
    ],
];

echo "Filters: " . json_encode($filters) . "\n";

$export = new GlobalAttendanceReportExport($filters, $user);
$collection = $export->collection();

echo "Collection Count: " . $collection->count() . "\n";

if ($collection->count() > 0) {
    echo "First 5 Members:\n";
    foreach($collection->take(5) as $m) {
        echo "- {$m->full_name} (Group: {$m->group->name})\n";
    }
} else {
    echo "No members found in collection.\n";
    // Check total members in DB
    echo "Total Status=1 Members in DB: " . Member::where('status', true)->count() . "\n";
}
