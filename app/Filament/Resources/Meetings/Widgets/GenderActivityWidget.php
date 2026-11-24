<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class GenderActivityWidget extends ChartWidget
{
    public $filters = [];

    protected ?string $heading = 'Aktivitas Anggota Berdasarkan Gender';

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
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(255, 255, 255, 0.1)',
                        'drawBorder' => false,
                    ],
                ],
            ],
            'barPercentage' => 0.7,
            'categoryPercentage' => 0.8,
        ];
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        if (empty($data['datasets'])) {
            return 'Belum ada data aktivitas';
        }
        $hadir = array_sum($data['datasets'][0]['data']);
        $izinSakit = array_sum($data['datasets'][1]['data']);
        $tidakHadir = array_sum($data['datasets'][2]['data']);
        $total = $hadir + $izinSakit + $tidakHadir;
        if ($total === 0) {
            return 'Belum ada data partisipasi';
        }

        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_act_gen_'.($user->id).'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            if (! $filterMeetingId) {
                return ['datasets' => [], 'labels' => []];
            }

            $query = Attendance::where('meeting_id', $filterMeetingId)->where('attendances.status', \App\Enums\AttendanceStatus::HADIR);
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if ($filterGroupId) {
                $query->whereHas('member', fn ($q) => $q->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds()));
            } elseif ($filterLevelId) {
                $query->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $query->whereHas('member', fn ($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            $data = $query->join('members', 'attendances.member_id', '=', 'members.id')
                ->selectRaw('members.gender, count(*) as total')
                ->groupBy('members.gender')
                ->pluck('total', 'gender');

            $labels = $data->keys()->map(fn ($g) => $g === 'male' ? 'LAKI-LAKI' : 'PEREMPUAN')->toArray();

            return [
                'datasets' => [['label' => 'Hadir', 'data' => $data->values()->toArray(), 'backgroundColor' => ['#3b82f6', '#ec4899']]],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
