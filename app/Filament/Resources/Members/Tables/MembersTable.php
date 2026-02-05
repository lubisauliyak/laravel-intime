<?php

namespace App\Filament\Resources\Members\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('qr_code_path')
                    ->label('QR')
                    ->square()
                    ->disk('public')
                    ->visibleFrom('md'),
                TextColumn::make('member_code')
                    ->label('ID Anggota')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable(),
                TextColumn::make('nick_name')
                    ->label('Panggilan')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...(\App\Models\Level::orderBy('level_number', 'desc')->get()->map(function ($level) {
                    if ($level->level_number === 1) {
                        return TextColumn::make('group.name')
                            ->label(ucwords(strtolower($level->name)))
                            ->sortable();
                    }
                    return TextColumn::make("level_{$level->level_number}")
                        ->label(ucwords(strtolower($level->name)))
                        ->getStateUsing(fn ($record) => $record->group?->getParentAtLevel($level->level_number)?->name)
                        ->placeholder('-')
                        ->toggleable(isToggledHiddenByDefault: true);
                })->toArray()),                
                TextColumn::make('birth_date')
                    ->label('Tgl Lahir')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('age')
                    ->label('Usia')
                    ->suffix(' Thn')
                    ->sortable(),
                TextColumn::make('ageGroup.name')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains(strtolower($state), 'anak') => 'info',
                        str_contains(strtolower($state), 'remaja') => 'warning',
                        str_contains(strtolower($state), 'dewasa') => 'success',
                        str_contains(strtolower($state), 'lansia') => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('gender')
                    ->label('L/P')
                    ->formatStateUsing(fn (string $state): string => $state === 'male' ? 'L' : 'P')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),                
                TextColumn::make('membership_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pengurus' => 'warning',
                        'anggota' => 'success',
                        default => 'gray',
                    })
                    ->visibleFrom('sm'),
                IconColumn::make('status')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
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
                \Filament\Actions\ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat'),
                    EditAction::make()
                        ->label('Ubah'),
                    DeleteAction::make()
                        ->label('Hapus'),
                    RestoreAction::make()
                        ->label('Pulihkan'),
                    ForceDeleteAction::make()
                        ->label('Hapus Permanen'),
                ])
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->orderByRaw("FIELD(gender, 'male', 'female')")
                ->orderBy('age', 'asc')
                ->orderBy('full_name', 'asc')
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
