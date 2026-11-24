<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgeGroupParticipationWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;

    protected static ?int $sort = 3;

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
        return 'Aktivitas Anggota Berdasarkan Usia';
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

        $cacheKey = 'age_participation_v3_'.($user->group_id ?? 'all').'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 180, fn () => $this->getParticipationStats($user, $ref));
    }

    private function getParticipationStats($user, Meeting $ref): array
    {
        $targetAgeGroups = $ref->target_age_groups ?? [];
        $ageGroups = $this->getFilteredAgeGroups($targetAgeGroups);
        $allowedGroupIds = $this->getAllowedGroupIds($user);

        $memberCounts = $this->getMemberCounts($allowedGroupIds, $targetAgeGroups);
        $attendanceCounts = $this->getAttendanceCounts($ref->id, $allowedGroupIds, $targetAgeGroups);

        $hadirData = [];
        $izinSakitData = [];
        $tidakHadirData = [];
        $labels = [];

        foreach ($ageGroups as $ageGroup) {
            $totalMembers = $memberCounts[$ageGroup->id] ?? 0;
            if ($totalMembers === 0) {
                continue;
            }

            $counts = $attendanceCounts->get($ageGroup->id, collect());
            $hadirCount = $counts->where('status', AttendanceStatus::HADIR->value)->sum('count');
            $izinCount = $counts->where('status', AttendanceStatus::IZIN->value)->sum('count');
            $sakitCount = $counts->where('status', AttendanceStatus::SAKIT->value)->sum('count');

            // Hitung hanya target dasar yang terekam hadir/izin/sakit
            $targetAttended = $counts->where('member_target', true)->whereIn('status', [
                AttendanceStatus::HADIR->value,
                AttendanceStatus::IZIN->value,
                AttendanceStatus::SAKIT->value,
            ])->sum('count');

            $izinSakitCount = $izinCount + $sakitCount;
            $tidakHadirCount = max(0, $totalMembers - $targetAttended);

            $hadirData[] = $hadirCount;
            $izinSakitData[] = $izinSakitCount;
            $tidakHadirData[] = $tidakHadirCount;
            $labels[] = $ageGroup->name;
        }

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

    private function getFilteredAgeGroups(array $targetAgeGroups)
    {
        $query = AgeGroup::orderBy('sort_order');
        if (! empty($targetAgeGroups)) {
            $query->whereIn('name', $targetAgeGroups);
        }
        $ageGroups = $query->get();

        return $ageGroups->isEmpty() ? AgeGroup::orderBy('sort_order')->get() : $ageGroups;
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
            ->select('age_group_id', DB::raw('count(*) as total'))
            ->groupBy('age_group_id')
            ->pluck('total', 'age_group_id');
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
            ->select('members.age_group_id', 'members.status as member_target', 'attendances.status', DB::raw('count(*) as count'))
            ->groupBy('members.age_group_id', 'members.status', 'attendances.status')
            ->get()
            ->groupBy('age_group_id');
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
