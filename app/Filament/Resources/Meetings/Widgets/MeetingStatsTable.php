<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Member;
use App\Models\Meeting;
use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MeetingStatsTable extends TableWidget
{
    public ?Meeting $record = null;

    public $parentId = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Statistik Kehadiran per Grup';

    public function table(Table $table): Table
    {
        $meeting = $this->record;

        if (!$meeting) {
            return $table->query(fn() => Group::whereRaw('1 = 0'));
        }

        $user = auth()->user();

        return $table
            ->query(function () use ($meeting, $user) {
                if ($this->parentId) {
                    return Group::where('parent_id', $this->parentId);
                }

                // Phased Screening: Tentukan titik start berdasarkan hierarki user
                $startGroupId = $meeting->group_id;

                if (!$user->isSuperAdmin() && $user->group_id) {
                    // Jika user berada di bawah level penyelenggara, mulai dari grup user tersebut
                    if ($user->group_id === $meeting->group_id || in_array($meeting->group_id, $user->group->getAllAncestorIds())) {
                        $startGroupId = $user->group_id;
                    }
                }

                return Group::query()->where('id', $startGroupId);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Grup')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('level.name')
                    ->label('Tingkat')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subgroup_count')
                    ->label('Subgrup')
                    ->alignCenter()
                    ->getStateUsing(fn (Group $record) => $record->children()->count() . ' grup')
                    ->toggleable()
                    ->visible(function () use ($meeting) {
                        if ($this->parentId) {
                            return Group::where('parent_id', $this->parentId)->whereHas('children')->exists();
                        }
                        return $meeting->group->children()->exists();
                    }),

                TextColumn::make('members_count')
                    ->label('Anggota')
                    ->alignCenter()
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        $allAgeGroupsCount = \App\Models\AgeGroup::count();
                        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
                        $baseTargetCount = Member::whereIn('group_id', $descendantIds)
                            ->where('status', true)
                            ->when($meeting->target_gender->value !== \App\Enums\MeetingTargetGender::ALL->value, function ($q) use ($meeting) {
                                return $q->where('gender', $meeting->target_gender->value);
                            })
                            ->when($shouldFilterByAge, function ($q) use ($meeting) {
                                return $q->whereHas('ageGroup', function ($aq) use ($meeting) {
                                    return $aq->whereIn('name', (array) $meeting->target_age_groups);
                                });
                            })
                            ->count();

                        $nonTargetAttended = Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('status', [\App\Enums\AttendanceStatus::HADIR, \App\Enums\AttendanceStatus::IZIN, \App\Enums\AttendanceStatus::SAKIT])
                            ->whereHas('member', function ($q) use ($descendantIds) {
                                $q->whereIn('group_id', $descendantIds)->where('status', false);
                            })
                            ->count();

                        return $baseTargetCount + $nonTargetAttended;
                    }),

                TextColumn::make('present_count')
                    ->label('Hadir')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Attendance::where('meeting_id', $meeting->id)
                            ->where('status', \App\Enums\AttendanceStatus::HADIR)
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds))
                            ->count();
                    }),

                TextColumn::make('present_l_count')
                    ->label('L')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Attendance::where('meeting_id', $meeting->id)
                            ->where('status', \App\Enums\AttendanceStatus::HADIR)
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds)->where('gender', \App\Enums\Gender::MALE))
                            ->count();
                    }),

                TextColumn::make('present_p_count')
                    ->label('P')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Attendance::where('meeting_id', $meeting->id)
                            ->where('status', \App\Enums\AttendanceStatus::HADIR)
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds)->where('gender', \App\Enums\Gender::FEMALE))
                            ->count();
                    }),

                TextColumn::make('permission_sick_count')
                    ->label('Izin/Sakit')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('status', [\App\Enums\AttendanceStatus::IZIN, \App\Enums\AttendanceStatus::SAKIT])
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds))
                            ->count();
                    }),

                TextColumn::make('excused_l_count')
                    ->label('L')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('status', [\App\Enums\AttendanceStatus::IZIN, \App\Enums\AttendanceStatus::SAKIT])
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds)->where('gender', \App\Enums\Gender::MALE))
                            ->count();
                    }),

                TextColumn::make('excused_p_count')
                    ->label('P')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('status', [\App\Enums\AttendanceStatus::IZIN, \App\Enums\AttendanceStatus::SAKIT])
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds)->where('gender', \App\Enums\Gender::FEMALE))
                            ->count();
                    }),

                TextColumn::make('alfa_or_belum_count')
                    ->label(fn () => $meeting->isExpired() ? 'Alpa' : 'Belum Hadir')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        $allAgeGroupsCount = \App\Models\AgeGroup::count();
                        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
                        $targetMemberIds = Member::whereIn('group_id', $descendantIds)
                            ->where('status', true)
                            ->when($meeting->target_gender->value !== \App\Enums\MeetingTargetGender::ALL->value, function ($q) use ($meeting) {
                                return $q->where('gender', $meeting->target_gender->value);
                            })
                            ->when($shouldFilterByAge, function ($q) use ($meeting) {
                                return $q->whereHas('ageGroup', function ($aq) use ($meeting) {
                                    return $aq->whereIn('name', (array) $meeting->target_age_groups);
                                });
                            })
                            ->pluck('id');

                        $target = $targetMemberIds->count();
                        if ($target === 0) return 0;

                        $attendedTargetCount = Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('member_id', $targetMemberIds)
                            ->whereIn('status', [\App\Enums\AttendanceStatus::HADIR, \App\Enums\AttendanceStatus::IZIN, \App\Enums\AttendanceStatus::SAKIT])
                            ->count();

                        return max(0, $target - $attendedTargetCount);
                    }),

                TextColumn::make('attendance_percentage')
                    ->label('% Hadir')
                    ->alignRight()
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        $allAgeGroupsCount = \App\Models\AgeGroup::count();
                        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
                        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
                        $baseTargetCount = Member::whereIn('group_id', $descendantIds)
                            ->where('status', true)
                            ->when($meeting->target_gender->value !== \App\Enums\MeetingTargetGender::ALL->value, function ($q) use ($meeting) {
                                return $q->where('gender', $meeting->target_gender->value);
                            })
                            ->when($shouldFilterByAge, function ($q) use ($meeting) {
                                return $q->whereHas('ageGroup', function ($aq) use ($meeting) {
                                    return $aq->whereIn('name', (array) $meeting->target_age_groups);
                                });
                            })
                            ->count();

                        $nonTargetAttended = Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('status', [\App\Enums\AttendanceStatus::HADIR, \App\Enums\AttendanceStatus::IZIN, \App\Enums\AttendanceStatus::SAKIT])
                            ->whereHas('member', function ($q) use ($descendantIds) {
                                $q->whereIn('group_id', $descendantIds)->where('status', false);
                            })
                            ->count();

                        $totalDynamicTarget = $baseTargetCount + $nonTargetAttended;
                        if ($totalDynamicTarget === 0) return '0%';

                        $presentCount = Attendance::where('meeting_id', $meeting->id)
                            ->where('status', \App\Enums\AttendanceStatus::HADIR)
                            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds))
                            ->count();

                        return round(($presentCount / $totalDynamicTarget) * 100, 1) . '%';
                    })
                    ->weight('bold')
                    ->color(fn ($state) => floatval($state) >= 75 ? 'success' : (floatval($state) >= 50 ? 'warning' : 'danger')),
            ])
            ->headerActions([
                Action::make('back_to_main')
                    ->label('Kembali ke Atas')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn () => $this->parentId !== null)
                    ->action(fn () => $this->parentId = null),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view_members')
                        ->label('Lihat Nama')
                        ->icon('heroicon-m-users')
                        ->color('info')
                        ->url(fn (Group $record) => MeetingResource::getUrl('attendance-details', [
                            'record' => $meeting->uid,
                        ]) . "?group={$record->uid}"),
                    Action::make('view_subgroups')
                        ->label('Lihat Subgrup')
                        ->icon('heroicon-m-chevron-right')
                        ->color('primary')
                        ->visible(fn (Group $record) => $record->children()->exists())
                        ->action(fn (Group $record) => $this->parentId = $record->id),
                ])
            ])
            ->paginated(false);
    }
}
