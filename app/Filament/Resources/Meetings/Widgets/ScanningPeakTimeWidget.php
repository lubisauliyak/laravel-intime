<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ScanningPeakTimeWidget extends ChartWidget
{
    public $filters = [];

    protected ?string $heading = 'Beban Aktivitas Scanner';

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    protected ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'aspectRatio' => 1.5,
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
                'x' => ['grid' => ['display' => false]],
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
        ];
    }

    public function getDescription(): ?string
    {
        $data = $this->getData();
        $total = 0;
        if (! empty($data['datasets'])) {
            foreach ($data['datasets'] as $dataset) {
                $total += array_sum($dataset['data']);
            }
        }
        if ($total === 0) {
            return 'Belum ada aktivitas masuk';
        }

        return "Total : {$total} Anggota";
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_peak_time_'.($user->id).'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            if (! $filterMeetingId) {
                return ['datasets' => [], 'labels' => []];
            }

            $query = Attendance::where('meeting_id', $filterMeetingId);
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if ($filterGroupId) {
                $query->whereHas('member', fn ($q) => $q->whereIn('group_id', Group::find($filterGroupId)->getAllDescendantIds()));
            } elseif ($filterLevelId) {
                $query->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $query->whereHas('member', fn ($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            $data = $query->selectRaw('HOUR(checkin_time) as hour, count(*) as total')
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('total', 'hour');

            $labels = $data->keys()->map(fn ($h) => sprintf('%02d:00', $h))->toArray();

            return [
                'datasets' => [['label' => 'Pindaian', 'data' => $data->values()->toArray(), 'backgroundColor' => '#6366f1', 'borderColor' => '#6366f1', 'fill' => 'start']],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }
}
