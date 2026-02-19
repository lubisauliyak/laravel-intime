<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class GenderDistributionWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];
    protected ?string $heading = 'Komposisi Jenis Kelamin';
    protected ?string $maxHeight = '250px';

    private function getGenderData(): array
    {
        $user = auth()->user();
        $cacheKey = 'gender_dist_' . ($user->group_id ?? 'all');

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $query = Member::where('status', true)
                ->where('membership_type', '!=', 'pengurus');

            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
                $query->whereIn('group_id', $allowedGroupIds);
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
            return 'Belum ada data anggota';
        }

        return "Total: {$data['total']} • Laki-laki: {$data['male']} ({$data['male_percent']}%) • Perempuan: {$data['female']} ({$data['female_percent']}%)";
    }

    protected function getData(): array
    {
        $data = $this->getGenderData();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Anggota',
                    'data' => [$data['male'], $data['female']],
                    'backgroundColor' => ['#3b82f6', '#ec4899'],
                ],
            ],
            'labels' => ['Laki-laki', 'Perempuan'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
