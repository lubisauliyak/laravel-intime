<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->locale('id')->diffForHumans())
                    ->description(fn (ActivityLog $record) => Carbon::parse($record->created_at)->format('d/m/Y H:i'))
                    ->sortable(),

                TextColumn::make('causer_name')
                    ->label('Pelaku')
                    ->searchable()
                    ->badge()
                    ->color(fn (ActivityLog $record) => $record->causer_name === 'Sistem' ? 'gray' : 'primary'),

                TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'created' => 'Dibuat',
                        'updated' => 'Diperbarui',
                        'deleted' => 'Dihapus',
                        'restored' => 'Dipulihkan',
                        default => ucfirst($state),
                    })
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('subject_type')
                    ->label('Modul')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => match (class_basename($state)) {
                        'Member' => 'Anggota',
                        'Meeting' => 'Pertemuan',
                        'Attendance' => 'Presensi',
                        'Group' => 'Kelompok',
                        'User' => 'Pengguna',
                        default => class_basename($state),
                    }),

                TextColumn::make('description')
                    ->label('Deskripsi Aktivitas')
                    ->searchable()
                    ->wrap()
                    ->weight(FontWeight::Medium),

                TextColumn::make('group.name')
                    ->label('Wilayah / Kelompok')
                    ->placeholder('Pusat / Semua')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('view_detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (ActivityLog $record) => $record->description)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl')
                    ->modalContent(fn (ActivityLog $record) => view('filament.resources.activity-logs.detail-modal', ['record' => $record])),
            ])
            ->headerActions([
                Action::make('prune_old_logs')
                    ->label('Bersihkan Log Lama')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->isSuperAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('Pembersihan Riwayat Log Lama')
                    ->modalDescription(function () {
                        $days = (int) env('ACTIVITY_LOG_RETENTION_DAYS', 30);
                        $count = ActivityLog::where('created_at', '<', now()->subDays($days))->count();
                        return "Ditemukan {$count} rekaman log yang berusia lebih dari {$days} hari. Apakah Anda yakin ingin menghapus data tersebut secara permanen?";
                    })
                    ->modalSubmitActionLabel('Ya, Bersihkan Sekarang')
                    ->action(function () {
                        $days = (int) env('ACTIVITY_LOG_RETENTION_DAYS', 30);
                        $count = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
                        Notification::make()
                            ->title('Berhasil Membersihkan Log')
                            ->body("Sebanyak {$count} rekaman aktivitas lama berhasil dibersihkan.")
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                Filter::make('rentang_tanggal')
                    ->form([
                        DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal'),
                        DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['dari_tanggal'], fn ($q) => $q->whereDate('created_at', '>=', $data['dari_tanggal']))
                            ->when($data['sampai_tanggal'], fn ($q) => $q->whereDate('created_at', '<=', $data['sampai_tanggal']));
                    }),

                SelectFilter::make('event')
                    ->label('Aksi')
                    ->options([
                        'created' => 'Dibuat',
                        'updated' => 'Diperbarui',
                        'deleted' => 'Dihapus',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('Modul')
                    ->options([
                        'App\Models\Member' => 'Anggota',
                        'App\Models\Meeting' => 'Pertemuan',
                        'App\Models\Attendance' => 'Presensi',
                        'App\Models\Group' => 'Kelompok',
                        'App\Models\User' => 'Pengguna',
                    ]),
            ]);
    }
}
