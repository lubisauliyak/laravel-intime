<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class GroupRanking extends BaseWidget
{
    protected static ?string $heading = 'Ranking Grup (Kehadiran Terbanyak)';
    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Group::query()
                    ->whereHas('members')
                    ->addSelect([
                        'total_attendance' => Attendance::selectRaw('count(*)')
                            ->whereHas('member', function (Builder $query) {
                                $query->whereColumn('group_id', 'groups.id');
                            })
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
                    ->label('Total Scan Hadir')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('total_attendance', 'desc');
    }
}
