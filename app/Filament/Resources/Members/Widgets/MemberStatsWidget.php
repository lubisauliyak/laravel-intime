<?php

namespace App\Filament\Resources\Members\Widgets;

use App\Filament\Resources\Members\MemberResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $data = MemberResource::getMemberCountsAndTrends();

        $total = $data['total'];
        $active = $data['active'];
        $pending = $data['pending'];
        $inactive = $data['inactive'];

        $totalChart = $data['total_chart'];
        $activeChart = $data['active_chart'];
        $pendingChart = $data['pending_chart'];
        $inactiveChart = $data['inactive_chart'];

        $activePercentage = $total > 0 ? round(($active / $total) * 100, 1) : 0;
        $pendingPercentage = $total > 0 ? round(($pending / $total) * 100, 1) : 0;
        $inactivePercentage = $total > 0 ? round(($inactive / $total) * 100, 1) : 0;

        return [
            Stat::make('Total Anggota', number_format($total, 0, ',', '.'))
                ->description('Total keseluruhan')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($totalChart)
                ->color('primary'),

            Stat::make('Anggota Aktif', number_format($active, 0, ',', '.'))
                ->description("{$activePercentage}% dari total")
                ->descriptionIcon('heroicon-m-check-badge')
                ->chart($activeChart)
                ->color('success'),

            Stat::make('Menunggu Verifikasi', number_format($pending, 0, ',', '.'))
                ->description("{$pendingPercentage}% menunggu")
                ->descriptionIcon('heroicon-m-clock')
                ->chart($pendingChart)
                ->color('warning'),

            Stat::make('Anggota Kurang Aktif', number_format($inactive, 0, ',', '.'))
                ->description("{$inactivePercentage}% kurang aktif")
                ->descriptionIcon('heroicon-m-user-minus')
                ->chart($inactiveChart)
                ->color('gray'),
        ];
    }
}
