<?php

namespace App\Filament\Resources\ActivityLogs\Widgets;

use App\Models\ActivityLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ActivityLogStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $cacheKey = 'activity_log_stats_'.($user?->id ?? 'guest').'_'.($user?->group_id ?? 'all');

        $data = Cache::remember($cacheKey, 120, function () use ($user) {
            $baseQuery = ActivityLog::query()->forManageableGroups($user);

            // Statistik 30 hari terakhir
            $stats = (clone $baseQuery)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(CASE WHEN event = "updated" THEN 1 END) as updated_count,
                    COUNT(CASE WHEN event = "deleted" THEN 1 END) as deleted_count
                ')
                ->first();

            // Kontributor teraktif 30 hari terakhir
            $topCauser = (clone $baseQuery)
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('causer_name')
                ->where('causer_name', '!=', 'Sistem')
                ->selectRaw('causer_name, COUNT(*) as action_count')
                ->groupBy('causer_name')
                ->orderByDesc('action_count')
                ->first();

            // Tren harian 7 hari terakhir untuk sparkline
            $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
            $trends = (clone $baseQuery)
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('
                    DATE(created_at) as log_date,
                    COUNT(*) as total,
                    COUNT(CASE WHEN event = "updated" THEN 1 END) as updated_count,
                    COUNT(CASE WHEN event = "deleted" THEN 1 END) as deleted_count
                ')
                ->groupBy('log_date')
                ->get()
                ->keyBy('log_date');

            $totalChart = $days->map(fn ($d) => (int) ($trends[$d]->total ?? 0))->values()->toArray();
            $updateChart = $days->map(fn ($d) => (int) ($trends[$d]->updated_count ?? 0))->values()->toArray();
            $deleteChart = $days->map(fn ($d) => (int) ($trends[$d]->deleted_count ?? 0))->values()->toArray();

            return [
                'total' => (int) ($stats->total ?? 0),
                'updated' => (int) ($stats->updated_count ?? 0),
                'deleted' => (int) ($stats->deleted_count ?? 0),
                'top_name' => $topCauser?->causer_name ?? 'Belum ada data',
                'top_count' => (int) ($topCauser?->action_count ?? 0),
                'total_chart' => $totalChart,
                'update_chart' => $updateChart,
                'delete_chart' => $deleteChart,
            ];
        });

        return [
            Stat::make('Total Aktivitas (30 Hari)', number_format($data['total'], 0, ',', '.'))
                ->description('Seluruh rekam jejak aktivitas')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($data['total_chart'])
                ->color('primary'),

            Stat::make('Pembaruan Data', number_format($data['updated'], 0, ',', '.'))
                ->description('Perubahan nilai/status')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->chart($data['update_chart'])
                ->color('info'),

            Stat::make('Aksi Kritis / Hapus', number_format($data['deleted'], 0, ',', '.'))
                ->description('Data yang dihapus')
                ->descriptionIcon('heroicon-m-trash')
                ->chart($data['delete_chart'])
                ->color('danger'),

            Stat::make('Kontributor Teraktif', $data['top_name'])
                ->description($data['top_count'] > 0 ? "{$data['top_count']} aksi tercatat" : 'Belum ada kontributor')
                ->descriptionIcon('heroicon-m-user')
                ->color('success'),
        ];
    }
}
