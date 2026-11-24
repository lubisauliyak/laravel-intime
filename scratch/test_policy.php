<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CardTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== TEST POLICY & CONTROLLER AUTHORIZATION ===\n";

$template = CardTemplate::first();

// 1. Unauthenticated request to controller
$controller = new \App\Http\Controllers\CardEditorController;
try {
    Auth::logout();
    $controller->index($template);
    echo "1. Unauthenticated access: FAILED (Expected 401)\n";
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo '1. Unauthenticated access blocked with HTTP '.$e->getStatusCode().": OK\n";
}

// 2. Superadmin access
$superAdmin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();
if (! $superAdmin) {
    $superAdmin = User::first();
}

if ($superAdmin) {
    Auth::login($superAdmin);
    try {
        $view = $controller->index($template);
        echo "2. Superadmin access allowed: OK\n";
    } catch (\Exception $e) {
        echo '2. Superadmin access: FAILED ('.$e->getMessage().")\n";
    }
}

echo "=== SELESAI ===\n";
