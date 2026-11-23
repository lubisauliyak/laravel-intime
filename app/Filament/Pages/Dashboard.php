<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Dashboard as BaseDashboard;
class Dashboard extends BaseDashboard
{
    use HasPageShield;

    protected static ?string $navigationLabel = 'Beranda';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\QuickActionsWidget::class,
            \App\Filament\Widgets\TodayAttendanceOverview::class,
            \App\Filament\Widgets\TodayAttendanceTrend::class,
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Beranda';
    }

    public function getSubheading(): ?string
    {
        return "Selamat datang, " . auth()->user()->name . "!";
    }
}
