<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class GroupRanking extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 12;
    
    // Responsive column span
    protected int|string|array $columnSpan = 'full';
    
    protected ?string $pollingInterval = '30s';
    protected static ?string $heading = 'Peringkat Kelompok';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        
        // Find reference meeting
        $meetingQuery = Meeting::where('meeting_date', '<=', now()->toDateString());
        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
        }
        $refMeeting = $meetingQuery->latest('meeting_date')->first();
        $isToday = $refMeeting && $refMeeting->meeting_date->isToday();

        $query = Group::query()->whereHas('members');

        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            $query->whereIn('id', $allowedGroupIds);
        }

        return $table
            ->query(
                $query
                    ->addSelect([
                        'present_count' => Attendance::selectRaw('count(*)')
                            ->whereHas('member', function (Builder $query) {
                                $query->whereColumn('group_id', 'groups.id');
                            })
                            ->where('status', 'hadir')
                            ->when($refMeeting, fn($q) => $q->where('meeting_id', $refMeeting->id))
                            ->when(!$refMeeting, fn($q) => $q->whereDate('checkin_time', now())),
                        'excused_count' => Attendance::selectRaw('count(*)')
                            ->whereHas('member', function (Builder $query) {
                                $query->whereColumn('group_id', 'groups.id');
                            })
                            ->whereIn('status', ['izin', 'sakit'])
                            ->when($refMeeting, fn($q) => $q->where('meeting_id', $refMeeting->id))
                            ->when(!$refMeeting, fn($q) => $q->whereDate('checkin_time', now())),
                    ])
                    ->withCount(['members' => function (Builder $query) use ($refMeeting) {
                        // Optional: If meeting has target filtering, we might want to filter here too.
                        // But usually ranking is for the whole group members.
                        $query->where('status', true);
                    }])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kelompok')
                    ->searchable(),
                TextColumn::make('members_count')
                    ->label('Anggota')
                    ->alignCenter(),
                TextColumn::make('present_count')
                    ->label('Hadir')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                TextColumn::make('excused_count')
                    ->label('Izin/Sakit')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                TextColumn::make('absent_count')
                    ->label('Tidak Hadir')
                    ->getStateUsing(fn ($record) => max(0, $record->members_count - ($record->present_count + $record->excused_count)))
                    ->badge()
                    ->color('danger')
                    ->alignCenter(),
                TextColumn::make('attendance_percentage')
                    ->label('% Hadir')
                    ->getStateUsing(function ($record) {
                        if ($record->members_count <= 0) return '0%';
                        $percentage = ($record->present_count / $record->members_count) * 100;
                        return number_format($percentage, 1) . '%';
                    })
                    ->badge()
                    ->color(fn ($state) => ((float) $state >= 80) ? 'success' : (((float) $state >= 50) ? 'warning' : 'danger'))
                    ->alignCenter(),
            ])
            ->defaultSort('present_count', 'desc');
    }
}
