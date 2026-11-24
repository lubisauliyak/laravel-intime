<?php

namespace App\Filament\Widgets;

use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScanningPeakTimeWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;

    protected static ?int $sort = 8;

    // Responsive column span
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '250px';

    private function getReferenceMeeting(): ?Meeting
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? null;

        return $filterMeetingId ? Meeting::find($filterMeetingId) : null;
    }

    public function getHeading(): ?string
    {
        return 'Beban Aktivitas Scanner';
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        $total = 0;
        if (! empty($data['datasets'])) {
            foreach ($data['datasets'] as $dataset) {
                $total += array_sum($dataset['data']);
            }
        }

        if ($total === 0) {
            return 'Belum ada aktivitas masuk';
        }

        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $ref = $this->getReferenceMeeting();

        if (! $ref) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $user = auth()->user();
        $cacheKey = 'scanning_peak_'.($user->group_id ?? 'all').'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 120, function () use ($user, $ref) {
            $targetPoints = 20;

            // Build base query for attendance - only count 'hadir' status
            $baseQuery = Attendance::query()
                ->whereNotNull('checkin_time')
                ->where('status', \App\Enums\AttendanceStatus::HADIR->value)
                ->where('meeting_id', $ref->id);

            // Add target filtering
            $baseQuery->whereHas('member', function ($q) use ($ref) {
                $q->where('status', true);

                if ($ref->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                    $q->where('gender', $ref->target_gender->value);
                }

                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
                $selectedAgeGroupsCount = empty($ref->target_age_groups) ? 0 : count($ref->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                if ($shouldFilterByAge) {
                    $q->whereHas('ageGroup', function ($aq) use ($ref) {
                        return $aq->whereIn('name', (array) $ref->target_age_groups);
                    });
                }
            });

            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if ($filterGroupId) {
                $baseQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds()));
            } elseif ($filterLevelId) {
                $baseQuery->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $baseQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            // Get first and last checkin time
            $firstCheckin = (clone $baseQuery)->orderBy('checkin_time')->value('checkin_time');
            $lastCheckin = (clone $baseQuery)->orderByDesc('checkin_time')->value('checkin_time');

            if (! $firstCheckin || ! $lastCheckin) {
                return [
                    'datasets' => [],
                    'labels' => [],
                ];
            }

            $firstTime = Carbon::parse($firstCheckin);
            $lastTime = Carbon::parse($lastCheckin);

            // Calculate total duration in minutes
            $totalMinutes = $firstTime->diffInMinutes($lastTime);
            if ($totalMinutes < 1) {
                $totalMinutes = 1;
            }

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

            // Query attendance data separated by method
            $scanData = (clone $baseQuery)
                ->where('method', \App\Enums\AttendanceMethod::QR_CODE->value)
                ->select(
                    DB::raw('HOUR(checkin_time) as hour'),
                    DB::raw('FLOOR(MINUTE(checkin_time) / '.$intervalMinutes.') * '.$intervalMinutes.' as minute_slot'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('hour', 'minute_slot')
                ->orderBy('hour')
                ->orderBy('minute_slot')
                ->get()
                ->keyBy(function ($item) {
                    return $item->hour.'_'.$item->minute_slot;
                });

            $manualData = (clone $baseQuery)
                ->where('method', \App\Enums\AttendanceMethod::MANUAL->value)
                ->select(
                    DB::raw('HOUR(checkin_time) as hour'),
                    DB::raw('FLOOR(MINUTE(checkin_time) / '.$intervalMinutes.') * '.$intervalMinutes.' as minute_slot'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('hour', 'minute_slot')
                ->orderBy('hour')
                ->orderBy('minute_slot')
                ->get()
                ->keyBy(function ($item) {
                    return $item->hour.'_'.$item->minute_slot;
                });

            $selfData = (clone $baseQuery)
                ->where('method', \App\Enums\AttendanceMethod::SELF->value)
                ->select(
                    DB::raw('HOUR(checkin_time) as hour'),
                    DB::raw('FLOOR(MINUTE(checkin_time) / '.$intervalMinutes.') * '.$intervalMinutes.' as minute_slot'),
                    DB::raw('count(*) as count')
                )
                ->groupBy('hour', 'minute_slot')
                ->orderBy('hour')
                ->orderBy('minute_slot')
                ->get()
                ->keyBy(function ($item) {
                    return $item->hour.'_'.$item->minute_slot;
                });

            // Generate labels and values for time slots
            $labels = [];
            $scanValues = [];
            $manualValues = [];
            $selfValues = [];
            $current = $start->copy();

            while ($current->lessThanOrEqualTo($end)) {
                $key = $current->hour.'_'.$current->minute;
                $labels[] = $current->format('H:i');
                $scanValues[] = $scanData->get($key)?->count ?? 0;
                $manualValues[] = $manualData->get($key)?->count ?? 0;
                $selfValues[] = $selfData->get($key)?->count ?? 0;
                $current->addMinutes($intervalMinutes);

                // Safety: max 30 points to avoid infinite loop
                if (count($labels) >= 30) {
                    break;
                }
            }

            $totalScan = array_sum($scanValues);
            $totalManual = array_sum($manualValues);
            $totalSelf = array_sum($selfValues);
            $totalGlobal = $totalScan + $totalManual + $totalSelf;

            $pctScan = $totalGlobal > 0 ? number_format(($totalScan / $totalGlobal) * 100, 1) : 0;
            $pctManual = $totalGlobal > 0 ? number_format(($totalManual / $totalGlobal) * 100, 1) : 0;
            $pctSelf = $totalGlobal > 0 ? number_format(($totalSelf / $totalGlobal) * 100, 1) : 0;

            $datasets = [];

            if ($totalScan > 0) {
                $datasets[] = [
                    'label' => "QR Code: {$totalScan} ({$pctScan}%)",
                    'data' => $scanValues,
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
                ];
            }

            if ($totalManual > 0) {
                $datasets[] = [
                    'label' => "Manual: {$totalManual} ({$pctManual}%)",
                    'data' => $manualValues,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.08)',
                    'fill' => 'start',
                    'tension' => 0.4,
                    'borderWidth' => 2.5,
                    'pointRadius' => 3,
                    'pointBackgroundColor' => '#3b82f6',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointHoverRadius' => 6,
                ];
            }

            if ($totalSelf > 0) {
                $datasets[] = [
                    'label' => "Mandiri: {$totalSelf} ({$pctSelf}%)",
                    'data' => $selfValues,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.08)',
                    'fill' => 'start',
                    'tension' => 0.4,
                    'borderWidth' => 2.5,
                    'pointRadius' => 3,
                    'pointBackgroundColor' => '#f59e0b',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointHoverRadius' => 6,
                ];
            }

            return [
                'datasets' => $datasets,
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
            'maintainAspectRatio' => false,
            'aspectRatio' => 1.5,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 16,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
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
                        'display' => true,
                        'color' => 'rgba(255, 255, 255, 0.1)',
                        'drawBorder' => false,
                    ],
                ],
            ],
        ];
    }
}
