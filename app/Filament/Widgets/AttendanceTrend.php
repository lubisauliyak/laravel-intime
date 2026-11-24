<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class AttendanceTrend extends ChartWidget
{
    use InteractsWithPageFilters;

    public ?string $filter = 'month';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Statistik Tren Kehadiran';

    protected ?string $maxHeight = '300px';

    protected string $view = 'filament.widgets.attendance-trend-chart';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /**
     * Adaptive Polling:
     * Polling hanya aktif (30s) jika pertemuan hari ini sedang berlangsung.
     * Jika melihat arsip pertemuan lampau, polling otomatis nonaktif (null) untuk menghemat CPU shared hosting.
     */
    protected function getPollingInterval(): ?string
    {
        $refMeeting = $this->getMeetingRecord();
        if (! $refMeeting) {
            return null;
        }

        if ($refMeeting->meeting_date?->isToday() && ! $refMeeting->isExpired()) {
            return '30s';
        }

        return null;
    }

    protected function getMeetingRecord(): ?Meeting
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? $this->pageFilters['meeting_id'] ?? null;
        if (! $filterMeetingId) {
            return null;
        }

        return Meeting::find($filterMeetingId);
    }

    protected function getData(): array
    {
        $currentMeeting = $this->getMeetingRecord();
        if (! $currentMeeting || ! $currentMeeting->meeting_date) {
            return ['datasets' => [], 'labels' => []];
        }

        $user = auth()->user();
        $isToday = $currentMeeting->meeting_date && $currentMeeting->meeting_date->isToday();
        $isExpired = $currentMeeting->isExpired();
        $isActive = $isToday && ! $isExpired;

        $selectedFilter = $this->filter ?? 'month';

        // Cache TTL: 30 detik saat ada pertemuan aktif, 30 menit jika data lampau
        $cacheTtl = $isActive ? 30 : 1800;
        $cacheKey = 'attendance_trend_'.($user->group_id ?? 'all').'_'.$selectedFilter.'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, $cacheTtl, function () use ($currentMeeting, $user, $selectedFilter) {
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;
            $refDate = $currentMeeting->meeting_date;

            $meetingQuery = Meeting::with('group.level')
                ->where('meeting_date', '<=', $refDate);

            // Filter rentang waktu:
            if ($selectedFilter === 'month') {
                $meetingQuery->whereYear('meeting_date', $refDate->year)
                    ->whereMonth('meeting_date', $refDate->month);
            } elseif ($selectedFilter === 'year') {
                $meetingQuery->whereYear('meeting_date', $refDate->year);
            }

            // Hierarki cakupan pertemuan:
            // - Superadmin: melihat semua pertemuan (atau sesuai filter eksplisit)
            // - Admin dengan grup (Kelompok, Desa, Daerah): melihat grup sendiri + parent/ancestors, tanpa anak (children)
            if ($user && ! $user->isSuperAdmin() && $user->group_id && $user->group) {
                $allowedGroupIds = array_merge([$user->group_id], $user->group->getAllAncestorIds());
                $meetingQuery->whereIn('group_id', $allowedGroupIds);
            } elseif ($filterGroupId) {
                $meetingQuery->where('group_id', $filterGroupId);
            } elseif ($filterLevelId) {
                $meetingQuery->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
            }

            $limit = $selectedFilter === 'month' ? 10 : 15;

            $meetings = $meetingQuery->latest('meeting_date')
                ->latest('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();

            if ($meetings->isEmpty()) {
                return [
                    'datasets' => [],
                    'labels' => [],
                ];
            }

            $meetingIds = $meetings->pluck('id')->toArray();

            // Scoping peserta yang dihitung:
            // Jika admin kelompok/desa melihat pertemuan daerah, hitung hanya peserta dari kelompok/desa mereka
            $descendantIds = null;
            if ($filterGroupId) {
                $descendantIds = Group::find($filterGroupId)?->getAllDescendantIds() ?? [];
            } elseif ($user && ! $user->isSuperAdmin() && $user->group_id && $user->group) {
                $descendantIds = $user->group->getAllDescendantIds();
            }

            $attendanceQuery = Attendance::whereIn('meeting_id', $meetingIds)
                ->where('status', AttendanceStatus::HADIR);

            if ($descendantIds !== null) {
                $attendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            } elseif ($filterLevelId) {
                $attendanceQuery->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            }

            // Single query agregasi terindeks (bebas N+1)
            $attendanceCounts = $attendanceQuery
                ->selectRaw('meeting_id, count(*) as total')
                ->groupBy('meeting_id')
                ->pluck('total', 'meeting_id')
                ->toArray();

            $labels = [];
            $values = [];

            foreach ($meetings as $meeting) {
                // Gunakan kode singkat tingkat grup: DRH, DES, KEL, KAB
                $levelCode = $meeting->group?->level?->code;
                $dateFormatted = $meeting->meeting_date->format('d/m');
                $labels[] = $levelCode ? (strtoupper($levelCode).' '.$dateFormatted) : $dateFormatted;
                $values[] = (int) ($attendanceCounts[$meeting->id] ?? 0);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Kehadiran',
                        'data' => $values,
                        'borderColor' => '#22c55e',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                        'fill' => true,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 3,
                        'pointBackgroundColor' => '#22c55e',
                        'pointBorderColor' => '#ffffff',
                        'pointBorderWidth' => 1.5,
                        'pointHoverRadius' => 5,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'aspectRatio' => 1.5,
            'plugins' => [
                'legend' => [
                    'display' => false,
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
        ];
    }
}
