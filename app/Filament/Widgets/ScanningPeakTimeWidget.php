<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ScanningPeakTimeWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];
    protected ?string $pollingInterval = null;
    protected ?string $maxHeight = '250px';

    private function getReferenceMeeting(): ?Meeting
    {
        $user = auth()->user();
        $query = Meeting::where('meeting_date', '<=', now()->toDateString());
        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $query->whereIn('group_id', $allowedMeetingGroupIds);
        }
        return $query->latest('meeting_date')->first();
    }

    public function getHeading(): ?string
    {
        $ref = $this->getReferenceMeeting();
        if (!$ref || $ref->meeting_date->isToday()) {
            return 'Beban Aktivitas Scanner';
        }
        return 'Beban Aktivitas Scanner (' . $ref->meeting_date->format('d/m/Y') . ')';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();
        $cacheKey = 'scanning_peak_' . ($user->group_id ?? 'all') . '_' . ($ref->id ?? 'today');

        return Cache::remember($cacheKey, 300, function () use ($user, $ref) {
            $targetPoints = 20;

            // Build base query for attendance
            $baseQuery = Attendance::query()->whereNotNull('checkin_time');

            if ($ref) {
                $baseQuery->where('meeting_id', $ref->id);
            } else {
                $baseQuery->whereDate('checkin_time', now());
            }

            if (!$user->isSuperAdmin() && $user->group_id) {
                $baseQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            // Get first and last checkin time
            $firstCheckin = (clone $baseQuery)->orderBy('checkin_time')->value('checkin_time');
            $lastCheckin = (clone $baseQuery)->orderByDesc('checkin_time')->value('checkin_time');

            if (!$firstCheckin || !$lastCheckin) {
                return [
                    'datasets' => [['label' => 'Jumlah Scan', 'data' => [], 'borderColor' => '#fbbf24', 'backgroundColor' => 'rgba(251, 191, 36, 0.1)', 'fill' => 'start', 'tension' => 0.4]],
                    'labels' => [],
                ];
            }

            $firstTime = Carbon::parse($firstCheckin);
            $lastTime = Carbon::parse($lastCheckin);

            // Calculate total duration in minutes
            $totalMinutes = $firstTime->diffInMinutes($lastTime);
            if ($totalMinutes < 1) $totalMinutes = 1;

            // Choose interval: aim for ~20 data points with 5/10/15 min increments
            $rawInterval = $totalMinutes / $targetPoints;
            if ($rawInterval <= 5) {
                $intervalMinutes = 5;
            } elseif ($rawInterval <= 10) {
                $intervalMinutes = 10;
            } else {
                $intervalMinutes = 15;
            }

            // Round start time DOWN to nearest interval
            $startMinute = floor($firstTime->minute / $intervalMinutes) * $intervalMinutes;
            $start = $firstTime->copy()->minute($startMinute)->second(0);

            // Round end time UP to nearest interval
            $endMinute = ceil($lastTime->minute / $intervalMinutes) * $intervalMinutes;
            $end = $lastTime->copy()->minute(0)->second(0);
            if ($endMinute >= 60) {
                $end->addHour();
            } else {
                $end->minute($endMinute);
            }

            // Query attendance data with minute-level granularity
            $data = (clone $baseQuery)
                ->select(
                    DB::raw('HOUR(checkin_time) as hour'),
                    DB::raw('FLOOR(MINUTE(checkin_time) / ' . $intervalMinutes . ') * ' . $intervalMinutes . ' as minute_slot'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('hour', 'minute_slot')
                ->orderBy('hour')
                ->orderBy('minute_slot')
                ->get()
                ->keyBy(function ($item) {
                    return $item->hour . '_' . $item->minute_slot;
                });

            // Generate labels and values for time slots
            $labels = [];
            $values = [];
            $current = $start->copy();

            while ($current->lessThanOrEqualTo($end)) {
                $key = $current->hour . '_' . $current->minute;
                $labels[] = $current->format('H:i');
                $values[] = $data->get($key)?->count ?? 0;
                $current->addMinutes($intervalMinutes);

                // Safety: max 30 points to avoid infinite loop 
                if (count($labels) >= 30) break;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Jumlah Scan',
                        'data' => $values,
                        'borderColor' => '#fbbf24',
                        'backgroundColor' => 'rgba(251, 191, 36, 0.1)',
                        'fill' => 'start',
                        'tension' => 0.4,
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
}
