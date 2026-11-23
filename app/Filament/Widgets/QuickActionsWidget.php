<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Member;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuickActionsWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';
    protected ?string $heading = 'Aksi Cepat';

    protected function getStats(): array
    {
        $user = auth()->user();

        // Base Query untuk Pertemuan (dengan group scope user)
        $meetingBaseQuery = Meeting::query();

        if ($user && !$user->isSuperAdmin()) {
            if ($user->group_id && $user->group) {
                $descendantIds = $user->group->getAllDescendantIds();
                $ancestorIds = $user->group->getAllAncestorIds();
                $allowedGroupIds = array_unique(array_merge($descendantIds, $ancestorIds));
                $meetingBaseQuery->whereIn('group_id', $allowedGroupIds);
            } else {
                $meetingBaseQuery->whereRaw('1 = 0');
            }
        }

        $driver = DB::getDriverName();
        $rawDate = $driver === 'sqlite' ? "strftime('%Y-%m-%d', meeting_date)" : "DATE(meeting_date)";

        // 1. Pertemuan Baru - Total bulan ini & tren 7 hari terakhir
        $thisMonthMeetingsCount = (clone $meetingBaseQuery)
            ->whereYear('meeting_date', now()->year)
            ->whereMonth('meeting_date', now()->month)
            ->count();

        $meetingCounts = (clone $meetingBaseQuery)
            ->whereDate('meeting_date', '>=', now()->subDays(6)->toDateString())
            ->whereDate('meeting_date', '<=', now()->toDateString())
            ->selectRaw("{$rawDate} as m_date, count(*) as total")
            ->groupBy('m_date')
            ->pluck('total', 'm_date')
            ->toArray();

        $meetingChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateKey = now()->subDays($i)->toDateString();
            $meetingChart[] = (int) ($meetingCounts[$dateKey] ?? 0);
        }

        // 2. Pertemuan Hari Ini (Scanner QR)
        $todayMeetingsCount = (clone $meetingBaseQuery)
            ->whereDate('meeting_date', now()->toDateString())
            ->count();

        $todayMeeting = (clone $meetingBaseQuery)
            ->with('group')
            ->select(['id', 'uid', 'name', 'start_time', 'group_id'])
            ->whereDate('meeting_date', now()->toDateString())
            ->latest('start_time')
            ->first();

        $scannerValue = (string) $todayMeetingsCount;

        $scannerDesc = $todayMeeting
            ? ('Buka sesi ' . ($todayMeeting->group?->name ?? $todayMeeting->name))
            : 'Tidak ada sesi';

        // 3. Rekap Laporan - Total seluruh presensi yang terekam & tren 7 hari
        $attendanceBaseQuery = Attendance::query();
        if ($user && !$user->isSuperAdmin()) {
            $attendanceBaseQuery->whereIn('meeting_id', (clone $meetingBaseQuery)->select('id'));
        }

        $totalAttendancesCount = (clone $attendanceBaseQuery)->count();

        $rawAttendanceDate = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', coalesce(checkin_time, created_at))"
            : "DATE(coalesce(checkin_time, created_at))";

        $attendanceCounts = (clone $attendanceBaseQuery)
            ->whereDate('checkin_time', '>=', now()->subDays(6)->toDateString())
            ->whereDate('checkin_time', '<=', now()->toDateString())
            ->selectRaw("{$rawAttendanceDate} as a_date, count(*) as total")
            ->groupBy('a_date')
            ->pluck('total', 'a_date')
            ->toArray();

        $attendanceChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateKey = now()->subDays($i)->toDateString();
            $attendanceChart[] = (int) ($attendanceCounts[$dateKey] ?? 0);
        }

        $reportUrl = $todayMeeting
            ? url("/admin/meetings/{$todayMeeting->uid}")
            : url("/admin/meetings");

        // 4. Daftar Anggota - Total anggota aktif & jumlah pendaftaran bulan ini
        $memberBaseQuery = Member::query();

        if ($user && !$user->isSuperAdmin()) {
            if ($user->group_id && $user->group) {
                $memberBaseQuery->whereIn('group_id', $user->getManageableGroupIds());
            } else {
                $memberBaseQuery->whereRaw('1 = 0');
            }
        }

        $activeMembersCount = (clone $memberBaseQuery)->where('status', true)->count();
        $newMembersThisMonth = (clone $memberBaseQuery)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $newMembersFormatted = number_format($newMembersThisMonth, 0, ',', '.');
        $memberDesc = "Lihat +{$newMembersFormatted} anggota baru";

        $rawMonth = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $sevenMonthsAgo = now()->subMonths(6)->startOfMonth();

        $memberMonthlyCounts = (clone $memberBaseQuery)
            ->where('created_at', '>=', $sevenMonthsAgo)
            ->selectRaw("{$rawMonth} as ym, count(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->toArray();

        $memberChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $ymKey = now()->subMonths($i)->format('Y-m');
            $memberChart[] = (int) ($memberMonthlyCounts[$ymKey] ?? 0);
        }

        return [
            Stat::make('Pertemuan', number_format($thisMonthMeetingsCount, 0, ',', '.'))
                ->description('Buat pertemuan baru')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->chart($meetingChart)
                ->color('primary')
                ->url(url('/admin/meetings/create')),

            Stat::make('Scanner QR', $scannerValue)
                ->description($scannerDesc)
                ->descriptionIcon('heroicon-m-qr-code')
                ->color($todayMeeting ? 'success' : 'gray')
                ->url($todayMeeting ? route('scanner.live', $todayMeeting) : null),

            Stat::make('Rekap Laporan', number_format($totalAttendancesCount, 0, ',', '.'))
                ->description('Lihat total presensi')
                ->descriptionIcon('heroicon-m-document-text')
                ->chart($attendanceChart)
                ->color('warning')
                ->url($reportUrl),

            Stat::make('Daftar Anggota', number_format($activeMembersCount, 0, ',', '.'))
                ->description($memberDesc)
                ->descriptionIcon('heroicon-m-identification')
                ->chart($memberChart)
                ->color('info')
                ->url(url('/admin/members')),
        ];
    }
}
