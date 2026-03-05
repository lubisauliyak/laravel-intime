<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\AgeGroup;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class TopInsightWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 100;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        $insights = $this->getInsightData();

        return $table
            ->heading('💡 Wawasan')
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
        $user = auth()->user();
        $cacheKey = 'top_insights_' . ($user->group_id ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $insights = [];

            // 1. Trend Analysis
            $meetingQuery = Meeting::query();
            if (!$user->isSuperAdmin() && $user->group_id) {
                $allowedMeetingGroupIds = array_merge(
                    [$user->group_id],
                    $user->group->getAllAncestorIds()
                );
                $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
            }
            $lastTwoMeetings = $meetingQuery->latest('meeting_date')->limit(2)->get();

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
                    'title' => "Tren Kehadiran " . ucfirst($trend),
                    'body' => "Kehadiran pada \"{$m1->name}\" ({$r1}%) {$trend} {$absDiff}% dibanding pertemuan sebelumnya.",
                ];
            }

            // 2. Consistency Award (Top Group)
            $refMeeting = $lastTwoMeetings->first() ?? null;
            if ($refMeeting) {
                $topGroup = $this->getTopConsistentGroup($refMeeting, $user);
                if ($topGroup) {
                    $insights[] = [
                        'icon' => '⭐',
                        'title' => "Kelompok Paling Rajin",
                        'body' => "Kelompok \"{$topGroup->name}\" mencatat kehadiran tertinggi ({$topGroup->rate}%).",
                    ];
                }
            }

            // 3. Red Flag Alerts (Ghost Members)
            $ghostMembers = $this->getGhostMembers($user);
            if (!empty($ghostMembers)) {
                $count = count($ghostMembers);
                $names = implode(', ', array_slice($ghostMembers, 0, 3));
                if ($count > 3) $names .= " dan " . ($count - 3) . " lainnya";

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
        $attendanceQuery = Attendance::where('meeting_id', $meeting->id);
        $memberQuery = Member::where('status', true);

        if (!$user->isSuperAdmin() && $user->group_id) {
            $descendantIds = $user->group->getAllDescendantIds();
            $attendanceQuery->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds));
            $memberQuery->whereIn('group_id', $descendantIds);
        }

        if ($meeting) {
            $attendanceQuery->whereHas('member', function ($q) use ($meeting) {
                if ($meeting->target_gender !== 'all') {
                    $q->where('gender', $meeting->target_gender);
                }

                $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => AgeGroup::count());
                $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                if ($shouldFilterByAge) {
                    $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', (array) $meeting->target_age_groups));
                }
            });

            if ($meeting->target_gender !== 'all') {
                $memberQuery->where('gender', $meeting->target_gender);
            }

            $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => AgeGroup::count());
            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

            if ($shouldFilterByAge) {
                $memberQuery->whereHas('ageGroup', fn($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
            }
        }

        $totalMembers = $memberQuery->count();
        $attended = $attendanceQuery->count();

        return $totalMembers > 0 ? round(($attended / $totalMembers) * 100, 1) : 0;
    }

    private function getTopConsistentGroup($meeting, $user)
    {
        $groupsQuery = Group::whereHas('members');
        if (!$user->isSuperAdmin() && $user->group_id) {
            $groupsQuery->whereIn('id', $user->group->getAllDescendantIds());
        }
        $groups = $groupsQuery->get();

        return $groups->map(function ($group) use ($meeting) {
            $memberQuery = $group->members()->where('status', true);
            
            // Filter by target
            if ($meeting->target_gender !== 'all') {
                $memberQuery->where('gender', $meeting->target_gender);
            }

            $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => AgeGroup::count());
            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

            if ($shouldFilterByAge) {
                $memberQuery->whereHas('ageGroup', fn($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
            }

            $totalMembers = $memberQuery->count();
            if ($totalMembers === 0) return null;

            $attended = Attendance::where('meeting_id', $meeting->id)
                ->whereHas('member', function ($q) use ($group, $meeting) {
                    $q->where('group_id', $group->id);
                    
                    if ($meeting->target_gender !== 'all') {
                        $q->where('gender', $meeting->target_gender);
                    }

                    $allAgeGroupsCount = Cache::remember('all_age_groups_count', 3600, fn() => AgeGroup::count());
                    $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                    $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

                    if ($shouldFilterByAge) {
                        $q->whereHas('ageGroup', fn($aq) => $aq->whereIn('name', (array) $meeting->target_age_groups));
                    }
                })->count();

            return (object) [
                'name' => $group->name,
                'rate' => round(($attended / $totalMembers) * 100, 1)
            ];
        })->filter()->sortByDesc('rate')->first();
    }

    private function getGhostMembers($user): array
    {
        $meetingQuery = Meeting::query();
        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
        }
        $lastMeetings = $meetingQuery->latest('meeting_date')->limit(3)->pluck('id');

        if ($lastMeetings->count() < 3) return [];

        $memberQuery = Member::where('status', true);
        if (!$user->isSuperAdmin() && $user->group_id) {
            $memberQuery->whereIn('group_id', $user->group->getAllDescendantIds());
        }

        return $memberQuery->whereDoesntHave('attendances', function ($q) use ($lastMeetings) {
            $q->whereIn('meeting_id', $lastMeetings)->where('status', 'hadir');
        })->limit(10)->pluck('full_name')->toArray();
    }
}
