<?php

namespace App\Filament\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GroupRanking extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?string $heading = 'Peringkat Kelompok';

    public function table(Table $table): Table
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? null;

        if (! $filterMeetingId) {
            return $table
                ->query(Group::whereRaw('1 = 0'))
                ->columns([
                    TextColumn::make('name')->label('Nama Kelompok'),
                    TextColumn::make('members_count')->label('Anggota'),
                    TextColumn::make('present_count')->label('Total Hadir'),
                    TextColumn::make('male_present_count')->label('L'),
                    TextColumn::make('female_present_count')->label('P'),
                    TextColumn::make('excused_count')->label('Izin/Sakit'),
                    TextColumn::make('attendance_percentage')->label('% Hadir'),
                ])
                ->emptyStateHeading('Belum ada data')
                ->emptyStateDescription('Silakan pilih pertemuan pada filter di atas')
                ->emptyStateIcon('heroicon-o-hand-raised')
                ->paginated(false);
        }

        $user = auth()->user();
        $filterGroupId = $this->filters['group_id'] ?? null;
        $filterLevelId = $this->filters['level_id'] ?? null;

        $refMeeting = Meeting::find($filterMeetingId);

        return $table
            ->query($this->getRankingQuery($user, $refMeeting, $filterGroupId, $filterLevelId))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kelompok')
                    ->searchable(),
                TextColumn::make('members_count')
                    ->label('Anggota')
                    ->alignCenter(),
                TextColumn::make('present_count')
                    ->label('Total Hadir')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                TextColumn::make('male_present_count')
                    ->label('L')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('female_present_count')
                    ->label('P')
                    ->badge()
                    ->color('danger')
                    ->alignCenter(),
                TextColumn::make('excused_count')
                    ->label('Izin/Sakit')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                TextColumn::make('attendance_percentage')
                    ->label('% Hadir')
                    ->getStateUsing(function ($record) {
                        if ($record->members_count <= 0) {
                            return '0%';
                        }
                        $percentage = ($record->present_count / $record->members_count) * 100;

                        return number_format($percentage, 1).'%';
                    })
                    ->badge()
                    ->color(fn ($state) => ((float) $state >= 80) ? 'success' : (((float) $state >= 50) ? 'warning' : 'danger'))
                    ->alignCenter()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            DB::raw('CAST(present_count AS DECIMAL) / NULLIF(members_count, 0)'),
                            $direction
                        );
                    }),
            ])
            ->defaultSort('attendance_percentage', 'desc');
    }

    private function getRankingQuery($user, ?Meeting $refMeeting, $filterGroupId, $filterLevelId): Builder
    {
        $query = Group::query()->whereHas('members');

        if ($filterGroupId) {
            $descendantIds = Group::find($filterGroupId)->getAllDescendantIds();
            $query->whereIn('id', $descendantIds);
        } elseif ($filterLevelId) {
            $query->where('level_id', $filterLevelId);
        } elseif (! $user->isSuperAdmin() && $user->group_id) {
            $query->whereIn('id', $user->group->getAllDescendantIds());
        }

        return $query
            ->withCount(['members' => fn ($q) => $q->where('status', true)])
            ->addSelect([
                'present_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id'))
                    ->where('status', AttendanceStatus::HADIR)
                    ->when($refMeeting, fn ($q) => $q->where('meeting_id', $refMeeting->id))
                    ->when(! $refMeeting, fn ($q) => $q->whereRaw('0 = 1')),

                'male_present_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id')->where('gender', Gender::MALE))
                    ->where('status', AttendanceStatus::HADIR)
                    ->when($refMeeting, fn ($q) => $q->where('meeting_id', $refMeeting->id))
                    ->when(! $refMeeting, fn ($q) => $q->whereRaw('0 = 1')),

                'female_present_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id')->where('gender', Gender::FEMALE))
                    ->where('status', AttendanceStatus::HADIR)
                    ->when($refMeeting, fn ($q) => $q->where('meeting_id', $refMeeting->id))
                    ->when(! $refMeeting, fn ($q) => $q->whereRaw('0 = 1')),

                'excused_count' => Attendance::selectRaw('count(*)')
                    ->whereHas('member', fn ($q) => $q->whereColumn('group_id', 'groups.id'))
                    ->whereIn('status', [AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
                    ->when($refMeeting, fn ($q) => $q->where('meeting_id', $refMeeting->id))
                    ->when(! $refMeeting, fn ($q) => $q->whereRaw('0 = 1')),
            ]);
    }
}
