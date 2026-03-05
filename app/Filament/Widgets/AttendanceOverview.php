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

        return Cache::remember($cacheKey, 120, function () use ($user) {
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

            // Filter by target audience (gender and age groups)
            if ($refMeeting) {
                $attendanceQuery->whereHas('member', function ($q) use ($refMeeting) {
                    if ($refMeeting->target_gender !== 'all') {
                        $q->where('gender', $refMeeting->target_gender);
                    }

                    $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => \App\Models\AgeGroup::count());
                    $selectedAgeGroupsCount = empty($refMeeting->target_age_groups) ? 0 : count($refMeeting->target_age_groups);
                    $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                    if ($shouldFilterByAge) {
                        $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', (array) $refMeeting->target_age_groups));
                    }
                });
            }

            $attendanceCount = (clone $attendanceQuery)->where('status', 'hadir')->count();
            $lateCount = (clone $attendanceQuery)->where('status', 'hadir')->where('notes', 'LIKE', '%TERLAMBAT%')->count();

            // Populasi anggota: filter berdasarkan target usia dari pertemuan terakhir
            $memberQuery = Member::where('status', true);
            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedGroupIds = $user->group->getAllDescendantIds();
                $memberQuery->whereIn('group_id', $allowedGroupIds);
            }

            // Filter population by target
            if ($refMeeting) {
                if ($refMeeting->target_gender !== 'all') {
                    $memberQuery->where('gender', $refMeeting->target_gender);
                }

                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => \App\Models\AgeGroup::count());
                $selectedAgeGroupsCount = empty($refMeeting->target_age_groups) ? 0 : count($refMeeting->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                if ($shouldFilterByAge) {
                    $memberQuery->whereHas('ageGroup', fn($q) => $q->whereIn('name', (array) $refMeeting->target_age_groups));
                }
            }

            $totalMembers = $memberQuery->count();
            $notAttendedCount = max(0, $totalMembers - $attendanceCount);

            $attendanceRate = $totalMembers > 0 ? ($attendanceCount / $totalMembers) * 100 : 0;

            $isExpired = $refMeeting ? $refMeeting->isExpired() : true;

            // Label Logic
            $presensiLabel = 'Hadir';
            
            // "Belum Hadir" during meeting time, "Tidak Hadir" if session expired/past
            $belumHadirLabel = ($isToday && !$isExpired) ? 'Belum Hadir' : 'Tidak Hadir';
            
            $terlambatLabel = 'Terlambat';
            $populasiLabel = 'Target Populasi';
            $rasioLabel = 'Rasio Kehadiran';

            $dateSuffix = $refMeeting && !$isToday ? ' (' . $refMeeting->meeting_date->format('d/m/Y') . ')' : '';

            $stats = [
                Stat::make($presensiLabel, $attendanceCount)
                    ->description('Total kehadiran')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
                Stat::make($belumHadirLabel, $notAttendedCount)
                    ->description(($isToday && !$isExpired) ? 'Estimasi yang akan datang' : 'Tidak hadir tanpa keterangan')
                    ->descriptionIcon('heroicon-m-user-minus')
                    ->color($notAttendedCount > 0 ? ($isExpired ? 'danger' : 'warning') : 'gray'),
                Stat::make($terlambatLabel, $lateCount)
                    ->description('Hadir setelah waktu mulai sesi')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($lateCount > 0 ? 'warning' : 'gray'),
                Stat::make($populasiLabel, $totalMembers)
                    ->description('Total target anggota aktif')
                    ->descriptionIcon('heroicon-m-users'),
                Stat::make($rasioLabel, number_format($attendanceRate, 1) . '%')
                    ->description('Persentase tingkat kehadiran')
                    ->descriptionIcon('heroicon-m-presentation-chart-line')
                    ->color($attendanceRate > 75 ? 'success' : ($attendanceRate > 50 ? 'warning' : 'danger')),
            ];

            // 6. Pengurus Hadir (Bukan Target / Total Hadir)
            if ($refMeeting) {
                $allAttendanceQuery = Attendance::where('meeting_id', $refMeeting->id);
                if (!$user->isSuperAdmin() && $user->group_id) {
                    $allAttendanceQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
                }

                $pengurusAttendanceQuery = (clone $allAttendanceQuery)->whereHas('member', function ($q) {
                    $q->whereIn('membership_type', ['pengurus', 'PENGURUS'])
                      ->orWhereHas('positions');
                });

                $totalPengurusHadir = $pengurusAttendanceQuery->count();
                
                $pengurusBukanTarget = 0;
                if (!empty($refMeeting->target_age_groups)) {
                    $pengurusBukanTarget = (clone $pengurusAttendanceQuery)
                        ->whereHas('member.ageGroup', fn($q) => $q->whereNotIn('name', (array) $refMeeting->target_age_groups))
                        ->count();
                }

                $stats[] = Stat::make('Pengurus Hadir', $pengurusBukanTarget > 0 ? "{$pengurusBukanTarget} / {$totalPengurusHadir}" : (string) $totalPengurusHadir)
                    ->description($pengurusBukanTarget > 0 ? 'Bukan target / Total hadir' : 'Total kehadiran pengurus')
                    ->descriptionIcon('heroicon-m-shield-check')
                    ->color('info');
            }

            return $stats;
        });
    }
}
