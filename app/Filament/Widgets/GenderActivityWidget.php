<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenderActivityWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;

    protected static ?int $sort = 5;

    // Responsive column span
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

    private function getReferenceMeeting(): ?Meeting
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? null;

        return $filterMeetingId ? Meeting::find($filterMeetingId) : null;
    }

    public function getHeading(): ?string
    {
        return 'Aktivitas Anggota Berdasarkan Gender';
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        if (empty($data['datasets'])) {
            return 'Belum ada data aktivitas';
        }

        $hadir = array_sum($data['datasets'][0]['data']);
        $izinSakit = array_sum($data['datasets'][1]['data']);
        $tidakHadir = array_sum($data['datasets'][2]['data']);
        $total = $hadir + $izinSakit + $tidakHadir;

        if ($total === 0) {
            return 'Belum ada data partisipasi';
        }

        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();

        if (! $ref) {
            return ['datasets' => [], 'labels' => []];
        }

        $cacheKey = 'gender_activity_v2_'.($user->group_id ?? 'all').'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 180, fn () => $this->getGenderActivityStats($user, $ref));
    }

    private function getGenderActivityStats($user, Meeting $ref): array
    {
        $targetAgeGroups = $ref->target_age_groups ?? [];
        $allowedGroupIds = $this->getAllowedGroupIds($user);

        $memberCounts = $this->getMemberCounts($allowedGroupIds, $targetAgeGroups);
        $attendanceCounts = $this->getAttendanceCounts($ref->id, $allowedGroupIds, $targetAgeGroups);

        $genders = [
            Gender::MALE->value => Gender::MALE->getLabel(),
            Gender::FEMALE->value => Gender::FEMALE->getLabel(),
        ];

        $hadirData = [];
        $izinSakitData = [];
        $tidakHadirData = [];
        $labels = [];

        foreach ($genders as $genderKey => $genderLabel) {
            $totalMembers = $memberCounts[$genderKey] ?? 0;
            if ($totalMembers === 0) {
                continue;
            }

            $counts = $attendanceCounts->get($genderKey, collect());
            $hadirCount = $counts->where('status', AttendanceStatus::HADIR->value)->sum('count');
            $izinCount = $counts->where('status', AttendanceStatus::IZIN->value)->sum('count');
            $sakitCount = $counts->where('status', AttendanceStatus::SAKIT->value)->sum('count');
            $alphaCount = $counts->where('status', AttendanceStatus::ALFA->value)->sum('count');

            // Hitung hanya target dasar yang terekam hadir/izin/alpa
            $targetRecorded = $counts->where('member_target', true)->whereIn('status', [
                AttendanceStatus::HADIR->value,
                AttendanceStatus::IZIN->value,
                AttendanceStatus::SAKIT->value,
                AttendanceStatus::ALFA->value,
            ])->sum('count');

            $tidakHadirCount = max(0, $totalMembers - $targetRecorded);

            $labels[] = $genderLabel;
            $hadirData[] = $hadirCount;
            $izinSakitData[] = $izinCount + $sakitCount;
            $tidakHadirData[] = $tidakHadirCount + $alphaCount;
        }

        return $this->formatChartData($hadirData, $izinSakitData, $tidakHadirData, $labels);
    }

    private function getAllowedGroupIds($user): ?array
    {
        $filterGroupId = $this->filters['group_id'] ?? null;
        $filterLevelId = $this->filters['level_id'] ?? null;

        if ($filterGroupId) {
            return \App\Models\Group::find($filterGroupId)->getAllDescendantIds();
        }
        if ($filterLevelId) {
            return \App\Models\Group::where('level_id', $filterLevelId)->get()->flatMap->getAllDescendantIds()->unique()->toArray();
        }
        if ($user && ! $user->isSuperAdmin() && $user->group_id) {
            return $user->group->getAllDescendantIds();
        }

        return null;
    }

    private function getMemberCounts(?array $allowedGroupIds, array $targetAgeGroups)
    {
        return Member::where('status', true)
            ->when($allowedGroupIds, fn ($q) => $q->whereIn('group_id', $allowedGroupIds))
            ->when(! empty($targetAgeGroups), fn ($q) => $q->whereHas('ageGroup', fn ($aq) => $aq->whereIn('name', $targetAgeGroups)))
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender');
    }

    private function getAttendanceCounts(int $meetingId, ?array $allowedGroupIds, array $targetAgeGroups)
    {
        return Attendance::where('meeting_id', $meetingId)
            ->whereHas('member', function ($q) use ($allowedGroupIds, $targetAgeGroups) {
                if ($allowedGroupIds) {
                    $q->whereIn('group_id', $allowedGroupIds);
                }
                if (! empty($targetAgeGroups)) {
                    $q->whereHas('ageGroup', fn ($aq) => $aq->whereIn('name', $targetAgeGroups));
                }
            })
            ->join('members', 'attendances.member_id', '=', 'members.id')
            ->select('members.gender', 'members.status as member_target', 'attendances.status', DB::raw('count(*) as count'))
            ->groupBy('members.gender', 'members.status', 'attendances.status')
            ->get()
            ->groupBy('gender');
    }

    private function formatChartData(array $hadirData, array $izinSakitData, array $tidakHadirData, array $labels): array
    {
        $totalHadir = array_sum($hadirData);
        $totalIzinSakit = array_sum($izinSakitData);
        $totalTidakHadir = array_sum($tidakHadirData);
        $totalGlobal = $totalHadir + $totalIzinSakit + $totalTidakHadir;

        $pctHadir = $totalGlobal > 0 ? number_format(($totalHadir / $totalGlobal) * 100, 1) : 0;
        $pctIzinSakit = $totalGlobal > 0 ? number_format(($totalIzinSakit / $totalGlobal) * 100, 1) : 0;
        $pctTidakHadir = $totalGlobal > 0 ? number_format(($totalTidakHadir / $totalGlobal) * 100, 1) : 0;

        return [
            'datasets' => [
                [
                    'label' => "H: {$totalHadir} ({$pctHadir}%)",
                    'data' => $hadirData,
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => "I/S: {$totalIzinSakit} ({$pctIzinSakit}%)",
                    'data' => $izinSakitData,
                    'backgroundColor' => '#facc15',
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => "A: {$totalTidakHadir} ({$pctTidakHadir}%)",
                    'data' => $tidakHadirData,
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $labels,
        ];
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
            'barPercentage' => 0.7,
            'categoryPercentage' => 0.8,
        ];
    }
}
