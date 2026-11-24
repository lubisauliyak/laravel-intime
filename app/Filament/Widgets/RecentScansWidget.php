<?php

namespace App\Filament\Widgets;

use App\Enums\MembershipType;
use App\Models\Attendance;
use App\Models\Meeting;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentScansWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = true;

    protected static ?int $sort = 11;

    // Responsive column span
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected static ?string $heading = 'Pindaian Terakhir';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        // Find reference meeting
        $filterMeetingId = $this->filters['meeting_id'] ?? null;
        $filterGroupId = $this->filters['group_id'] ?? null;
        $filterLevelId = $this->filters['level_id'] ?? null;

        $refMeeting = $filterMeetingId ? Meeting::find($filterMeetingId) : null;

        if (! $refMeeting) {
            return $table
                ->query(Attendance::query()->whereRaw('0 = 1'))
                ->columns([
                    TextColumn::make('member.full_name')
                        ->label('Nama Anggota'),
                    TextColumn::make('member.gender')
                        ->label('L/P'),
                    TextColumn::make('member.group.name')
                        ->label('Kelompok'),
                    TextColumn::make('checkin_time')
                        ->label('Pukul'),
                    TextColumn::make('status')
                        ->label('Status'),
                    TextColumn::make('notes')
                        ->label('Keterangan'),
                ])
                ->emptyStateHeading('Belum ada data')
                ->emptyStateDescription('Silakan pilih pertemuan pada filter di atas')
                ->emptyStateIcon('heroicon-o-hand-raised')
                ->paginated(false);
        }

        $isToday = $refMeeting && $refMeeting->meeting_date->isToday();

        $query = Attendance::query()->latest('checkin_time');

        if ($refMeeting) {
            $query->where('meeting_id', $refMeeting->id);
        }

        if ($filterGroupId) {
            $allowedGroupIds = \App\Models\Group::find($filterGroupId)->getAllDescendantIds();
            $query->whereIn('member_id', \App\Models\Member::whereIn('group_id', $allowedGroupIds)->pluck('id'));
        } elseif ($filterLevelId) {
            $query->whereHas('member.group', fn ($q) => $q->where('level_id', $filterLevelId));
        } elseif (! $user->isSuperAdmin() && $user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            $authGroupIds = array_merge([$user->group_id], $user->group->getAllAncestorIds());

            $query->whereHas('member', function ($q) use ($allowedGroupIds, $authGroupIds) {
                $q->whereIn('group_id', $allowedGroupIds)
                    ->orWhere(function ($sq) use ($authGroupIds) {
                        $sq->where('membership_type', MembershipType::PENGURUS->value)
                            ->whereHas('positions', fn ($pq) => $pq->whereIn('group_id', $authGroupIds));
                    });
            });
        }

        return $table
            ->query($query->limit(10))
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Nama Anggota'),
                TextColumn::make('member.gender')
                    ->label('L/P')
                    ->getStateUsing(fn ($record) => $record->member->gender->getCode())
                    ->badge()
                    ->color(fn ($record) => $record->member->gender->getColor()),
                TextColumn::make('member.group.name')
                    ->label('Kelompok'),
                TextColumn::make('checkin_time')
                    ->label('Pukul')
                    ->time('H:i:s')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->badge()
                    ->color(fn ($state) => str_contains($state ?? '', 'TERLAMBAT') ? 'danger' : 'gray'),
            ])
            ->paginated(false);
    }
}
