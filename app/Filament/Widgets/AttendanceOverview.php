<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Enums\AttendanceStatus;
use App\Models\Member;
use App\Models\Meeting;
use App\Models\Group;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class AttendanceOverview extends StatsOverviewWidget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    /**
     * Adaptive Polling:
     * Polling hanya aktif (15s) jika pertemuan hari ini dan belum expired (sedang berlangsung).
     * Jika pertemuan sudah selesai atau arsip lampau, polling nonaktif (null) untuk menghemat CPU shared hosting.
     */
    protected function getPollingInterval(): ?string
    {
        $refMeeting = $this->getMeetingRecord();
        if (!$refMeeting) {
            return null;
        }

        if ($refMeeting->meeting_date?->isToday() && !$refMeeting->isExpired()) {
            return '15s';
        }

        return null;
    }

    protected function getMeetingRecord(): ?Meeting
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? $this->pageFilters['meeting_id'] ?? null;
        if (!$filterMeetingId) {
            return null;
        }

        return Meeting::find($filterMeetingId);
    }

    public function getHeading(): string
    {
        $heading = 'Ringkasan Kehadiran';
        $meeting = $this->getMeetingRecord();
        if ($meeting) {
            $heading .= ' - ' . $meeting->name;
            if ($meeting->meeting_date?->isToday() && !$meeting->isExpired()) {
                $heading .= ' (Live)';
            }
        }

        return $heading;
    }

    protected function getStats(): array
    {
        $refMeeting = $this->getMeetingRecord();

        if (!$refMeeting) {
            return [
                Stat::make('Belum Ada Data', '—')
                    ->description('Silakan pilih pertemuan pada filter di atas')
                    ->descriptionIcon('heroicon-m-hand-raised')
                    ->color('gray'),
            ];
        }

        $user = auth()->user();
        $isToday = $refMeeting->meeting_date && $refMeeting->meeting_date->isToday();
        $isExpired = $refMeeting->isExpired();
        $isActive = $isToday && !$isExpired;

        // Multi-tier cache TTL: 15 detik jika aktif berjalan (sinkron dengan polling), 1 jam jika sesi selesai/lampau
        $cacheTtl = $isActive ? 15 : 3600;
        $cacheKey = 'attendance_overview_' . ($user->group_id ?? 'all') . '_' . md5(json_encode($this->filters));

        return Cache::remember($cacheKey, $cacheTtl, function () use ($user, $refMeeting, $isToday, $isExpired, $isActive) {
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            // Resolve descendant IDs
            $descendantIds = null;
            if ($filterGroupId) {
                $descendantIds = Group::find($filterGroupId)?->getAllDescendantIds() ?? [];
            } elseif (!$user->isSuperAdmin() && $user->group_id) {
                $descendantIds = $user->group->getAllDescendantIds();
            }

            // 1. Populasi dasar target (cache 5 menit agar tidak membebani DB berulang kali tiap poll)
            $popCacheKey = 'attendance_base_pop_' . ($refMeeting->id) . '_' . ($filterGroupId ?? '0') . '_' . ($filterLevelId ?? '0') . '_' . ($user->group_id ?? 'all');
            $totalBaseMembers = Cache::remember($popCacheKey, 300, function () use ($user, $descendantIds, $filterLevelId, $refMeeting) {
                $memberQuery = Member::where('status', true);

                if ($descendantIds !== null) {
                    $memberQuery->whereIn('group_id', $descendantIds);
                } elseif ($filterLevelId) {
                    $memberQuery->whereHas('group', fn($q) => $q->where('level_id', $filterLevelId));
                }

                if ($refMeeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                    $memberQuery->where('gender', $refMeeting->target_gender->value);
                }

                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => \App\Models\AgeGroup::count());
                $selectedAgeGroupsCount = empty($refMeeting->target_age_groups) ? 0 : count($refMeeting->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                if ($shouldFilterByAge) {
                    $memberQuery->whereHas('ageGroup', fn($q) => $q->whereIn('name', (array) $refMeeting->target_age_groups));
                }

                return $memberQuery->count();
            });

            // 2. Query Presensi Pertemuan Saat Ini
            $attendanceQuery = Attendance::query()->where('meeting_id', $refMeeting->id);

            if ($descendantIds !== null) {
                $attendanceQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds));
            } elseif ($filterLevelId) {
                $attendanceQuery->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
            }

            // Filter target audience
            $attendanceQuery->whereHas('member', function ($q) use ($refMeeting) {
                if ($refMeeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                    $q->where('gender', $refMeeting->target_gender->value);
                }

                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => \App\Models\AgeGroup::count());
                $selectedAgeGroupsCount = empty($refMeeting->target_age_groups) ? 0 : count($refMeeting->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                if ($shouldFilterByAge) {
                    $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', (array) $refMeeting->target_age_groups));
                }
            });

            $attendanceCount = (clone $attendanceQuery)->where('status', AttendanceStatus::HADIR)->count();
            $lateCount = (clone $attendanceQuery)->where('status', AttendanceStatus::HADIR)->where('notes', 'LIKE', '%TERLAMBAT%')->count();

            // Non-target yang hadir atau izin/sakit
            $nonTargetAttendedCount = (clone $attendanceQuery)
                ->whereHas('member', fn($m) => $m->where('status', false))
                ->whereIn('status', [AttendanceStatus::HADIR, AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
                ->count();

            $totalDynamicTarget = $totalBaseMembers + $nonTargetAttendedCount;

            // Target dasar yang hadir atau izin/sakit
            $targetAttendedCount = (clone $attendanceQuery)
                ->whereHas('member', fn($m) => $m->where('status', true))
                ->whereIn('status', [AttendanceStatus::HADIR, AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
                ->count();

            $notAttendedCount = max(0, $totalBaseMembers - $targetAttendedCount);
            $attendanceRate = $totalDynamicTarget > 0 ? ($attendanceCount / $totalDynamicTarget) * 100 : 0;

            // 3. Pengurus Hadir
            $allAttendanceQuery = Attendance::where('meeting_id', $refMeeting->id);
            if ($descendantIds !== null) {
                $allAttendanceQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds));
            } elseif ($filterLevelId) {
                $allAttendanceQuery->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
            }

            $pengurusAttendanceQuery = (clone $allAttendanceQuery)->whereHas('member', function ($q) {
                $q->where('membership_type', \App\Enums\MembershipType::PENGURUS->value)
                    ->orWhereHas('positions');
            });

            $totalPengurusHadir = $pengurusAttendanceQuery->count();

            $pengurusBukanTarget = 0;
            if (!empty($refMeeting->target_age_groups)) {
                $pengurusBukanTarget = (clone $pengurusAttendanceQuery)
                    ->whereHas('member.ageGroup', fn($q) => $q->whereNotIn('name', (array) $refMeeting->target_age_groups))
                    ->count();
            }

            // 4. Sparkline Trends (Histori 6 pertemuan lampau + 1 pertemuan saat ini)
            $trends = $this->getSparklineTrends($refMeeting, [
                'attendanceCount' => $attendanceCount,
                'notAttendedCount' => $notAttendedCount,
                'lateCount' => $lateCount,
                'totalDynamicTarget' => $totalDynamicTarget,
                'attendanceRate' => round($attendanceRate, 1),
                'pengurusCount' => $totalPengurusHadir,
            ], $descendantIds, $filterLevelId, $user);

            // Label Logic
            $presensiLabel = AttendanceStatus::HADIR->getLabel();
            $belumHadirLabel = ($isToday && !$isExpired) ? 'Belum Hadir' : 'Tidak Hadir';
            $terlambatLabel = 'Terlambat';
            $populasiLabel = 'Target Populasi';
            $rasioLabel = 'Rasio Kehadiran';

            $liveSuffix = $isActive ? ' • Live' : '';

            return [
                Stat::make($presensiLabel, number_format($attendanceCount, 0, ',', '.'))
                    ->description('Total kehadiran')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->chart($trends['hadir'])
                    ->color('success'),

                Stat::make($belumHadirLabel, number_format($notAttendedCount, 0, ',', '.'))
                    ->description(($isToday && !$isExpired) ? 'Estimasi yang akan datang' : 'Tidak hadir tanpa keterangan')
                    ->descriptionIcon('heroicon-m-user-minus')
                    ->chart($trends['tidak_hadir'])
                    ->color($notAttendedCount > 0 ? ($isExpired ? 'danger' : 'warning') : 'gray'),

                Stat::make($terlambatLabel, number_format($lateCount, 0, ',', '.'))
                    ->description('Hadir setelah waktu mulai sesi')
                    ->descriptionIcon('heroicon-m-clock')
                    ->chart($trends['terlambat'])
                    ->color($lateCount > 0 ? 'warning' : 'gray'),

                Stat::make($populasiLabel, number_format($totalDynamicTarget, 0, ',', '.'))
                    ->description('Target dinamis pertemuan')
                    ->descriptionIcon('heroicon-m-users')
                    ->chart($trends['populasi'])
                    ->color('primary'),

                Stat::make($rasioLabel, number_format($attendanceRate, 1) . '%')
                    ->description('Persentase tingkat kehadiran')
                    ->descriptionIcon('heroicon-m-presentation-chart-line')
                    ->chart($trends['rasio'])
                    ->color($attendanceRate > 75 ? 'success' : ($attendanceRate > 50 ? 'warning' : 'danger')),

                Stat::make('Pengurus Hadir', $pengurusBukanTarget > 0 ? "{$pengurusBukanTarget} / {$totalPengurusHadir}" : (string) $totalPengurusHadir)
                    ->description($pengurusBukanTarget > 0 ? 'Bukan target / Total hadir' : 'Total kehadiran pengurus')
                    ->descriptionIcon('heroicon-m-shield-check')
                    ->chart($trends['pengurus'])
                    ->color('info'),
            ];
        });
    }

    /**
     * Menghitung data sparkline dari 6 pertemuan lampau + 1 pertemuan saat ini.
     * Menggunakan query tunggal GROUP BY meeting_id dengan cache 30 menit untuk data masa lalu,
     * sehingga polling 15s pertemuan aktif tidak membebani MySQL di shared hosting.
     */
    protected function getSparklineTrends(
        Meeting $refMeeting,
        array $currentValues,
        ?array $descendantIds,
        ?int $filterLevelId,
        $user
    ): array {
        $groupId = $refMeeting->group_id;

        // Ambil maksimal 6 pertemuan sebelumnya pada grup yang sama secara kronologis
        $pastMeetings = Meeting::where('group_id', $groupId)
            ->where('id', '!=', $refMeeting->id)
            ->where(function ($q) use ($refMeeting) {
                $q->where('meeting_date', '<', $refMeeting->meeting_date)
                    ->orWhere(function ($sq) use ($refMeeting) {
                        $sq->where('meeting_date', '=', $refMeeting->meeting_date)
                            ->where('id', '<', $refMeeting->id);
                    });
            })
            ->orderBy('meeting_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get(['id', 'meeting_date'])
            ->reverse()
            ->values();

        $pastMeetingIds = $pastMeetings->pluck('id')->toArray();

        // Jika belum ada pertemuan lampau (pertemuan pertama kali)
        if (empty($pastMeetingIds)) {
            return [
                'hadir' => [$currentValues['attendanceCount'], $currentValues['attendanceCount']],
                'tidak_hadir' => [$currentValues['notAttendedCount'], $currentValues['notAttendedCount']],
                'terlambat' => [$currentValues['lateCount'], $currentValues['lateCount']],
                'populasi' => [$currentValues['totalDynamicTarget'], $currentValues['totalDynamicTarget']],
                'rasio' => [$currentValues['attendanceRate'], $currentValues['attendanceRate']],
                'pengurus' => [$currentValues['pengurusCount'], $currentValues['pengurusCount']],
            ];
        }

        // Cache data historis 30 menit (pertemuan lampau bersifat statis)
        $historyCacheKey = 'sparkline_hist_' . $groupId . '_' . implode('-', $pastMeetingIds) . '_' . ($descendantIds ? implode(',', $descendantIds) : ($filterLevelId ?? 'all'));

        $historyData = Cache::remember($historyCacheKey, 1800, function () use ($pastMeetingIds, $descendantIds, $filterLevelId, $user) {
            $baseQuery = Attendance::whereIn('meeting_id', $pastMeetingIds);

            if ($descendantIds !== null) {
                $baseQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds));
            } elseif ($filterLevelId) {
                $baseQuery->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
            } elseif (!$user->isSuperAdmin() && $user->group_id) {
                $baseQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            // 1 query agregasi cepat untuk hadir dan terlambat
            $counts = (clone $baseQuery)
                ->selectRaw("
                    meeting_id,
                    COUNT(CASE WHEN status = 'hadir' THEN 1 END) as hadir_count,
                    COUNT(CASE WHEN status = 'hadir' AND notes LIKE '%TERLAMBAT%' THEN 1 END) as late_count
                ")
                ->groupBy('meeting_id')
                ->get()
                ->keyBy('meeting_id');

            // 1 query untuk pengurus hadir
            $pengurusCounts = (clone $baseQuery)
                ->where('status', AttendanceStatus::HADIR)
                ->whereHas('member', function ($q) {
                    $q->where('membership_type', \App\Enums\MembershipType::PENGURUS->value)
                        ->orWhereHas('positions');
                })
                ->selectRaw('meeting_id, count(*) as total')
                ->groupBy('meeting_id')
                ->pluck('total', 'meeting_id')
                ->toArray();

            return [
                'counts' => $counts->toArray(),
                'pengurus' => $pengurusCounts,
            ];
        });

        $hadirTrend = [];
        $tidakHadirTrend = [];
        $terlambatTrend = [];
        $populasiTrend = [];
        $rasioTrend = [];
        $pengurusTrend = [];

        $approxPopulasi = max(1, $currentValues['totalDynamicTarget']);

        foreach ($pastMeetingIds as $mId) {
            $meetingRow = $historyData['counts'][$mId] ?? null;
            $mHadir = (int) ($meetingRow['hadir_count'] ?? 0);
            $mLate = (int) ($meetingRow['late_count'] ?? 0);
            $mPengurus = (int) ($historyData['pengurus'][$mId] ?? 0);
            $mTidakHadir = max(0, $approxPopulasi - $mHadir);
            $mRasio = round(($mHadir / $approxPopulasi) * 100, 1);

            $hadirTrend[] = $mHadir;
            $tidakHadirTrend[] = $mTidakHadir;
            $terlambatTrend[] = $mLate;
            $populasiTrend[] = $approxPopulasi;
            $rasioTrend[] = $mRasio;
            $pengurusTrend[] = $mPengurus;
        }

        // Tambahkan titik ke-7: nilai real-time sesi saat ini
        $hadirTrend[] = $currentValues['attendanceCount'];
        $tidakHadirTrend[] = $currentValues['notAttendedCount'];
        $terlambatTrend[] = $currentValues['lateCount'];
        $populasiTrend[] = $currentValues['totalDynamicTarget'];
        $rasioTrend[] = $currentValues['attendanceRate'];
        $pengurusTrend[] = $currentValues['pengurusCount'];

        return [
            'hadir' => $hadirTrend,
            'tidak_hadir' => $tidakHadirTrend,
            'terlambat' => $terlambatTrend,
            'populasi' => $populasiTrend,
            'rasio' => $rasioTrend,
            'pengurus' => $pengurusTrend,
        ];
    }
}
