<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AttendanceOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 1;
    
    // Responsive column span: full on mobile, 3 cols on desktop
    protected int|string|array $columnSpan = 'full';
    
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $cacheKey = 'attendance_overview_' . ($user->group_id ?? 'all');

        return Cache::remember($cacheKey, 60, function () use ($user) {
            // Find reference meeting (today or latest past)
            $meetingQuery = Meeting::where('meeting_date', '<=', now()->toDateString());
            if (!$user->isSuperAdmin() && $user->group_id) {
                // Hanya tampilkan pertemuan milik sendiri atau parent (pusat)
                // Pertemuan milik cabang (children) tidak dijadikan referensi utama di dasbor
                $allowedMeetingGroupIds = array_merge(
                    [$user->group_id],
                    $user->group->getAllAncestorIds()
                );
                $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
            }
            $refMeeting = $meetingQuery->latest('meeting_date')->first();
            $isToday = $refMeeting && $refMeeting->meeting_date->isToday();

            // Attendance based on reference meeting
            $attendanceQuery = Attendance::query();
            if ($refMeeting) {
                $attendanceQuery->where('meeting_id', $refMeeting->id);
            } else {
                $attendanceQuery->whereDate('checkin_time', now());
            }

            if (!$user->isSuperAdmin() && $user->group_id) {
                $descendantIds = $user->group->getAllDescendantIds();
                $attendanceQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds));
            }

            $attendanceCount = $attendanceQuery->count();

            // Populasi anggota: selalu data terkini
            $memberQuery = Member::where('status', true);
            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
                $memberQuery->whereIn('group_id', $allowedGroupIds);
            }
            $totalMembers = $memberQuery->count();

            $attendanceRate = $totalMembers > 0 ? ($attendanceCount / $totalMembers) * 100 : 0;

            // Label: tampilkan tanggal hanya jika bukan hari ini
            $presensiLabel = $isToday
                ? 'Total Presensi Hari Ini'
                : 'Total Presensi (' . ($refMeeting ? $refMeeting->meeting_date->format('d/m/Y') : '-') . ')';
            $presensiDesc = $isToday
                ? 'Anggota yang telah melakukan presensi hari ini'
                : 'Data presensi pertemuan terakhir';

            $rasioLabel = $isToday
                ? 'Rasio Kehadiran'
                : 'Rasio Kehadiran (' . ($refMeeting ? $refMeeting->meeting_date->format('d/m/Y') : '-') . ')';

            return [
                Stat::make($presensiLabel, $attendanceCount)
                    ->description($presensiDesc)
                    ->descriptionIcon('heroicon-m-user-group')
                    ->color('success'),
                Stat::make('Populasi Anggota', $totalMembers)
                    ->description('Jumlah total anggota terdaftar aktif')
                    ->descriptionIcon('heroicon-m-users'),
                Stat::make($rasioLabel, number_format($attendanceRate, 1) . '%')
                    ->description('Persentase kehadiran terhadap populasi')
                    ->descriptionIcon('heroicon-m-presentation-chart-line')
                    ->color($attendanceRate > 70 ? 'success' : 'warning'),
            ];
        });
    }
}
