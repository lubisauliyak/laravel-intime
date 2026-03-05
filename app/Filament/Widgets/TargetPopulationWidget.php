<?php

namespace App\Filament\Widgets;

use App\Models\AgeGroup;
use App\Models\Meeting;
use App\Models\Member;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TargetPopulationWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 5;
    
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
        return 'Populasi Anggota Berdasarkan Usia';
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

        $cacheKey = 'target_population_v2_' . ($user->group_id ?? 'all') . '_' . $ref->id;

        return Cache::remember($cacheKey, 300, function () use ($user, $ref) {
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

            $memberQuery = Member::where('status', true)
                ->when($allowedGroupIds, fn($q) => $q->whereIn('group_id', $allowedGroupIds));

            if (!empty($targetAgeGroups)) {
                $memberQuery->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', $targetAgeGroups));
            }

            $memberCounts = $memberQuery
                ->select('age_group_id', DB::raw('count(*) as total'))
                ->groupBy('age_group_id')
                ->pluck('total', 'age_group_id');

            $data = [];
            $rawLabels = [];
            $colors = [
                '#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#a855f7',
                '#ec4899', '#06b6d4', '#f97316', '#14b8a6', '#6366f1'
            ];

            foreach ($ageGroups as $ageGroup) {
                $count = $memberCounts[$ageGroup->id] ?? 0;
                $rawLabels[] = $ageGroup->name;
                $data[] = $count;
            }

            $total = array_sum($data);
            $labels = [];
            foreach ($data as $index => $count) {
                $percent = $total > 0 ? number_format(($count / $total) * 100, 1) : 0;
                $labels[] = "{$rawLabels[$index]}: {$count} ({$percent}%)";
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Jumlah Anggota',
                        'data' => $data,
                        'backgroundColor' => array_slice($colors, 0, count($data)),
                        'borderColor' => array_fill(0, count($data), '#ffffff'),
                        'borderWidth' => 2,
                        'spacing' => 3,
                        'hoverOffset' => 8,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        if (empty($data['datasets']) || empty($data['datasets'][0]['data'])) {
            return 'Belum ada data target';
        }

        $total = array_sum($data['datasets'][0]['data']);
        return "Total : {$total} Anggota";
    }

    protected function getType(): string
    {
        return 'pie';
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
        ];
    }
}
