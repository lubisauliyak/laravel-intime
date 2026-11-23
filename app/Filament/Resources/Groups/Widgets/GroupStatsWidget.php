<?php

namespace App\Filament\Resources\Groups\Widgets;

use App\Filament\Resources\Groups\GroupResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GroupStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = GroupResource::getGroupStats();

        $total = $stats['total'] ?? 0;
        $active = $stats['active'] ?? 0;
        $new = $stats['new'] ?? 0;
        $inactive = $stats['inactive'] ?? 0;

        $activePercentage = $total > 0 ? round(($active / $total) * 100, 1) : 0;
        $inactivePercentage = $total > 0 ? round(($inactive / $total) * 100, 1) : 0;

        $totalChart = $stats['total_chart'] ?? [0, 0, 0, 0, 0, 0];
        $activeChart = $stats['active_chart'] ?? [0, 0, 0, 0, 0, 0];
        $newChart = $stats['new_chart'] ?? [0, 0, 0, 0, 0, 0];
        $inactiveChart = $stats['inactive_chart'] ?? [0, 0, 0, 0, 0, 0];

        return [
            Stat::make('Total Grup', number_format($total, 0, ',', '.'))
                ->description('Total dalam wewenang')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->chart($totalChart)
                ->color('primary'),

            Stat::make('Grup Aktif', number_format($active, 0, ',', '.'))
                ->description("{$activePercentage}% ada pertemuan")
                ->descriptionIcon('heroicon-m-check-badge')
                ->chart($activeChart)
                ->color('success'),

            Stat::make('Grup Baru', number_format($new, 0, ',', '.'))
                ->description('Terakhir terdaftar')
                ->descriptionIcon('heroicon-m-sparkles')
                ->chart($newChart)
                ->color('info'),

            Stat::make('Grup Kurang Aktif', number_format($inactive, 0, ',', '.'))
                ->description("{$inactivePercentage}% dari total")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->chart($inactiveChart)
                ->color('warning'),
        ];
    }
}
