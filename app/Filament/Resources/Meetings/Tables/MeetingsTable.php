<?php

namespace App\Filament\Resources\Meetings\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\ActionGroup;
use App\Models\Meeting;
use App\Exports\MeetingAttendanceExport;

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
                    ->dateTime('H:i'),
                TextColumn::make('checkin_open_time')
                    ->label('Presensi Buka')
                    ->dateTime('H:i')
                    ->placeholder('Jam dimulai')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('H:i'),
                TextColumn::make('target_gender')
                    ->label('L/P')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'all' => 'Semua',
                        'male' => 'L',
                        'female' => 'P',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),
                TextColumn::make('target_age_groups')
                    ->label('Kategori Usia')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return null;
                        return \App\Models\AgeGroup::whereIn('name', (array) $state)
                            ->orderBy('sort_order')
                            ->pluck('name')
                            ->toArray();
                    })
                    ->color(fn (string $state): string => match (true) {
                        str_contains(strtolower($state), 'pra remaja') => 'info',
                        str_contains(strtolower($state), 'remaja') => 'warning',
                        str_contains(strtolower($state), 'pra nikah') => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->visibleFrom('md'),
                TextColumn::make('deleted_at')
                    ->label('Dihapus')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat'),
                    EditAction::make()
                        ->label('Ubah')
                        ->visible(fn (Meeting $record) => auth()->user()->can('update', $record)),
                    Action::make('export_excel')
                        ->label('Export Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->visible(fn (Meeting $record) => auth()->user()->can('export', $record))
                        ->action(fn ($record) => (new MeetingAttendanceExport($record->id))->download("Kehadiran-{$record->name}-{$record->group->name}-" . $record->meeting_date->format('Y-m-d') . ".xlsx")),
                    Action::make('export_pdf')
                        ->label('Cetak PDF')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->visible(fn (Meeting $record) => auth()->user()->can('export', $record))
                        ->url(fn ($record) => route('meeting.report.pdf', $record))
                        ->openUrlInNewTab(),
                    Action::make('open_scanner')
                        ->label('Scanner Station')
                        ->icon('heroicon-o-qr-code')
                        ->color('emerald')
                        ->url(fn ($record) => route('scanner.live', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Meeting $record) => !$record->isExpired() && auth()->user()->can('View:ScanAttendance')),
                ])
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen'),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih'),
                ])->label('Aksi Massal'),
            ]);
    }
}
