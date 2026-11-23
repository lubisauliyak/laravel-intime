<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ActivityLog;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pembersihan berkala data audit log yang melampaui batas retensi
Schedule::call(function () {
    $days = (int) env('ACTIVITY_LOG_RETENTION_DAYS', 30);
    ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
})->dailyAt('02:00')->name('prune-activity-logs');
