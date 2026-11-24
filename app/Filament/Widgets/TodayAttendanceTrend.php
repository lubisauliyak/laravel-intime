<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TodayAttendanceTrend extends AttendanceTrend
{
    protected static bool $isLazy = true;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 5,
    ];

    protected ?string $heading = 'Tren Partisipasi Pertemuan';

    protected ?string $maxHeight = '220px';

    public ?string $filter = 'all';

    protected ?Meeting $cachedTodayMeeting = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Meeting::class) ?? true;
    }

    protected function getMeetingRecord(): ?Meeting
    {
        if ($this->cachedTodayMeeting === null) {
            $user = auth()->user();
            $query = Meeting::query();

            if ($user && ! $user->isSuperAdmin()) {
                if ($user->group_id && $user->group) {
                    $descendantIds = $user->group->getAllDescendantIds();
                    $ancestorIds = $user->group->getAllAncestorIds();
                    $allowedGroupIds = array_unique(array_merge($descendantIds, $ancestorIds));
                    $query->whereIn('group_id', $allowedGroupIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $this->cachedTodayMeeting = (clone $query)
                ->whereDate('meeting_date', Carbon::today())
                ->latest('meeting_date')
                ->first()
                ?? (clone $query)->latest('meeting_date')->latest('id')->first();
        }

        return $this->cachedTodayMeeting;
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

        $selectedFilter = $this->filter ?? 'all';

        // Cache TTL: 30s jika aktif, 10 menit jika arsip lampau
        $cacheTtl = $isActive ? 30 : 600;
        $cacheKey = 'dashboard_attendance_trend_'.($user->group_id ?? 'all').'_'.$selectedFilter.'_'.$currentMeeting->id;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($currentMeeting, $user, $selectedFilter): array {
            $refDate = $currentMeeting->meeting_date;

            $meetingQuery = Meeting::with('group.level')
                ->where('meeting_date', '<=', $refDate);

            if ($selectedFilter === 'month') {
                $meetingQuery->whereYear('meeting_date', $refDate->year)
                    ->whereMonth('meeting_date', $refDate->month);
            } elseif ($selectedFilter === 'year') {
                $meetingQuery->whereYear('meeting_date', $refDate->year);
            }

            if ($user && ! $user->isSuperAdmin() && $user->group_id && $user->group) {
                $allowedGroupIds = array_merge([$user->group_id], $user->group->getAllAncestorIds());
                $meetingQuery->whereIn('group_id', $allowedGroupIds);
            }

            // Batasi 7 pertemuan agar tampilan grafik ramping di layar desktop
            $meetings = $meetingQuery->latest('meeting_date')
                ->latest('id')
                ->limit(7)
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

            $descendantIds = null;
            if ($user && ! $user->isSuperAdmin() && $user->group_id && $user->group) {
                $descendantIds = $user->group->getAllDescendantIds();
            }

            $attendanceQuery = Attendance::whereIn('meeting_id', $meetingIds)
                ->where('status', AttendanceStatus::HADIR);

            if ($descendantIds !== null) {
                $attendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            }

            $attendanceCounts = $attendanceQuery
                ->selectRaw('meeting_id, count(*) as total')
                ->groupBy('meeting_id')
                ->pluck('total', 'meeting_id')
                ->toArray();

            $labels = [];
            $values = [];

            foreach ($meetings as $meeting) {
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
}
