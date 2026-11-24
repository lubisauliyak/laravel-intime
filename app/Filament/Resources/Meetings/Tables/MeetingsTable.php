<?php

namespace App\Filament\Resources\Meetings\Tables;

use App\Models\Meeting;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeetingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('meeting_date', 'DESC')
            ->columns([
                TextColumn::make('meeting_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('group.name')
                    ->label('Grup')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Pertemuan')
                    ->searchable(),

                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->alignCenter()
                    ->dateTime('H:i')
                    ->toggleable(),

                TextColumn::make('checkin_open_time')
                    ->label('Buka Presensi')
                    ->alignCenter()
                    ->dateTime('H:i')
                    ->placeholder('Sesuai jam pertemuan dimulai')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('end_time')
                    ->label('Selesai')
                    ->alignCenter()
                    ->dateTime('H:i')
                    ->toggleable(),

                TextColumn::make('target_gender')
                    ->label('L/P')
                    ->alignCenter()
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\MeetingTargetGender ? $state->getLabel() : $state)
                    ->color(fn ($state) => $state instanceof \App\Enums\MeetingTargetGender ? $state->getColor() : 'gray')
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('target_age_groups')
                    ->label('Kategori Usia')
                    ->badge()
                    ->state(function (Meeting $record): array {
                        $state = $record->target_age_groups;
                        if (empty($state)) {
                            return ['SEMUA'];
                        }

                        return \App\Models\AgeGroup::whereIn('name', (array) $state)
                            ->orderBy('sort_order')
                            ->pluck('name')
                            ->toArray();
                    })
                    ->color(fn ($state) => match (true) {
                        empty($state) || $state === 'Semua' => 'gray',
                        default => \App\Models\AgeGroup::getColorMapping()[(string) $state] ?? 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat'),
                    EditAction::make()
                        ->label('Ubah')
                        ->visible(fn (Meeting $record) => auth()->user()->can('update', $record)),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, DeleteBulkAction $action) {
                            $allowedRecords = $records->filter(fn ($record) => auth()->user()->can('delete', $record));
                            $allowedRecords->each(fn ($record) => $record->delete());

                            if ($allowedRecords->count() < $records->count()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa pertemuan gagal dihapus karena bukan bagian dari wilayah Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, ForceDeleteBulkAction $action) {
                            $allowedRecords = $records->filter(fn ($record) => auth()->user()->can('forceDelete', $record));
                            $allowedRecords->each(fn ($record) => $record->forceDelete());

                            if ($allowedRecords->count() < $records->count()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa pertemuan gagal dihapus permanen karena bukan bagian dari wilayah Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, RestoreBulkAction $action) {
                            $allowedRecords = $records->filter(fn ($record) => auth()->user()->can('restore', $record));
                            $allowedRecords->each(fn ($record) => $record->restore());

                            if ($allowedRecords->count() < $records->count()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa pertemuan gagal dipulihkan karena bukan bagian dari wilayah Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                ])->label('Aksi Massal'),
            ]);
    }
}
