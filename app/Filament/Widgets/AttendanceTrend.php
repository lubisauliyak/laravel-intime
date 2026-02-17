<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AttendanceTrend extends ChartWidget
{
    protected ?string $heading = 'Tren Kehadiran (10 Hari Terakhir)';
    protected ?string $pollingInterval = '15s';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $user = auth()->user();
        $startDate = now()->subDays(10);
        
        $query = Attendance::query()
            ->selectRaw('DATE(checkin_time) as date, count(*) as aggregate')
            ->where('checkin_time', '>=', $startDate);

        if (!$user->isSuperAdmin() && $user->group_id) {
            $group = $user->group;
            $descendantIds = $group->getAllDescendantIds();
            $childrenIds = array_diff($descendantIds, [$group->id]);

            if (!empty($childrenIds)) {
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', $childrenIds));
            } else {
                $query->whereHas('member', fn($q) => $q->where('group_id', $group->id));
            }
        }

        $data = $query->groupBy('date')
            ->orderBy('date')
            ->get();

        // Ensure we have labels for all days even if 0
        $labels = [];
        $values = [];
        
        for ($i = 10; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d M');
            
            $match = $data->firstWhere('date', $date);
            $values[] = $match ? $match->aggregate : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Kehadiran',
                    'data' => $values,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => 'start',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
