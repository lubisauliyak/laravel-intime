<?php

namespace App\Filament\Resources\Meetings\Widgets;

use Livewire\Attributes\On;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Group;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class PunctualityStatsWidget extends ChartWidget
{
    public $filters = [];
    protected ?string $heading = 'Distribusi Ketepatan Waktu';
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
        if (!empty($data['datasets'][0]['data'])) {
            $total = array_sum($data['datasets'][0]['data']);
        }
        if ($total === 0) return 'Belum ada data kehadiran';
        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_punc_stats_' . ($user->id) . '_' . md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            if (!$filterMeetingId) return ['datasets' => [], 'labels' => []];

            $query = Attendance::where('meeting_id', $filterMeetingId)->where('attendances.status', \App\Enums\AttendanceStatus::HADIR);
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if ($filterGroupId) $query->whereHas('member', fn($q) => $q->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds()));
            elseif ($filterLevelId) $query->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
            elseif (!$user->isSuperAdmin() && $user->group_id) $query->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));

            $onTime = (clone $query)->where('notes', 'NOT LIKE', '%TERLAMBAT%')->count();
            $late = (clone $query)->where('notes', 'LIKE', '%TERLAMBAT%')->count();

            return [
                'datasets' => [['label' => 'Anggota', 'data' => [$onTime, $late], 'backgroundColor' => ['#10b981', '#ef4444']]],
                'labels' => ['Tepat Waktu', 'Terlambat'],
            ];
        });
    }

    protected function getType(): string { return 'doughnut'; }
}
