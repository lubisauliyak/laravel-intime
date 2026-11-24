<?php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class TargetGenderDistributionWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

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
        $ref = $this->getReferenceMeeting();

        return 'Populasi Anggota Berdasarkan Gender';
    }

    private function getGenderData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();

        if (! $ref) {
            return [
                Gender::MALE->value => 0,
                Gender::FEMALE->value => 0,
                'total' => 0,
                'male_percent' => 0,
                'female_percent' => 0,
            ];
        }

        $cacheKey = 'target_gender_dist_'.($user->group_id ?? 'all').'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user, $ref) {
            $targetAgeGroups = $ref->target_age_groups ?? [];

            $query = Member::where('status', true);

            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if ($filterGroupId) {
                $query->whereIn('group_id', \App\Models\Group::find($filterGroupId)->getAllDescendantIds());
            } elseif ($filterLevelId) {
                $query->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif ($user && ! $user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
                $query->whereIn('group_id', $allowedGroupIds);
            }

            // Filter by target age groups from meeting
            if (! empty($targetAgeGroups)) {
                $query->whereHas('ageGroup', fn ($aq) => $aq->whereIn('name', $targetAgeGroups));
            }

            $data = $query->selectRaw('gender, count(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray();

            $maleCount = $data[Gender::MALE->value] ?? 0;
            $femaleCount = $data[Gender::FEMALE->value] ?? 0;
            $total = $maleCount + $femaleCount;

            $malePercent = $total > 0 ? number_format(($maleCount / $total) * 100, 1) : 0;
            $femalePercent = $total > 0 ? number_format(($femaleCount / $total) * 100, 1) : 0;

            return [
                Gender::MALE->value => $maleCount,
                Gender::FEMALE->value => $femaleCount,
                'total' => $total,
                'male_percent' => $malePercent,
                'female_percent' => $femalePercent,
            ];
        });
    }

    public function getDescription(): ?string
    {
        $data = $this->getGenderData();

        if ($data['total'] === 0) {
            return 'Belum ada data anggota sesuai target';
        }

        return "Total : {$data['total']} Anggota";
    }

    protected function getData(): array
    {
        $data = $this->getGenderData();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Anggota',
                    'data' => [$data[Gender::MALE->value], $data[Gender::FEMALE->value]],
                    'backgroundColor' => ['#3b82f6', '#f472b6'],
                    'borderColor' => ['#ffffff', '#ffffff'],
                    'borderWidth' => 2,
                    'spacing' => 3,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => [
                "Laki-laki: {$data[Gender::MALE->value]} ({$data['male_percent']}%)",
                "Perempuan: {$data[Gender::FEMALE->value]} ({$data['female_percent']}%)",
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
}
