<?php

namespace App\Filament\Resources\Meetings\Widgets;

use Livewire\Attributes\On;

use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Group;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentScansWidget extends BaseWidget
{
    public $filters = [];
    protected static bool $isLazy = true;
    protected static ?string $heading = 'Pindaian Terakhir';
    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        $meetingId = $this->filters['meeting_id'] ?? null;
        $groupId = $this->filters['group_id'] ?? null;

        $query = Attendance::query()->where('meeting_id', $meetingId)->orderBy('checkin_time', 'desc');

        if ($groupId) {
            $descendantIds = Group::find($groupId)->getAllDescendantIds();
            $query->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds));
        }

        $query->limit(10);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('member.full_name')->label('Nama Anggota'),
                TextColumn::make('member.gender')
                    ->label('L/P')
                    ->getStateUsing(fn ($record) => $record->member->gender->getCode())
                    ->badge()
                    ->color(fn ($record) => $record->member->gender->getColor()),
                TextColumn::make('member.group.name')->label('Kelompok'),
                TextColumn::make('checkin_time')->label('Pukul')->time('H:i:s')->alignCenter(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('notes')->label('Keterangan')
                    ->badge()
                    ->color(fn($state) => str_contains($state ?? '', 'TERLAMBAT') ? 'danger' : 'gray'),
            ])
            ->paginated(false);
    }
}
