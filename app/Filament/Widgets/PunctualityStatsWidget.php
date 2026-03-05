<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class PunctualityStatsWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];
    protected ?string $maxHeight = '280px';
    protected ?string $minHeight = '280px';

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
        return 'Distribusi Ketepatan Waktu';
    }

    private function getPunctualityData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();
        $cacheKey = 'punctuality_stats_' . ($user->group_id ?? 'all') . '_' . ($ref->id ?? 'today');

        return Cache::remember($cacheKey, 180, function () use ($user, $ref) {
            $query = Attendance::query();
            if ($ref) {
                $query->where('meeting_id', $ref->id);
            } else {
                $query->whereDate('checkin_time', now());
            }

            if (!$user->isSuperAdmin() && $user->group_id) {
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            } else {
                $query->whereHas('member', fn($q) => $q->where('status', true));
            }

            // Filter by target audience (gender and age groups) from meeting
            if ($ref) {
                $query->whereHas('member', function ($q) use ($ref) {
                    if ($ref->target_gender !== 'all') {
                        $q->where('gender', $ref->target_gender);
                    }

                    $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => \App\Models\AgeGroup::count());
                    $selectedAgeGroupsCount = empty($ref->target_age_groups) ? 0 : count($ref->target_age_groups);
                    $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                    if ($shouldFilterByAge) {
                        $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', (array) $ref->target_age_groups));
                    }
                });
            }

            $query->where('status', 'hadir');

            $total = $query->count();
            if ($total === 0) {
                return [
                    'total' => 0,
                    'late' => 0,
                    'on_time' => 0,
                    'on_time_percent' => 0,
                    'late_percent' => 0,
                ];
            }

            $lateCount = (clone $query)->where('notes', 'LIKE', '%TERLAMBAT%')->count();
            $onTimeCount = max(0, $total - $lateCount);
            
            return [
                'total' => $total,
                'late' => $lateCount,
                'on_time' => $onTimeCount,
                'on_time_percent' => number_format(($onTimeCount / $total) * 100, 1),
                'late_percent' => number_format(($lateCount / $total) * 100, 1),
            ];
        });
    }

    public function getDescription(): ?string
    {
        $data = $this->getPunctualityData();
        if ($data['total'] === 0) {
            return 'Belum ada data kehadiran';
        }

        return "Total : {$data['total']} Anggota";
    }

    protected function getData(): array
    {
        $data = $this->getPunctualityData();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Kehadiran',
                    'data' => [$data['on_time'], $data['late']],
                    'backgroundColor' => ['#22c55e', '#ef4444'],
                    'borderColor' => ['#ffffff', '#ffffff'],
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'spacing' => 3,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => [
                "Tepat Waktu: {$data['on_time']} ({$data['on_time_percent']}%)",
                "Terlambat: {$data['late']} ({$data['late_percent']}%)"
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
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
            'cutout' => '65%',
        ];
    }
}
