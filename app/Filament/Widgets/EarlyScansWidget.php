<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Enums\AttendanceStatus;
use App\Enums\MembershipType;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class EarlyScansWidget extends BaseWidget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Pindaian Terawal';

    private function getReferenceMeeting(): ?Meeting
    {
        $filterMeetingId = $this->filters['meeting_id'] ?? null;

        return $filterMeetingId ? Meeting::find($filterMeetingId) : null;
    }

    public function table(Table $table): Table
    {
        $ref = $this->getReferenceMeeting();

        if (!$ref) {
            return $table
                ->query(Attendance::query()->whereRaw('0 = 1'))
                ->columns([
                    Tables\Columns\TextColumn::make('member.full_name')
                        ->label('Nama Anggota'),
                    Tables\Columns\TextColumn::make('member.gender')
                        ->label('L/P'),
                    Tables\Columns\TextColumn::make('member.group.name')
                        ->label('Kelompok'),
                    Tables\Columns\TextColumn::make('checkin_time')
                        ->label('Pukul'),
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status'),
                    Tables\Columns\TextColumn::make('notes')
                        ->label('Keterangan'),
                ])
                ->emptyStateHeading('Belum ada data')
                ->emptyStateDescription('Silakan pilih pertemuan pada filter di atas')
                ->emptyStateIcon('heroicon-o-hand-raised')
                ->paginated(false);
        }

        $user = auth()->user();
        $filterGroupId = $this->filters['group_id'] ?? null;
        $filterLevelId = $this->filters['level_id'] ?? null;

        $query = Attendance::query()
            ->where('status', AttendanceStatus::HADIR)
            ->where('meeting_id', $ref->id);

        if ($filterGroupId) {
            $allowedGroupIds = \App\Models\Group::find($filterGroupId)->getAllDescendantIds();
            $query->whereIn('member_id', \App\Models\Member::whereIn('group_id', $allowedGroupIds)->pluck('id'));
        } elseif ($filterLevelId) {
            $query->whereHas('member.group', fn($q) => $q->where('level_id', $filterLevelId));
        } elseif (!$user->isSuperAdmin() && $user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            $authGroupIds = array_merge([$user->group_id], $user->group->getAllAncestorIds());

            $query->whereHas('member', function ($q) use ($allowedGroupIds, $authGroupIds) {
                $q->whereIn('group_id', $allowedGroupIds)
                    ->orWhere(function ($sq) use ($authGroupIds) {
                        $sq->where('membership_type', MembershipType::PENGURUS->value)
                            ->whereHas('positions', fn($pq) => $pq->whereIn('group_id', $authGroupIds));
                    });
            });
        }

        return $table
            ->query($query->orderBy('checkin_time', 'asc')->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Nama Anggota'),
                Tables\Columns\TextColumn::make('member.gender')
                    ->label('L/P')
                    ->getStateUsing(fn ($record) => $record->member->gender->getCode())
                    ->badge()
                    ->color(fn ($record) => $record->member->gender->getColor()),
                Tables\Columns\TextColumn::make('member.group.name')
                    ->label('Kelompok'),
                Tables\Columns\TextColumn::make('checkin_time')
                    ->label('Pukul')
                    ->time('H:i:s')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Keterangan')
                    ->badge()
                    ->color(fn($state) => str_contains($state ?? '', 'TERLAMBAT') ? 'danger' : 'gray'),
            ])
            ->paginated(false);
    }
}
