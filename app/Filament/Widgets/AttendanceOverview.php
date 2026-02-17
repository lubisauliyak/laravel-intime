<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttendanceOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $memberQuery = Member::where('status', true);
        $attendanceQuery = Attendance::whereDate('checkin_time', now());

        if (!$user->isSuperAdmin() && $user->group_id) {
            $group = $user->group;
            $descendantIds = $group->getAllDescendantIds();
            $childrenIds = array_diff($descendantIds, [$group->id]);

            if (!empty($childrenIds)) {
                // Has children, show stats for children only
                $memberQuery->whereIn('group_id', $childrenIds);
                $attendanceQuery->whereHas('member', fn($query) => $query->whereIn('group_id', $childrenIds));
            } else {
                // Bottom level, show stats for own group
                $memberQuery->where('group_id', $group->id);
                $attendanceQuery->whereHas('member', fn($query) => $query->where('group_id', $group->id));
            }
        }

        $todayAttendance = $attendanceQuery->count();
        $totalMembers = $memberQuery->count();
        
        // Simple average for active meetings
        $attendanceRate = $totalMembers > 0 ? ($todayAttendance / $totalMembers) * 100 : 0;

        return [
            Stat::make('Kehadiran Hari Ini', $todayAttendance)
                ->description('Jumlah anggota yang hadir hari ini')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
            Stat::make('Anggota Aktif', $totalMembers)
                ->description('Total anggota terdaftar aktif')
                ->descriptionIcon('heroicon-m-users'),
            Stat::make('Persentase Kehadiran', number_format($attendanceRate, 1) . '%')
                ->description('Rasio kehadiran hari ini')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color($attendanceRate > 70 ? 'success' : 'warning'),
        ];
    }
}
