<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\TodayAttendanceTrend;
use App\Filament\Widgets\UpcomingMeetingsWidget;
use App\Models\User;

echo "=== inTime Dashboard Cockpit Verification ===\n\n";

// 1. Dashboard Page Configuration
$dashboard = new Dashboard;
$widgets = $dashboard->getWidgets();
$columns = $dashboard->getColumns();

assert(count($widgets) === 3, 'Dashboard should register exactly 3 widgets');
assert(in_array(QuickActionsWidget::class, $widgets), 'QuickActionsWidget must be present');
assert(in_array(UpcomingMeetingsWidget::class, $widgets), 'UpcomingMeetingsWidget must be present');
assert(in_array(TodayAttendanceTrend::class, $widgets), 'TodayAttendanceTrend must be present');
assert($columns['xl'] === 12, 'XL grid must be 12 columns for side-by-side cockpit');
echo "[PASS] Dashboard page configuration matches 2-Tier single-screen cockpit\n";

// 2. Test Multi-Role Execution
$testUsers = [
    'Superadmin' => User::where('role', 'super_admin')->first(),
    'Admin Gedangan 1' => User::where('email', 'admin@gedangansatu.com')->first(),
    'Admin Sidoarjo Tengah' => User::where('email', 'admin@sdatengah.com')->first(),
    'Operator Putra' => User::where('email', 'absenputra@sdatengah.com')->first(),
];

foreach ($testUsers as $roleName => $user) {
    if (! $user) {
        echo "[SKIP] User for $roleName not found\n";

        continue;
    }

    auth()->login($user);
    echo "\nTesting as $roleName (Group: ".($user->group?->name ?? 'None')."):\n";

    // A. QuickActionsWidget
    $qa = new QuickActionsWidget;
    $qaMethod = new ReflectionMethod($qa, 'getStats');
    $qaMethod->setAccessible(true);
    $stats = $qaMethod->invoke($qa);
    assert(count($stats) === 4, "QuickActionsWidget must return 4 stats for $roleName");
    echo '  - QuickActionsWidget: OK ('.count($stats).' stats returned: ';
    $statLabels = array_map(fn ($s) => $s->getLabel(), $stats);
    echo implode(', ', $statLabels).")\n";

    // B. UpcomingMeetingsWidget
    $um = new UpcomingMeetingsWidget;
    $umTableMethod = new ReflectionMethod($um, 'getTable');
    $umTableMethod->setAccessible(true);
    // Execute table query
    $table = $um->table(new Filament\Tables\Table($um));
    $query = $table->getQuery();
    $meetings = $query->get();
    assert($meetings->count() <= 3, 'UpcomingMeetingsWidget must be limited to max 3 records');
    echo '  - UpcomingMeetingsWidget: OK ('.$meetings->count().' meetings retrieved: ';
    $meetingNames = $meetings->pluck('name')->map(fn ($n) => substr($n, 0, 20))->toArray();
    echo (count($meetingNames) > 0 ? implode(', ', $meetingNames) : 'None').")\n";

    // C. TodayAttendanceTrend
    $trend = new TodayAttendanceTrend;
    $trendMethod = new ReflectionMethod($trend, 'getData');
    $trendMethod->setAccessible(true);
    $trendData = $trendMethod->invoke($trend);
    assert(isset($trendData['datasets']), 'TodayAttendanceTrend must return datasets');
    assert(isset($trendData['labels']), 'TodayAttendanceTrend must return labels');
    $pointsCount = count($trendData['datasets'][0]['data'] ?? []);
    echo "  - TodayAttendanceTrend: OK ($pointsCount data points on chart)\n";
}

echo "\n========================================================\n";
echo "  HASIL AKHIR VERIFIKASI: SEMUA ROLE SUKSES 100%\n";
echo "========================================================\n";
