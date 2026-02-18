<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
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

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user, $ref) {
            // Determine time range
            $startHour = 6;
            $endHour = 23;
            $intervalMinutes = 15;

            if ($ref && $ref->start_time && $ref->end_time) {
                // Use meeting's actual time range
                $startHour = (int) $ref->start_time->format('H');
                $endHour = (int) $ref->end_time->format('H');
                
                // Add buffer: 1 hour before and after
                $startHour = max(0, $startHour - 1);
                $endHour = min(23, $endHour + 1);
            }

            // Query attendance data with minute-level granularity
            $query = Attendance::query()
                ->select(
                    DB::raw('HOUR(checkin_time) as hour'),
                    DB::raw('FLOOR(MINUTE(checkin_time) / ' . $intervalMinutes . ') * ' . $intervalMinutes . ' as minute_slot'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('hour', 'minute_slot')
                ->orderBy('hour')
                ->orderBy('minute_slot');

            if ($ref) {
                $query->where('meeting_id', $ref->id);
            } else {
                $query->whereDate('checkin_time', now());
            }

            if (!$user->isSuperAdmin() && $user->group_id) {
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            $data = $query->get()->keyBy(function ($item) {
                return $item->hour . '_' . $item->minute_slot;
            });

            // Generate labels and values for time slots
            $labels = [];
            $values = [];

            for ($h = $startHour; $h <= $endHour; $h++) {
                for ($m = 0; $m < 60; $m += $intervalMinutes) {
                    $key = $h . '_' . $m;
                    $labels[] = sprintf('%02d:%02d', $h, $m);
                    $values[] = $data->get($key)?->count ?? 0;
                }
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
