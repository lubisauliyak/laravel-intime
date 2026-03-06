<?php

namespace App\Filament\Widgets;

use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuickActionsWidget extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';
    protected ?string $heading = 'Aksi Cepat';

    protected function getStats(): array
    {
        $user = auth()->user();

        // Mencari pertemuan hari ini
        $meetingQuery = Meeting::whereDate('meeting_date', now()->toDateString());

        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
        }

        $todayMeeting = $meetingQuery->latest()->first();

        $reportUrl = $todayMeeting 
            ? url("/admin/meetings/{$todayMeeting->id}") 
            : url("/admin/meetings");

        $scannerDesc = $todayMeeting
            ? 'Buka ' . $todayMeeting->name
            : 'Tidak ada sesi hari ini';

        return [
            Stat::make('Pertemuan Baru', '➕')
                ->description('Buat sekarang')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('primary')
                ->url(url('/admin/meetings/create')),
            Stat::make('Scanner QR', '📷')
                ->description($scannerDesc)
                ->descriptionIcon('heroicon-m-qr-code')
                ->color($todayMeeting ? 'success' : 'gray')
                ->url($todayMeeting ? route('scanner.live', $todayMeeting) : null),
            Stat::make('Rekap Laporan', '📥')
                ->description('Lihat data presensi')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning')
                ->url($reportUrl),
            Stat::make('Daftar Anggota', '🪪')
                ->description('Lihat daftar anggota')
                ->descriptionIcon('heroicon-m-identification')
                ->color('info')
                ->url(url('/admin/members')),
        ];
    }
}
