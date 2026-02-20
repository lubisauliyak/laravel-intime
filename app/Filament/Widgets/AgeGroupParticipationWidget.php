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

    protected static ?int $sort = 5;
    
    // Responsive column span
    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    
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
            return 'Aktivitas Berdasarkan Usia';
        }
        return 'Aktivitas Berdasarkan Usia (' . $ref->meeting_date->format('d/m/Y') . ')';
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

        $cacheKey = 'age_participation_' . ($user->group_id ?? 'all') . '_' . $ref->id;

        return Cache::remember($cacheKey, 300, function () use ($user, $ref) {
            // Get target age groups from meeting (only show targeted categories)
            $targetAgeGroups = $ref->target_age_groups ?? [];
            
            // Get age groups that are in the target list
            $ageGroupsQuery = AgeGroup::orderBy('sort_order');
            if (!empty($targetAgeGroups)) {
                $ageGroupsQuery->whereIn('name', $targetAgeGroups);
            }
            $ageGroups = $ageGroupsQuery->get();

            // If no age groups found (meeting targets all ages), get all age groups
            if ($ageGroups->isEmpty()) {
                $ageGroups = AgeGroup::orderBy('sort_order')->get();
            }

            // Get descendant IDs if not super admin
            $allowedGroupIds = null;
            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
            }

            // Get total members per age group (including pengurus) - only for target age groups
            $memberCounts = Member::where('status', true)
                ->when($allowedGroupIds, fn($q) => $q->whereIn('group_id', $allowedGroupIds))
                ->when(!empty($targetAgeGroups), fn($q) => $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', $targetAgeGroups)))
                ->select('age_group_id', DB::raw('count(*) as total'))
                ->groupBy('age_group_id')
                ->pluck('total', 'age_group_id');

            // Get attendance counts per age group and status (including pengurus)
            $attendanceCounts = Attendance::where('meeting_id', $ref->id)
                ->whereHas('member', function ($q) use ($allowedGroupIds, $targetAgeGroups) {
                    $q->where('status', true);
                    if ($allowedGroupIds) {
                        $q->whereIn('group_id', $allowedGroupIds);
                    }
                    // Only count attendance for members in target age groups
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

                if ($totalMembers === 0) {
                    continue;
                }

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

            return [
                'datasets' => [
                    [
                        'label' => 'Hadir',
                        'data' => $hadirData,
                        'backgroundColor' => '#10b981',
                    ],
                    [
                        'label' => 'Izin/Sakit',
                        'data' => $izinSakitData,
                        'backgroundColor' => '#f59e0b',
                    ],
                    [
                        'label' => 'Tidak Hadir',
                        'data' => $tidakHadirData,
                        'backgroundColor' => '#ef4444',
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
