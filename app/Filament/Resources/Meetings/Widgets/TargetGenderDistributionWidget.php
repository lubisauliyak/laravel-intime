<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class TargetGenderDistributionWidget extends ChartWidget
{
    public $filters = [];

    protected ?string $heading = 'Populasi Anggota Berdasarkan Gender';

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
            'cutout' => '65%',
        ];
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        $total = 0;
        if (! empty($data['datasets'][0]['data'])) {
            $total = array_sum($data['datasets'][0]['data']);
        }
        if ($total === 0) {
            return 'Belum ada data anggota sesuai target';
        }

        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_pop_gen_'.($user->id).'_'.md5(json_encode($this->filters));

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

            $data = $query->selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total', 'gender');
            $labels = $data->keys()->map(fn ($g) => $g === 'male' ? 'LAKI-LAKI' : 'PEREMPUAN')->toArray();

            return [
                'datasets' => [['label' => 'Anggota', 'data' => $data->values()->toArray(), 'backgroundColor' => ['#3b82f6', '#ec4899']]],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
