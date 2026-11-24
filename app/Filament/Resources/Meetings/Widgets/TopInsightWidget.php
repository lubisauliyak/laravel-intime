<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class TopInsightWidget extends BaseWidget
{
    public $filters = [];

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Wawasan Utama';

    public function table(Table $table): Table
    {
        $insights = $this->getInsightData();

        return $table
            ->query(Meeting::query()->limit(0))
            ->emptyStateHeading('Belum ada wawasan')
            ->emptyStateDescription('Dashboard akan melakukan analisis otomatis setelah data presensi terkumpul.')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->columns([
                TextColumn::make('icon')->label('')->html()->width('40px'),
                TextColumn::make('title')->label('Insight')->weight('bold')->size('sm'),
                TextColumn::make('body')->label('Detail')->wrap()->size('sm')->color('gray'),
            ])
            ->records(fn () => collect($insights))
            ->paginated(false);
    }

    private function getInsightData(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_top_insights_'.($user->id).'_'.md5(json_encode($this->filters));

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $insights = [];
            $filterMeetingId = $this->filters['meeting_id'] ?? null;
            if (! $filterMeetingId) {
                return [];
            }

            $currentMeeting = Meeting::find($filterMeetingId);
            if (! $currentMeeting) {
                return [];
            }

            // 1. Trend Analysis
            $lastTwoMeetings = Meeting::where('meeting_date', '<=', $currentMeeting->meeting_date)
                ->latest('meeting_date')
                ->limit(2)
                ->get();

            if ($lastTwoMeetings->count() === 2) {
                $m1 = $lastTwoMeetings[0];
                $m2 = $lastTwoMeetings[1];
                $r1 = $this->getAttendanceRate($m1, $user);
                $r2 = $this->getAttendanceRate($m2, $user);
                $diff = $r1 - $r2;
                $trend = $diff >= 0 ? 'naik' : 'turun';
                $absDiff = abs(round($diff, 1));
                $emoji = $diff >= 0 ? '📈' : '📉';

                $insights[] = [
                    'icon' => $emoji,
                    'title' => 'Tren Kehadiran '.ucfirst($trend),
                    'body' => "Kehadiran pada \"{$m1->name}\" ({$r1}%) {$trend} {$absDiff}% dibanding pertemuan sebelumnya.",
                ];
            }

            // 2. Consistency Award (Top Group)
            $topGroup = $this->getTopConsistentGroup($currentMeeting, $user);
            if ($topGroup) {
                $insights[] = [
                    'icon' => '⭐',
                    'title' => 'Kelompok Paling Rajin',
                    'body' => "Kelompok \"{$topGroup->name}\" mencatat kehadiran tertinggi ({$topGroup->rate}%).",
                ];
            }

            return $insights;
        });
    }

    private function getAttendanceRate($meeting, $user): float
    {
        $attendanceQuery = Attendance::where('meeting_id', $meeting->id)->where('attendances.status', \App\Enums\AttendanceStatus::HADIR);
        $memberQuery = Member::where('members.status', true);
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

        if ($meeting->target_gender !== \App\Enums\MeetingTargetGender::ALL) {
            $attendanceQuery->whereHas('member', fn ($q) => $q->where('gender', $meeting->target_gender->value));
            $memberQuery->where('gender', $meeting->target_gender->value);
        }
        $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn () => AgeGroup::count());
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
        if ($shouldFilterByAge) {
            $attendanceQuery->whereHas('member.ageGroup', fn ($aq) => $aq->whereIn('name', (array) $meeting->target_age_groups));
            $memberQuery->whereHas('ageGroup', fn ($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
        }

        $totalMembers = $memberQuery->count();

        return $totalMembers > 0 ? round(($attendanceQuery->count() / $totalMembers) * 100, 1) : 0;
    }

    private function getTopConsistentGroup($meeting, $user)
    {
        $groupsQuery = Group::whereHas('members');
        if (! $user->isSuperAdmin() && $user->group_id) {
            $groupsQuery->whereIn('id', $user->group->getAllDescendantIds());
        }

        return $groupsQuery->get()->map(function (Group $group) use ($meeting) {
            $memberQuery = $group->members()->where('members.status', true);
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

            $attended = Attendance::where('meeting_id', $meeting->id)->where('attendances.status', \App\Enums\AttendanceStatus::HADIR)
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

            return (object) ['name' => $group->name, 'rate' => round(($attended / $totalMembers) * 100, 1)];
        })->filter()->sortByDesc('rate')->first();
    }
}
