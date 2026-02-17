<?php

namespace App\Filament\Resources\Groups\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level.name')
                    ->label('Tingkat')
                    ->badge()
                    ->color(fn ($record): string => match (($record->level?->level_number ?? 0) % 5) {
                        1 => 'success',
                        2 => 'warning',
                        3 => 'danger',
                        4 => 'info',
                        0 => 'primary', 
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Grup')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.full_name')
                    ->label('Induk Grup')
                    ->searchable(['name'])
                    ->sortable()
                    ->placeholder('-'),
                IconColumn::make('status')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('level_id')
                    ->label('Tingkat')
                    ->relationship('level', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('parent_id')
                    ->label('Induk Grup')
                    ->relationship('parent', 'groups.name', fn ($query) => $query->whereHas('children'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('status')
                    ->label('Status Aktif')
                    ->placeholder('Semua Status')
                    ->trueLabel('Aktif Saja')
                    ->falseLabel('Non-Aktif Saja')
                    ->queries(
                        true: fn ($query) => $query->where('status', true),
                        false: fn ($query) => $query->where('status', false),
                    ),
                TrashedFilter::make()
                    ->label('Tempat Sampah'),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    EditAction::make()
                        ->label('Ubah')
                        ->visible(fn ($record) => $record->canBeManagedBy(auth()->user())),
                    DeleteAction::make()
                        ->label('Hapus')
                        ->visible(fn ($record) => $record->canBeManagedBy(auth()->user())),
                ])
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->addSelect(['level_sort' => \App\Models\Level::select('level_number')
                    ->whereColumn('levels.id', 'groups.level_id')
                    ->limit(1)
                ])
                ->orderBy('level_sort', 'desc')
                ->orderBy('name', 'asc')
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih'),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen Terpilih'),
                ])->label('Aksi Massal'),
            ]);
    }
}
