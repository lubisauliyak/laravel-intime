<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Meeting;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PunctualityScansWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    public function getExtraAttributes(): array
    {
        return [
            'style' => 'min-height: 480px',
        ];
    }

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return 'Ketepatan Waktu Berdasarkan Usia';
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
            return 'Belum ada data kedatangan';
        }

        return "Total : {$total} Anggota";
    }

    private function getReferenceMeeting(): ?Meeting
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? null;

        return $filterMeetingId ? Meeting::find($filterMeetingId) : null;
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();
        $cacheKey = 'punctuality_age_only_v1_'.($user->group_id ?? 'all').'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 180, function () use ($user, $ref) {
            if (! $ref) {
                return ['datasets' => [], 'labels' => []];
            }

            $targetAgeGroups = (array) ($ref->target_age_groups ?? []);

            $ageGroupsQuery = AgeGroup::orderBy('sort_order');
            if (! empty($targetAgeGroups)) {
                $ageGroupsQuery->whereIn('name', $targetAgeGroups);
            }
            $ageGroups = $ageGroupsQuery->get();

            if ($ageGroups->isEmpty()) {
                $ageGroups = AgeGroup::orderBy('sort_order')->get();
            }

            $labels = $ageGroups->pluck('name')->toArray();

            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            $allowedGroupIds = null;
            if ($filterGroupId) {
                $allowedGroupIds = \App\Models\Group::find($filterGroupId)->getAllDescendantIds();
            } elseif ($filterLevelId) {
                $allowedGroupIds = \App\Models\Group::where('level_id', $filterLevelId)->get()->flatMap->getAllDescendantIds()->unique()->toArray();
            } elseif ($user && ! $user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
            }

            // Get attendance data grouped by age_group and lateness
            $attendanceQuery = Attendance::where('meeting_id', $ref->id)
                ->where('attendances.status', AttendanceStatus::HADIR)
                ->join('members', 'attendances.member_id', '=', 'members.id')
                ->select(
                    'members.age_group_id',
                    DB::raw("CASE WHEN attendances.notes LIKE '%TERLAMBAT%' THEN 'LATE' ELSE 'ONTIME' END as punctuality"),
                    DB::raw('count(*) as count')
                )
                ->when($allowedGroupIds, fn ($q) => $q->whereIn('members.group_id', $allowedGroupIds))
                ->groupBy('members.age_group_id', 'punctuality')
                ->get();

            $onTimeData = [];
            $lateData = [];

            foreach ($ageGroups as $group) {
                $onTimeData[] = $attendanceQuery->where('age_group_id', $group->id)->where('punctuality', 'ONTIME')->sum('count');
                $lateData[] = $attendanceQuery->where('age_group_id', $group->id)->where('punctuality', 'LATE')->sum('count');
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Tepat Waktu',
                        'data' => $onTimeData,
                        'backgroundColor' => '#22c55e',
                        'borderColor' => '#ffffff',
                        'borderWidth' => 2,
                        'borderRadius' => 4,
                    ],
                    [
                        'label' => 'Terlambat',
                        'data' => $lateData,
                        'backgroundColor' => '#ef4444',
                        'borderColor' => '#ffffff',
                        'borderWidth' => 2,
                        'borderRadius' => 4,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'aspectRatio' => 1,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => false,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'stacked' => false,
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
