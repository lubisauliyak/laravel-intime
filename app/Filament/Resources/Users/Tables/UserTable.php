<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UserTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group.name')
                    ->label('Grup')
                    ->formatStateUsing(fn ($record) => $record->group?->full_name)
                    ->placeholder('Tanpa Grup')
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Hak Akses')
                    ->badge(),
                IconColumn::make('status')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('email_verified_at')
                    ->label('Terverifikasi')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Tempat Sampah'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Ubah')
                        ->hidden(fn ($record) => $record->trashed()),
                    DeleteAction::make()
                        ->label('Hapus')
                        ->disabled(fn ($record) => $record->id === auth()->id())
                        ->tooltip(fn ($record) => $record->id === auth()->id() ? 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.' : null),
                    RestoreAction::make()
                        ->label('Pulihkan'),
                    ForceDeleteAction::make()
                        ->label('Hapus Permanen')
                        ->disabled(fn ($record) => $record->id === auth()->id())
                        ->tooltip(fn ($record) => $record->id === auth()->id() ? 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.' : null),
                ]),
            ])
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
