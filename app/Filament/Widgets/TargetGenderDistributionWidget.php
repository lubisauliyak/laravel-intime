<?php

namespace App\Filament\Widgets;

use App\Models\AgeGroup;
use App\Models\Meeting;
use App\Models\Member;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class TargetGenderDistributionWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];
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
        return 'Gender Anggota Berdasarkan Usia';
    }

    private function getGenderData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();

        if (!$ref) {
            return [
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'male_percent' => 0,
                'female_percent' => 0,
            ];
        }

        $cacheKey = 'target_gender_dist_' . ($user->group_id ?? 'all') . '_' . $ref->id;

        return Cache::remember($cacheKey, 300, function () use ($user, $ref) {
            $targetAgeGroups = $ref->target_age_groups ?? [];
            
            $query = Member::where('status', true);

            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
                $query->whereIn('group_id', $allowedGroupIds);
            }

            // Filter by target age groups from meeting
            if (!empty($targetAgeGroups)) {
                $query->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', $targetAgeGroups));
            }

            $data = $query->selectRaw('gender, count(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender')
                ->toArray();

            $maleCount = $data['male'] ?? 0;
            $femaleCount = $data['female'] ?? 0;
            $total = $maleCount + $femaleCount;

            $malePercent = $total > 0 ? number_format(($maleCount / $total) * 100, 1) : 0;
            $femalePercent = $total > 0 ? number_format(($femaleCount / $total) * 100, 1) : 0;

            return [
                'male' => $maleCount,
                'female' => $femaleCount,
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
                    'data' => [$data['male'], $data['female']],
                    'backgroundColor' => ['#3b82f6', '#f472b6'],
                    'borderColor' => ['#ffffff', '#ffffff'],
                    'borderWidth' => 2,
                    'spacing' => 3,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => [
                "Laki-laki: {$data['male']} ({$data['male_percent']}%)",
                "Perempuan: {$data['female']} ({$data['female_percent']}%)"
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
