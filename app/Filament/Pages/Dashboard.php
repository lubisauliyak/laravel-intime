<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\TodayAttendanceTrend;
use App\Filament\Widgets\UpcomingMeetingsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Beranda';

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            QuickActionsWidget::class,
            UpcomingMeetingsWidget::class,
            TodayAttendanceTrend::class,
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Beranda';
    }

    public function getSubheading(): ?string
    {
        return 'Selamat datang, '.auth()->user()->name.'!';
    }
}
