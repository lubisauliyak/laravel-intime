<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MeetingStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = MeetingResource::getMeetingStats();

        $total = $stats['total'] ?? 0;
        $upcoming = $stats['upcoming'] ?? 0;
        $month = $stats['month'] ?? 0;
        $avgAttendance = $stats['avg_attendance'] ?? 0;

        $totalChart = $stats['total_chart'] ?? [0, 0, 0, 0, 0, 0];
        $upcomingChart = $stats['upcoming_chart'] ?? [0, 0, 0, 0, 0, 0];
        $monthChart = $stats['month_chart'] ?? [0, 0, 0, 0, 0, 0];
        $avgChart = $stats['avg_chart'] ?? [0, 0, 0, 0, 0, 0];

        return [
            Stat::make('Total Pertemuan', number_format($total, 0, ',', '.'))
                ->description('Total agenda tercatat')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($totalChart)
                ->color('primary'),

            Stat::make('Mendatang', number_format($upcoming, 0, ',', '.'))
                ->description('Agenda ke depan')
                ->descriptionIcon('heroicon-m-clock')
                ->chart($upcomingChart)
                ->color('success'),

            Stat::make('Dalam Sebulan', number_format($month, 0, ',', '.'))
                ->description('Aktivitas bulan ini')
                ->descriptionIcon('heroicon-m-calendar')
                ->chart($monthChart)
                ->color('info'),

            Stat::make('Rata - Rata Kehadiran', number_format($avgAttendance, 0, ',', '.'))
                ->description('Anggota / pertemuan')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($avgChart)
                ->color('warning'),
        ];
    }
}
