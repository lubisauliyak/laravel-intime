<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::all();
foreach($users as $u) {
    echo "User: {$u->name} (Email: {$u->email}, GroupID: " . ($u->group_id ?? 'NULL') . ")\n";
    echo "  Roles: " . implode(', ', $u->getRoleNames()->toArray()) . "\n";
}
