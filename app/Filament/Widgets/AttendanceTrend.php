<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AttendanceTrend extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;
    
    // Responsive column span
    protected int|string|array $columnSpan = 'full';
    
    protected ?string $heading = 'Statistik Tren Kehadiran';
    protected ?string $pollingInterval = '15s';
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'attendance_trend_' . ($user->group_id ?? 'all');

        return Cache::remember($cacheKey, 180, function () use ($user) {
            $maxDaysAgo = now()->subDays(30);

            // Find earliest meeting within 30 days for this user's scope
            $meetingQuery = \App\Models\Meeting::where('meeting_date', '>=', $maxDaysAgo->toDateString())
                ->where('meeting_date', '<=', now()->toDateString());

            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedMeetingGroupIds = array_merge(
                    [$user->group_id],
                    $user->group->getAllAncestorIds()
                );
                $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
            }

            $earliestMeeting = $meetingQuery->orderBy('meeting_date')->first();

            // Start from earliest meeting date, or 7 days ago if no meetings found
            $startDate = $earliestMeeting
                ? $earliestMeeting->meeting_date->copy()->startOfDay()
                : now()->subDays(7);

            // Ensure we don't exceed 30 days
            if ($startDate->lt($maxDaysAgo)) {
                $startDate = $maxDaysAgo;
            }

            $query = Attendance::query()
                ->selectRaw('DATE(checkin_time) as date, count(*) as aggregate')
                ->where('checkin_time', '>=', $startDate);

            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', $allowedGroupIds));
            }

            $data = $query->groupBy('date')
                ->orderBy('date')
                ->get();

            // Generate labels from earliest meeting to today
            $labels = [];
            $values = [];
            $totalDays = $startDate->diffInDays(now());

            for ($i = $totalDays; $i >= 0; $i--) {
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
                        'borderColor' => '#22c55e',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.08)',
                        'fill' => 'start',
                        'tension' => 0.4,
                        'borderWidth' => 2.5,
                        'pointRadius' => 3,
                        'pointBackgroundColor' => '#22c55e',
                        'pointBorderColor' => '#ffffff',
                        'pointBorderWidth' => 2,
                        'pointHoverRadius' => 6,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
            ],
        ];
    }
}
