<?php

namespace App\Filament\Resources\Meetings\Widgets;

use Livewire\Attributes\On;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Group;
use App\Models\AgeGroup;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AgeGroupParticipationWidget extends ChartWidget
{
    public $filters = [];
    protected ?string $heading = 'Aktivitas Anggota Berdasarkan Usia';
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
        if ($total === 0) return 'Belum ada data partisipasi';
        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_act_age_' . ($user->id) . '_' . md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            if (!$filterMeetingId) return ['datasets' => [], 'labels' => []];

            $meeting = Meeting::find($filterMeetingId);
            if (!$meeting) return ['datasets' => [], 'labels' => []];

            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            $query = Attendance::where('meeting_id', $meeting->id)->where('attendances.status', \App\Enums\AttendanceStatus::HADIR);
            
            if ($filterGroupId) {
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds()));
            } elseif ($filterLevelId) {
                $query->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
            } elseif (!$user->isSuperAdmin() && $user->group_id) {
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            $data = $query->join('members', 'attendances.member_id', '=', 'members.id')
                ->join('age_groups', 'members.age_group_id', '=', 'age_groups.id')
                ->selectRaw('age_groups.name, count(*) as total')
                ->groupBy('age_groups.name')
                ->pluck('total', 'name');

            return [
                'datasets' => [['label' => 'Hadir', 'data' => $data->values()->toArray(), 'backgroundColor' => '#f59e0b']],
                'labels' => $data->keys()->toArray(),
            ];
        });
    }

    protected function getType(): string { return 'bar'; }
}
