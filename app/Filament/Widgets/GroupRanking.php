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

    protected static ?int $sort = 8;
    
    // Responsive column span
    protected int|string|array $columnSpan = 'full';
    
    protected ?string $pollingInterval = '30s';

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

        $heading = $isToday
            ? 'Ranking Kelompok'
            : 'Ranking Kelompok (' . ($refMeeting ? $refMeeting->meeting_date->format('d/m/Y') : '-') . ')';

        $query = Group::query()->whereHas('members');

        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            $query->whereIn('id', $allowedGroupIds);
        }

        return $table
            ->heading($heading)
            ->query(
                $query
                    ->addSelect([
                        'total_attendance' => Attendance::selectRaw('count(*)')
                            ->whereHas('member', function (Builder $query) {
                                $query->whereColumn('group_id', 'groups.id');
                            })
                            ->when($refMeeting, fn($q) => $q->where('meeting_id', $refMeeting->id))
                            ->when(!$refMeeting, fn($q) => $q->whereDate('checkin_time', now()))
                    ])
                    ->withCount('members')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Grup')
                    ->searchable(),
                TextColumn::make('members_count')
                    ->label('Anggota')
                    ->sortable(),
                TextColumn::make('total_attendance')
                    ->label('Total Presensi Hadir')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('total_attendance', 'desc');
    }
}
