<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\AgeGroup;
use App\Models\Member;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AgeGroupParticipationWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 8;
    
    // Responsive column span
    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

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
        return 'Aktivitas Anggota Berdasarkan Usia';
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

        if (!$ref) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $cacheKey = 'age_participation_v2_' . ($user->group_id ?? 'all') . '_' . $ref->id;

        return Cache::remember($cacheKey, 180, function () use ($user, $ref) {
            $targetAgeGroups = $ref->target_age_groups ?? [];
            
            $ageGroupsQuery = AgeGroup::orderBy('sort_order');
            if (!empty($targetAgeGroups)) {
                $ageGroupsQuery->whereIn('name', $targetAgeGroups);
            }
            $ageGroups = $ageGroupsQuery->get();

            if ($ageGroups->isEmpty()) {
                $ageGroups = AgeGroup::orderBy('sort_order')->get();
            }

            $allowedGroupIds = null;
            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
            }

            $memberCounts = Member::where('status', true)
                ->when($allowedGroupIds, fn($q) => $q->whereIn('group_id', $allowedGroupIds))
                ->when(!empty($targetAgeGroups), fn($q) => $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', $targetAgeGroups)))
                ->select('age_group_id', DB::raw('count(*) as total'))
                ->groupBy('age_group_id')
                ->pluck('total', 'age_group_id');

            $attendanceCounts = Attendance::where('meeting_id', $ref->id)
                ->whereHas('member', function ($q) use ($allowedGroupIds, $targetAgeGroups) {
                    $q->where('status', true);
                    if ($allowedGroupIds) {
                        $q->whereIn('group_id', $allowedGroupIds);
                    }
                    if (!empty($targetAgeGroups)) {
                        $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', $targetAgeGroups));
                    }
                })
                ->join('members', 'attendances.member_id', '=', 'members.id')
                ->select('members.age_group_id', 'attendances.status', DB::raw('count(*) as count'))
                ->groupBy('members.age_group_id', 'attendances.status')
                ->get()
                ->groupBy('age_group_id');

            $hadirData = [];
            $izinSakitData = [];
            $tidakHadirData = [];
            $labels = [];

            foreach ($ageGroups as $ageGroup) {
                $totalMembers = $memberCounts[$ageGroup->id] ?? 0;
                if ($totalMembers === 0) continue;

                $counts = $attendanceCounts->get($ageGroup->id, collect());
                $hadirCount = $counts->where('status', 'hadir')->sum('count');
                $izinCount = $counts->where('status', 'izin')->sum('count');
                $sakitCount = $counts->where('status', 'sakit')->sum('count');
                $alphaCount = $counts->where('status', 'alpha')->sum('count');

                $totalRecorded = $hadirCount + $izinCount + $sakitCount + $alphaCount;
                $tidakHadirCount = $totalMembers - $totalRecorded;

                $labels[] = $ageGroup->name;
                $hadirData[] = $hadirCount;
                $izinSakitData[] = $izinCount + $sakitCount;
                $tidakHadirData[] = $tidakHadirCount + $alphaCount;
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
                        'label' => "Hadir: {$totalHadir} ({$pctHadir}%)",
                        'data' => $hadirData,
                        'backgroundColor' => '#22c55e',
                        'borderRadius' => 6,
                        'borderSkipped' => false,
                    ],
                    [
                        'label' => "Izin/Sakit: {$totalIzinSakit} ({$pctIzinSakit}%)",
                        'data' => $izinSakitData,
                        'backgroundColor' => '#facc15',
                        'borderRadius' => 6,
                        'borderSkipped' => false,
                    ],
                    [
                        'label' => "Tidak Hadir: {$totalTidakHadir} ({$pctTidakHadir}%)",
                        'data' => $tidakHadirData,
                        'backgroundColor' => '#ef4444',
                        'borderRadius' => 6,
                        'borderSkipped' => false,
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
            'barPercentage' => 0.7,
            'categoryPercentage' => 0.8,
        ];
    }
}
