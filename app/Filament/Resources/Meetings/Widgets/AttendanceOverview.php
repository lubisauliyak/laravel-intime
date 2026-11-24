<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class AttendanceOverview extends StatsOverviewWidget
{
    public $filters = [];

    protected static bool $isLazy = true;

    protected ?string $heading = 'Ringkasan Kehadiran';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_attendance_overview_'.($user->id).'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 30, function () use ($user) {
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            $filterGroupId = $this->filters['group_id'] ?? null;
            $filterLevelId = $this->filters['level_id'] ?? null;

            if (! $filterMeetingId) {
                return [];
            }

            $refMeeting = Meeting::find($filterMeetingId);
            if (! $refMeeting) {
                return [];
            }

            $isToday = $refMeeting->meeting_date->isToday();

            // Attendance based on reference meeting
            $attendanceQuery = Attendance::query()->where('meeting_id', $refMeeting->id);

            if ($filterGroupId) {
                $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
                $attendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            } elseif ($filterLevelId) {
                $attendanceQuery->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $descendantIds = $user->group->getAllDescendantIds();
                $attendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            }

            // Filter by target audience
            $attendanceQuery->whereHas('member', function ($q) use ($refMeeting) {
                if ($refMeeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                    $q->where('gender', $refMeeting->target_gender->value);
                }
                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => \App\Models\AgeGroup::count());
                $selectedAgeGroupsCount = empty($refMeeting->target_age_groups) ? 0 : count($refMeeting->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
                if ($shouldFilterByAge) {
                    $q->whereHas('ageGroup', fn ($aq) => $aq->whereIn('name', (array) $refMeeting->target_age_groups));
                }
            });

            $attendanceCount = (clone $attendanceQuery)->where('attendances.status', AttendanceStatus::HADIR)->count();
            $lateCount = (clone $attendanceQuery)->where('attendances.status', AttendanceStatus::HADIR)->where('notes', 'LIKE', '%TERLAMBAT%')->count();

            // Populasi anggota
            $memberQuery = Member::where('members.status', true);
            if ($filterGroupId) {
                $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
                $memberQuery->whereIn('group_id', $descendantIds);
            } elseif ($filterLevelId) {
                $memberQuery->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $memberQuery->whereIn('group_id', $user->group->getAllDescendantIds());
            }

            if ($refMeeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                $memberQuery->where('gender', $refMeeting->target_gender->value);
            }
            $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => \App\Models\AgeGroup::count());
            $selectedAgeGroupsCount = empty($refMeeting->target_age_groups) ? 0 : count($refMeeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
            if ($shouldFilterByAge) {
                $memberQuery->whereHas('ageGroup', fn ($q) => $q->whereIn('name', (array) $refMeeting->target_age_groups));
            }

            $totalMembers = $memberQuery->count();
            $notAttendedCount = max(0, $totalMembers - $attendanceCount);
            $attendanceRate = $totalMembers > 0 ? ($attendanceCount / $totalMembers) * 100 : 0;
            $isExpired = $refMeeting->isExpired();

            $stats = [
                Stat::make(AttendanceStatus::HADIR->getLabel(), $attendanceCount)
                    ->description('Total kehadiran')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
                Stat::make(($isToday && ! $isExpired) ? 'Belum Hadir' : 'Tidak Hadir', $notAttendedCount)
                    ->description(($isToday && ! $isExpired) ? 'Estimasi yang akan datang' : 'Tidak hadir tanpa keterangan')
                    ->descriptionIcon('heroicon-m-user-minus')
                    ->color($notAttendedCount > 0 ? ($isExpired ? 'danger' : 'warning') : 'gray'),
                Stat::make('Terlambat', $lateCount)
                    ->description('Hadir setelah waktu mulai sesi')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($lateCount > 0 ? 'warning' : 'gray'),
                Stat::make('Target Populasi', $totalMembers)
                    ->description('Total target anggota aktif')
                    ->descriptionIcon('heroicon-m-users'),
                Stat::make('Rasio Kehadiran', number_format($attendanceRate, 1).'%')
                    ->description('Persentase tingkat kehadiran')
                    ->descriptionIcon('heroicon-m-presentation-chart-line')
                    ->color($attendanceRate > 75 ? 'success' : ($attendanceRate > 50 ? 'warning' : 'danger')),
            ];

            // Pengurus
            $pengurusAttendanceQuery = Attendance::where('meeting_id', $refMeeting->id)
                ->whereHas('member', function ($q) {
                    $q->where('membership_type', \App\Enums\MembershipType::PENGURUS->value)->orWhereHas('positions');
                });

            if ($filterGroupId) {
                $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
                $pengurusAttendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            } elseif ($filterLevelId) {
                $pengurusAttendanceQuery->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            } elseif (! $user->isSuperAdmin() && $user->group_id) {
                $pengurusAttendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $user->group->getAllDescendantIds()));
            }

            $totalPengurusHadir = (clone $pengurusAttendanceQuery)->count();
            $pengurusBukanTarget = 0;
            if (! empty($refMeeting->target_age_groups)) {
                $pengurusBukanTarget = $pengurusAttendanceQuery
                    ->whereHas('member.ageGroup', fn ($q) => $q->whereNotIn('name', (array) $refMeeting->target_age_groups))
                    ->count();
            }

            $stats[] = Stat::make('Pengurus Hadir', $pengurusBukanTarget > 0 ? "{$pengurusBukanTarget} / {$totalPengurusHadir}" : (string) $totalPengurusHadir)
                ->description($pengurusBukanTarget > 0 ? 'Bukan target / Total hadir' : 'Total kehadiran pengurus')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info');

            return $stats;
        });
    }
}
