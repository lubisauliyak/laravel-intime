<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class GroupRanking extends BaseWidget
{
    public $filters = [];

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?string $heading = 'Peringkat Kelompok';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $meetingId = $this->filters['meeting_id'] ?? null;
        $groupId = $this->filters['group_id'] ?? null;
        $levelId = $this->filters['level_id'] ?? null;

        $meeting = $meetingId ? Meeting::find($meetingId) : null;

        $query = Group::query()->whereHas('members');
        if ($groupId) {
            $descendantIds = Group::find($groupId)->getAllDescendantIds();
            $query->whereIn('id', $descendantIds);
        } elseif ($levelId) {
            $query->where('level_id', $levelId);
        } elseif (! $user->isSuperAdmin() && $user->group_id) {
            $query->whereIn('id', $user->group->getAllDescendantIds());
        }

        $query->withCount(['members' => fn ($q) => $q->where('members.status', true)])
            ->addSelect([
                'present_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id'))
                    ->where('attendances.status', AttendanceStatus::HADIR)
                    ->when($meeting, fn ($q) => $q->where('meeting_id', $meeting->id)),
                'male_present_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id')->where('gender', Gender::MALE))
                    ->where('attendances.status', AttendanceStatus::HADIR)
                    ->when($meeting, fn ($q) => $q->where('meeting_id', $meeting->id)),
                'female_present_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id')->where('gender', Gender::FEMALE))
                    ->where('attendances.status', AttendanceStatus::HADIR)
                    ->when($meeting, fn ($q) => $q->where('meeting_id', $meeting->id)),
                'excused_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id'))
                    ->whereIn('status', [AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
                    ->when($meeting, fn ($q) => $q->where('meeting_id', $meeting->id)),
            ]);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')->label('Nama Kelompok')->searchable(),
                TextColumn::make('members_count')->label('Anggota')->alignCenter(),
                TextColumn::make('present_count')->label('Total Hadir')->badge()->color('success')->alignCenter(),
                TextColumn::make('male_present_count')->label('L')->badge()->color('info')->alignCenter(),
                TextColumn::make('female_present_count')->label('P')->badge()->color('danger')->alignCenter(),
                TextColumn::make('excused_count')->label('Izin/Sakit')->badge()->color('warning')->alignCenter(),
                TextColumn::make('attendance_percentage')
                    ->label('% Hadir')
                    ->getStateUsing(function ($record) {
                        if ($record->members_count <= 0) {
                            return '0%';
                        }

                        return number_format(($record->present_count / $record->members_count) * 100, 1).'%';
                    })
                    ->badge()
                    ->color(fn ($state) => ((float) $state >= 80) ? 'success' : (((float) $state >= 50) ? 'warning' : 'danger'))
                    ->alignCenter(),
            ])
            ->defaultSort('present_count', 'desc');
    }
}
