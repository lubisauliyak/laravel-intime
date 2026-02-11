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
        $todayAttendance = Attendance::whereDate('checkin_time', now())->count();
        $totalMembers = Member::where('status', true)->count();
        
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
