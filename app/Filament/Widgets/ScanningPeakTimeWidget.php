<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\AgeGroup;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ScanningPeakTimeWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 9;
    
    // Responsive column span
    protected int|string|array $columnSpan = 'full';
    
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
        return 'Beban Aktivitas Scanner';
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        $total = 0;
        if (!empty($data['datasets'])) {
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
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();
        $cacheKey = 'scanning_peak_' . ($user->group_id ?? 'all') . '_' . ($ref->id ?? 'today');

        return Cache::remember($cacheKey, 120, function () use ($user, $ref) {
            $targetPoints = 20;

            // Build base query for attendance - only count 'hadir' status
            $baseQuery = Attendance::query()
                ->whereNotNull('checkin_time')
                ->where('status', 'hadir');

            if ($ref) {
                $baseQuery->where('meeting_id', $ref->id);

                // Add target filtering
                $baseQuery->whereHas('member', function ($q) use ($ref) {
                    $q->where('status', true);

                    if ($ref->target_gender !== 'all') {
                        $q->where('gender', $ref->target_gender);
                    }

                    $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => AgeGroup::count());
                    $selectedAgeGroupsCount = empty($ref->target_age_groups) ? 0 : count($ref->target_age_groups);
                    $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                    if ($shouldFilterByAge) {
                        $q->whereHas('ageGroup', function ($aq) use ($ref) {
                            return $aq->whereIn('name', (array) $ref->target_age_groups);
                        });
                    }
                });
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
                    'datasets' => [
                        ['label' => 'QR Code', 'data' => [], 'borderColor' => '#10b981', 'backgroundColor' => 'rgba(16, 185, 129, 0.1)', 'fill' => 'start', 'tension' => 0.4],
                        ['label' => 'Manual', 'data' => [], 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59, 130, 246, 0.1)', 'fill' => 'start', 'tension' => 0.4],
                    ],
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

            // Query attendance data separated by method (qr_code vs manual)
            $scanData = (clone $baseQuery)
                ->where('method', 'qr_code')
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

            $manualData = (clone $baseQuery)
                ->where('method', 'manual')
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
            $scanValues = [];
            $manualValues = [];
            $current = $start->copy();

            while ($current->lessThanOrEqualTo($end)) {
                $key = $current->hour . '_' . $current->minute;
                $labels[] = $current->format('H:i');
                $scanValues[] = $scanData->get($key)?->count ?? 0;
                $manualValues[] = $manualData->get($key)?->count ?? 0;
                $current->addMinutes($intervalMinutes);

                // Safety: max 30 points to avoid infinite loop
                if (count($labels) >= 30) break;
            }

            $totalScan = array_sum($scanValues);
            $totalManual = array_sum($manualValues);
            $totalGlobal = $totalScan + $totalManual;

            $pctScan = $totalGlobal > 0 ? number_format(($totalScan / $totalGlobal) * 100, 1) : 0;
            $pctManual = $totalGlobal > 0 ? number_format(($totalManual / $totalGlobal) * 100, 1) : 0;

            return [
                'datasets' => [
                    [
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
                    ],
                    [
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
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
            ],
        ];
    }
}
