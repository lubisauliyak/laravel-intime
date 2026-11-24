<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class TargetPopulationWidget extends ChartWidget
{
    public $filters = [];

    protected ?string $heading = 'Populasi Anggota Berdasarkan Usia';

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected ?string $maxHeight = '300px';

    public function getExtraAttributes(): array
    {
        return ['style' => 'min-height: 480px'];
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
        ];
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

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_pop_age_'.($user->id).'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            $meeting = $filterMeetingId ? Meeting::find($filterMeetingId) : null;

            $query = Member::where('members.status', true);
            if ($filterGroupId) {
                $query->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds());
            } elseif ($filterLevelId) {
                $query->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $query->whereIn('group_id', $user->group->getAllDescendantIds());
            }

            if ($meeting) {
                if ($meeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                    $query->where('gender', $meeting->target_gender->value);
                }
                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
                $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                if ($selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount) {
                    $query->whereHas('ageGroup', fn ($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
                }
            }

            $data = $query->join('age_groups', 'members.age_group_id', '=', 'age_groups.id')
                ->selectRaw('age_groups.name, count(*) as total')
                ->groupBy('age_groups.name')
                ->pluck('total', 'name');

            return [
                'datasets' => [['label' => 'Anggota', 'data' => $data->values()->toArray(), 'backgroundColor' => ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899']]],
                'labels' => $data->keys()->toArray(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
