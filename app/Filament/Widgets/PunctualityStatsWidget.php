<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class PunctualityStatsWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 3;
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
        if (!$ref || $ref->meeting_date->isToday()) {
            return 'Kualitas Kedisiplinan';
        }
        return 'Kualitas Kedisiplinan (' . $ref->meeting_date->format('d/m/Y') . ')';
    }

    private function getPunctualityData(): array
    {
        $user = auth()->user();
        $ref = $this->getReferenceMeeting();
        $cacheKey = 'punctuality_stats_' . ($user->group_id ?? 'all') . '_' . ($ref->id ?? 'today');

        return Cache::remember($cacheKey, 300, function () use ($user, $ref) {
            $query = Attendance::query();
            if ($ref) {
                $query->where('meeting_id', $ref->id);
            } else {
                $query->whereDate('checkin_time', now());
            }

            if (!$user->isSuperAdmin() && $user->group_id) {
                $query->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds())->where('membership_type', '!=', 'pengurus'));
            } else {
                $query->whereHas('member', fn($q) => $q->where('membership_type', '!=', 'pengurus'));
            }

            $total = $query->count();
            if ($total === 0) {
                return [
                    'total' => 0,
                    'late' => 0,
                    'on_time' => 0,
                    'on_time_percent' => 0,
                    'late_percent' => 0,
                ];
            }

            $lateCount = (clone $query)->where('notes', 'LIKE', '%TERLAMBAT%')->count();
            $onTimeCount = $total - $lateCount;
            
            return [
                'total' => $total,
                'late' => $lateCount,
                'on_time' => $onTimeCount,
                'on_time_percent' => number_format(($onTimeCount / $total) * 100, 1),
                'late_percent' => number_format(($lateCount / $total) * 100, 1),
            ];
        });
    }

    public function getDescription(): ?string
    {
        $data = $this->getPunctualityData();
        if ($data['total'] === 0) {
            return 'Belum ada data kehadiran';
        }

        return "Total: {$data['total']} • Tepat Waktu: {$data['on_time']} ({$data['on_time_percent']}%) • Terlambat: {$data['late']} ({$data['late_percent']}%)";
    }

    protected function getData(): array
    {
        $data = $this->getPunctualityData();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Kehadiran',
                    'data' => [$data['on_time'], $data['late']],
                    'backgroundColor' => ['#10b981', '#ef4444'],
                ],
            ],
            'labels' => ['Tepat Waktu', 'Terlambat'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
