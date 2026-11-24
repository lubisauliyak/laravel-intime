<?php

namespace App\Filament\Widgets;

use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class TopInsightWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 100;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Wawasan Utama';

    public function table(Table $table): Table
    {
        $insights = $this->getInsightData();

        return $table
            ->query(
                // Menggunakan Meeting sebagai dummy query, data akan di-override oleh records
                Meeting::query()->limit(0)
            )
            ->emptyStateHeading('Belum ada wawasan')
            ->emptyStateDescription('Dashboard akan melakukan analisis otomatis setelah data presensi terkumpul.')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->columns([
                TextColumn::make('icon')
                    ->label('')
                    ->html()
                    ->width('40px'),
                TextColumn::make('title')
                    ->label('Insight')
                    ->weight('bold')
                    ->size('sm'),
                TextColumn::make('body')
                    ->label('Detail')
                    ->wrap()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->records(fn () => collect($insights))
            ->paginated(false);
    }

    private function getInsightData(): array
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? null;

        if (! $filterMeetingId) {
            return [];
        }

        $user = auth()->user();
        $filterGroupId = $this->filters['group_id'] ?? null;
        $filterLevelId = $this->filters['level_id'] ?? null;
        $cacheKey = 'top_insights_'.($user->group_id ?? 'all')."_m{$filterMeetingId}_g{$filterGroupId}_l{$filterLevelId}";

        return Cache::remember($cacheKey, 300, function () use ($user, $filterGroupId, $filterLevelId) {
            $insights = [];

            $filterMeetingId = $this->filters['meeting_id'] ?? null;

            // 1. Trend Analysis
            $currentMeeting = Meeting::with('group')->find($filterMeetingId);
            if (! $currentMeeting || ! $currentMeeting->group_id) {
                return [];
            }

            $previousMeeting = Meeting::where('group_id', $currentMeeting->group_id)
                ->where('meeting_date', '<', $currentMeeting->meeting_date)
                ->latest('meeting_date')
                ->first();

            if ($previousMeeting) {
                $r1 = $this->getAttendanceRate($currentMeeting, $user);
                $r2 = $this->getAttendanceRate($previousMeeting, $user);

                $diff = $r1 - $r2;
                $trend = $diff >= 0 ? 'naik' : 'turun';
                $absDiff = abs(round($diff, 1));
                $emoji = $diff >= 0 ? '📈' : '📉';

                $insights[] = [
                    'icon' => $emoji,
                    'title' => 'Tren Kehadiran '.ucfirst($trend),
                    'body' => "Kehadiran pada \"{$currentMeeting->name}\" ({$r1}%) {$trend} {$absDiff}% dibanding pertemuan sebelumnya.",
                ];
            }

            // 2. Consistency Award (Top Group)
            $refMeeting = $currentMeeting;
            if ($refMeeting) {
                $topGroup = $this->getTopConsistentGroup($refMeeting, $user);
                if ($topGroup) {
                    $insights[] = [
                        'icon' => '⭐',
                        'title' => 'Kelompok Paling Rajin',
                        'body' => "Kelompok \"{$topGroup->name}\" mencatat kehadiran tertinggi ({$topGroup->rate}%).",
                    ];
                }
            }

            // 3. Red Flag Alerts (Ghost Members)
            $ghostMembers = $this->getGhostMembers($user, $filterMeetingId, $filterGroupId, $filterLevelId);
            if (! empty($ghostMembers)) {
                $count = count($ghostMembers);
                $names = implode(', ', array_slice($ghostMembers, 0, 3));
                if ($count > 3) {
                    $names .= ' dan '.($count - 3).' lainnya';
                }

                $insights[] = [
                    'icon' => '⚠️',
                    'title' => "Peringatan: {$count} Anggota Tidak Hadir 3x",
                    'body' => $names,
                ];
            }

            return $insights;
        });
    }

    private function getAttendanceRate($meeting, $user): float
    {
        $attendanceQuery = Attendance::where('meeting_id', $meeting->id)
            ->where('status', \App\Enums\AttendanceStatus::HADIR);
        $memberQuery = Member::where('status', true);

        $filterGroupId = $this->filters['group_id'] ?? null;
        $filterLevelId = $this->filters['level_id'] ?? null;

        if ($filterGroupId) {
            $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
            $attendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            $memberQuery->whereIn('group_id', $descendantIds);
        } elseif ($filterLevelId) {
            $attendanceQuery->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
            $memberQuery->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
        } elseif (! $user->isSuperAdmin() && $user->group_id) {
            $descendantIds = $user->group->getAllDescendantIds();
            $attendanceQuery->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds));
            $memberQuery->whereIn('group_id', $descendantIds);
        }

        if ($meeting) {
            $attendanceQuery->whereHas('member', function ($q) use ($meeting) {
                if ($meeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                    $q->where('gender', $meeting->target_gender->value);
                }

                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
                $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                if ($shouldFilterByAge) {
                    $q->whereHas('ageGroup', fn ($aq) => $aq->whereIn('name', (array) $meeting->target_age_groups));
                }
            });

            if ($meeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                $memberQuery->where('gender', $meeting->target_gender->value);
            }

            $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

            if ($shouldFilterByAge) {
                $memberQuery->whereHas('ageGroup', fn ($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
            }
        }

        $baseTotalMembers = $memberQuery->count();
        $attended = $attendanceQuery->count();

        $nonTargetAttended = (clone $attendanceQuery)
            ->whereHas('member', fn ($m) => $m->where('status', false))
            ->count();

        $totalDynamicTarget = $baseTotalMembers + $nonTargetAttended;

        return $totalDynamicTarget > 0 ? round(($attended / $totalDynamicTarget) * 100, 1) : 0;
    }

    private function getTopConsistentGroup($meeting, $user)
    {
        $groupsQuery = Group::whereHas('members');
        if (! $user->isSuperAdmin() && $user->group_id) {
            $groupsQuery->whereIn('id', $user->group->getAllDescendantIds());
        }
        $groups = $groupsQuery->get();

        return $groups->map(function (Group $group) use ($meeting) {
            $memberQuery = $group->members()->where('status', true);

            // Filter by target
            if ($meeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                $memberQuery->where('gender', $meeting->target_gender->value);
            }

            $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

            if ($shouldFilterByAge) {
                $memberQuery->whereHas('ageGroup', fn ($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
            }

            $totalMembers = $memberQuery->count();
            if ($totalMembers === 0) {
                return null;
            }

            $attended = Attendance::where('meeting_id', $meeting->id)
                ->where('status', \App\Enums\AttendanceStatus::HADIR)
                ->whereHas('member', function ($q) use ($group, $meeting) {
                    $q->where('group_id', $group->id);

                    if ($meeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
                        $q->where('gender', $meeting->target_gender->value);
                    }

                    $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
                    $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                    $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                    if ($shouldFilterByAge) {
                        $q->whereHas('ageGroup', fn ($aq) => $aq->whereIn('name', (array) $meeting->target_age_groups));
                    }
                })->count();

            return (object) [
                'name' => $group->name,
                'rate' => round(($attended / $totalMembers) * 100, 1),
            ];
        })->filter()->sortByDesc('rate')->first();
    }

    private function getGhostMembers($user, $filterMeetingId, $filterGroupId = null, $filterLevelId = null): array
    {
        $refMeeting = Meeting::find($filterMeetingId);
        if (! $refMeeting) {
            return [];
        }

        $meetingQuery = Meeting::where('meeting_date', '<=', $refMeeting->meeting_date);

        if ($filterGroupId) {
            $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
            $meetingQuery->whereIn('group_id', $descendantIds);
        } elseif ($filterLevelId) {
            $meetingQuery->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
        } elseif (! $user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
        }

        $lastMeetings = $meetingQuery->latest('meeting_date')->limit(3)->pluck('id');

        if ($lastMeetings->count() < 3) {
            return [];
        }

        $memberQuery = Member::where('status', true);

        if ($filterGroupId) {
            $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
            $memberQuery->whereIn('group_id', $descendantIds);
        } elseif ($filterLevelId) {
            $memberQuery->whereHas('group', fn ($q) => $q->where('level_id', $filterLevelId));
        } elseif (! $user->isSuperAdmin() && $user->group_id) {
            $memberQuery->whereIn('group_id', $user->group->getAllDescendantIds());
        }

        return $memberQuery->whereDoesntHave('attendances', function ($q) use ($lastMeetings) {
            $q->whereIn('meeting_id', $lastMeetings)->where('status', \App\Enums\AttendanceStatus::HADIR);
        })->limit(10)->pluck('full_name')->toArray();
    }
}
