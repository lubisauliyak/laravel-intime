<?php

namespace App\Filament\Resources\Meetings\Widgets;

use Livewire\Attributes\On;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Group;
use App\Models\AgeGroup;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class PunctualityScansWidget extends ChartWidget
{
    public $filters = [];
    protected ?string $heading = 'Ketepatan Waktu Berdasarkan Usia';
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
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => false,
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'stacked' => false,
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(255, 255, 255, 0.1)',
                        'drawBorder' => false,
                    ],
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        $total = 0;
        if (!empty($data['datasets'])) {
            foreach ($data['datasets'] as $dataset) {
                $total += array_sum($dataset['data']);
            }
        }
        if ($total === 0) return 'Belum ada data kedatangan';
        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_punc_scans_' . ($user->id) . '_' . md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            if (!$filterMeetingId) return ['datasets' => [], 'labels' => []];

            $query = Attendance::where('meeting_id', $filterMeetingId)->where('attendances.status', \App\Enums\AttendanceStatus::HADIR);
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if ($filterGroupId) $query->whereHas('member', fn($q) => $q->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds()));
            elseif ($filterLevelId) $query->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
            elseif (!$user->isSuperAdmin() && $user->group_id) $query->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));

            $ageGroups = AgeGroup::orderBy('id')->get();
            $onTimeData = [];
            $lateData = [];
            $labels = [];

            foreach ($ageGroups as $ag) {
                $labels[] = $ag->name;
                $onTimeData[] = (clone $query)->whereHas('member', fn($q) => $q->where('age_group_id', $ag->id))->where('notes', 'NOT LIKE', '%TERLAMBAT%')->count();
                $lateData[] = (clone $query)->whereHas('member', fn($q) => $q->where('age_group_id', $ag->id))->where('notes', 'LIKE', '%TERLAMBAT%')->count();
            }

            return [
                'datasets' => [
                    ['label' => 'Tepat Waktu', 'data' => $onTimeData, 'backgroundColor' => '#10b981'],
                    ['label' => 'Terlambat', 'data' => $lateData, 'backgroundColor' => '#ef4444'],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string { return 'bar'; }
}
