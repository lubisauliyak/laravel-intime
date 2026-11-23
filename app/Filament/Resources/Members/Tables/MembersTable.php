<?php

namespace App\Filament\Resources\Members\Tables;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use App\Models\Member;
use App\Models\AgeGroup;
use App\Models\Level;
use App\Enums\Gender;
use App\Enums\MembershipType;
use App\Exports\MembersRawExport;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Toggle;
use ZipArchive;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('age_group_id', 'ASC')
            ->columns([
                ImageColumn::make('profile_photo_path')
                    ->label('Foto')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn($record) => "https://ui-avatars.com/api/?name=" . urlencode($record->full_name) . "&color=FFFFFF&background=111827")
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('member_code')
                    ->label('ID Anggota')
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->color(fn(Member $record): ?string => $record->getMemberColor())
                    ->tooltip(fn(Member $record): ?string => $record->getMemberColorTooltip()),
                TextColumn::make('nick_name')
                    ->label('Panggilan')
                    ->toggleable(isToggledHiddenByDefault: true),
                ...(static::getDynamicLevelColumns()),
                TextColumn::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('age')
                    ->label('Usia')
                    ->suffix(' Thn')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ageGroup.name')
                    ->label('Kategori Usia')
                    ->placeholder('Tanpa Kategori')
                    ->alignCenter()
                    ->badge()
                    ->color(fn($state): string => AgeGroup::getColorMapping()[(string) $state] ?? 'gray')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('gender')
                    ->label('L/P')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state instanceof Gender ? $state->getCode() : (Gender::tryFrom($state)?->getCode() ?? $state))
                    ->visibleFrom('sm'),
                TextColumn::make('membership_type')
                    ->label('Kepengurusan')
                    ->badge(fn(Member $record) => ($record->membership_type instanceof MembershipType ? $record->membership_type : MembershipType::tryFrom($record->membership_type)) === MembershipType::PENGURUS && $record->positions->isNotEmpty())
                    ->color(fn(Member $record) => ($record->membership_type instanceof MembershipType ? $record->membership_type : MembershipType::tryFrom($record->membership_type)) === MembershipType::PENGURUS ? 'info' : 'gray')
                    ->formatStateUsing(function (Member $record) {
                        $isPengurus = ($record->membership_type instanceof MembershipType ? $record->membership_type : MembershipType::tryFrom($record->membership_type)) === MembershipType::PENGURUS;
                        if (!$isPengurus || $record->positions->isEmpty()) {
                            return '-';
                        }

                        $levels = $record->positions
                            ->map(fn($pos) => $pos->group?->level)
                            ->filter()
                            ->sortBy('level_number')
                            ->map(fn($level) => ($level->name))
                            ->unique()
                            ->values();

                        return $levels->isNotEmpty() ? $levels->implode(', ') : 'Pengurus';
                    })
                    ->tooltip(function (Member $record) {
                        if ($record->positions->isEmpty()) {
                            return null;
                        }
                        return $record->positions->map(function ($pos) {
                            $parts = array_filter([
                                $pos->position_name,
                                $pos->category?->name,
                                $pos->group?->name,
                            ]);
                            return Str::title(strtolower(implode(' ', $parts)));
                        })->implode(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('sm'),
                IconColumn::make('is_permanent')
                    ->label('Verifikasi')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('status')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter()
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
                SelectFilter::make('group_id')
                    ->label('Kelompok')
                    ->multiple()
                    ->relationship(
                        'group',
                        'groups.name',
                        fn($query) => $query
                            ->whereHas('level', fn($q) => $q->where('level_number', 1))
                            ->leftJoin('groups as desa', 'groups.parent_id', '=', 'desa.id')
                            ->select('groups.*')
                            ->orderBy('desa.name', 'asc')
                            ->orderBy('groups.name', 'asc')
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('age_group_id')
                    ->label('Kategori Usia')
                    ->multiple()
                    ->options(function () {
                        $options = AgeGroup::orderBy('sort_order')->pluck('name', 'id')->toArray();
                        $options['null'] = 'Tanpa Kategori Usia';
                        return $options;
                    })
                    ->query(function ($query, array $data) {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        $values = $data['values'];
                        $hasNull = in_array('null', $values);
                        $normalIds = array_diff($values, ['null']);

                        return $query->where(function ($q) use ($hasNull, $normalIds) {
                            if ($hasNull && !empty($normalIds)) {
                                $q->whereNull('age_group_id')
                                    ->orWhereIn('age_group_id', $normalIds);
                            } elseif ($hasNull) {
                                $q->whereNull('age_group_id');
                            } else {
                                $q->whereIn('age_group_id', $normalIds);
                            }
                        });
                    }),
                SelectFilter::make('gender')
                    ->label('L/P')
                    ->options(Gender::class),
                SelectFilter::make('membership_type')
                    ->label('Kepengurusan')
                    ->options([
                        MembershipType::ANGGOTA->value => 'Anggota Biasa',
                        MembershipType::PENGURUS->value => 'Pengurus',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat'),
                    Action::make('download_kta')
                        ->label('Unduh KTA')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn(Member $record) => response()->download(Storage::disk('public')->path($record->membership_card_photo_path), "KTA_{$record->member_code}.png"))
                        ->visible(fn(Member $record) => auth()->user()->can('downloadKta', $record))
                        ->disabled(fn(Member $record) => empty($record->membership_card_photo_path) || !Storage::disk('public')->exists($record->membership_card_photo_path))
                        ->tooltip(fn(Member $record) => empty($record->membership_card_photo_path) || !Storage::disk('public')->exists($record->membership_card_photo_path) ? 'KTA belum dibuat untuk anggota ini. Silakan buat KTA terlebih dahulu.' : null),
                    Action::make('make_permanent')
                        ->label('Setujui Anggota')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Persetujuan Keanggotaan')
                        ->modalDescription('Apakah Anda yakin ingin menyetujui dan memverifikasi keanggotaan permanen anggota ini?')
                        ->form([
                            Toggle::make('set_as_target')
                                ->label('Jadikan Sebagai Target Kehadiran Wajib')
                                ->helperText('Jika diaktifkan, anggota ini otomatis menjadi target kehadiran dan dihitung alpa bila tidak hadir pada pertemuan berikutnya.')
                                ->default(true),
                        ])
                        ->action(function (Member $record, array $data) {
                            $updateData = ['is_permanent' => true];
                            if (!empty($data['set_as_target'])) {
                                $updateData['status'] = true;
                            }
                            $record->update($updateData);

                            Notification::make()
                                ->success()
                                ->title('Anggota Berhasil Disetujui')
                                ->body("Keanggotaan {$record->full_name} kini berstatus permanen.")
                                ->send();
                        })
                        ->visible(fn(Member $record) => auth()->user()->can('makePermanent', $record))
                        ->disabled(fn(Member $record) => (bool) $record->is_permanent)
                        ->tooltip(fn(Member $record) => $record->is_permanent ? 'Anggota ini sudah berstatus permanen.' : null),
                    Action::make('download_qr')
                        ->label('Unduh QR')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn(Member $record) => response()->download(Storage::disk('public')->path($record->qr_code_path), "{$record->member_code}.png"))
                        ->visible(fn(Member $record) => auth()->user()->can('downloadQr', $record))
                        ->disabled(fn(Member $record) => empty($record->qr_code_path) || !Storage::disk('public')->exists($record->qr_code_path))
                        ->tooltip(fn(Member $record) => empty($record->qr_code_path) || !Storage::disk('public')->exists($record->qr_code_path) ? 'Kode QR belum tersedia untuk anggota ini.' : null),
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
            ->modifyQueryUsing(
                fn($query) => $query
                    ->with(['positions.category', 'positions.group.level'])
                    ->join('groups as kelompok', 'members.group_id', '=', 'kelompok.id')
                    ->leftJoin('groups as desa', 'kelompok.parent_id', '=', 'desa.id')
                    ->leftJoin('groups as daerah', 'desa.parent_id', '=', 'daerah.id')
                    ->select('members.*')
                    ->orderBy('daerah.name', 'asc')
                    ->orderBy('desa.name', 'asc')
                    ->orderBy('kelompok.name', 'asc')
                    ->orderByRaw("FIELD(gender, '" . Gender::MALE->value . "', '" . Gender::FEMALE->value . "')")
                    ->orderBy('members.member_code', 'asc')
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_selected')
                        ->label('Ekspor Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn() => auth()->user()?->can('export', Member::class) ?? false)
                        ->action(function (Collection $records) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('export', $record));

                            if ($allowedRecords->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Tidak Ada Data yang Diizinkan')
                                    ->body('Seluruh anggota yang dipilih berada di luar wilayah wewenang Anda.')
                                    ->send();
                                return;
                            }

                            $skippedCount = $records->count() - $allowedRecords->count();
                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Data Dilewati')
                                    ->body("{$skippedCount} anggota dilewati karena di luar wilayah wewenang Anda.")
                                    ->send();
                            }

                            $filename = 'Data-Anggota-Terpilih-' . now()->format('Ymd_His') . '.xlsx';

                            return (new MembersRawExport($allowedRecords->pluck('id')->toArray()))
                                ->download($filename);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('generate_card_bulk')
                        ->label('Buat KTA Terpilih')
                        ->icon('heroicon-o-identification')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('GenerateCard:Member'))
                        ->form([
                            \Filament\Forms\Components\Select::make('template_id')
                                ->label('Pilih Template')
                                ->options(\App\Models\CardTemplate::where('is_active', true)->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $template = \App\Models\CardTemplate::find($data['template_id']);
                            if ($template) {
                                $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('generateCard', $record));
                                $skippedCount = $records->count() - $allowedRecords->count();

                                $service = new \App\Services\CardGeneratorService();
                                foreach ($allowedRecords as $record) {
                                    $service->generate($template, $record);
                                }

                                if ($skippedCount > 0) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Pembuatan KTA Selesai dengan Catatan')
                                        ->body("{$allowedRecords->count()} KTA berhasil dibuat. {$skippedCount} anggota dilewati karena di luar wilayah wewenang Anda.")
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->success()
                                        ->title("{$allowedRecords->count()} KTA Berhasil Dibuat")
                                        ->send();
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('make_permanent_bulk')
                        ->label('Setujui Terpilih')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->modalHeading('Persetujuan Massal Anggota')
                        ->modalDescription('Setujui seluruh anggota terpilih yang belum berstatus permanen.')
                        ->form([
                            Toggle::make('set_as_target')
                                ->label('Jadikan Sebagai Target Kehadiran Wajib')
                                ->helperText('Aktifkan agar seluruh anggota yang disetujui otomatis menjadi target kehadiran.')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('makePermanent', $record) && !$record->is_permanent);
                            $skippedCount = $records->count() - $allowedRecords->count();

                            $updateData = ['is_permanent' => true];
                            if (!empty($data['set_as_target'])) {
                                $updateData['status'] = true;
                            }

                            $allowedRecords->each->update($updateData);

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Verifikasi Selesai dengan Catatan')
                                    ->body("{$allowedRecords->count()} anggota berhasil disetujui. {$skippedCount} anggota dilewati (sudah permanen atau di luar wewenang).")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title("{$allowedRecords->count()} Anggota Berhasil Disetujui")
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate_bulk')
                        ->label('Nonaktifkan Terpilih')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->visible(fn() => auth()->user()->can('Deactivate:Member'))
                        ->action(function (Collection $records) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('deactivate', $record) && $record->status);
                            $skippedCount = $records->count() - $allowedRecords->count();

                            $allowedRecords->each->update(['status' => false]);

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Penonaktifan Selesai dengan Catatan')
                                    ->body("{$allowedRecords->count()} anggota berhasil dinonaktifkan. {$skippedCount} anggota dilewati (sudah nonaktif atau di luar wewenang).")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title("{$allowedRecords->count()} Anggota Berhasil Dinonaktifkan")
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('download_qrs')
                        ->label('Unduh QR Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('DownloadQr:Member'))
                        ->action(function (Collection $records) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('downloadQr', $record));
                            $zipName = 'QR_Codes_' . now()->format('Ymd_His') . '.zip';
                            $zipPath = storage_path('app/public/' . $zipName);
                            $zip = new ZipArchive;

                            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                                foreach ($allowedRecords as $record) {
                                    if ($record->qr_code_path && Storage::disk('public')->exists($record->qr_code_path)) {
                                        $filePath = Storage::disk('public')->path($record->qr_code_path);
                                        $zip->addFile($filePath, "{$record->member_code}_{$record->full_name}.png");
                                    }
                                }
                                $zip->close();
                            }

                            return response()->download($zipPath)->deleteFileAfterSend(true);
                        }),
                    BulkAction::make('download_ktas')
                        ->label('Unduh KTA Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn() => auth()->user()->can('DownloadKta:Member'))
                        ->action(function (Collection $records) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('downloadKta', $record));
                            $zipName = 'KTA_Codes_' . now()->format('Ymd_His') . '.zip';
                            $zipPath = storage_path('app/public/' . $zipName);
                            $zip = new ZipArchive;

                            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                                foreach ($allowedRecords as $record) {
                                    if ($record->membership_card_photo_path && Storage::disk('public')->exists($record->membership_card_photo_path)) {
                                        $filePath = Storage::disk('public')->path($record->membership_card_photo_path);
                                        $zip->addFile($filePath, "KTA_{$record->member_code}_{$record->full_name}.png");
                                    }
                                }
                                $zip->close();
                            }

                            return response()->download($zipPath)->deleteFileAfterSend(true);
                        }),
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->action(function (Collection $records, DeleteBulkAction $action) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('delete', $record));
                            $allowedRecords->each->delete();

                            if ($allowedRecords->count() < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa anggota gagal dihapus karena bukan bagian dari wilayah wewenang Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih')
                        ->action(function (Collection $records, RestoreBulkAction $action) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('restore', $record));
                            $allowedRecords->each->restore();

                            if ($allowedRecords->count() < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa anggota gagal dipulihkan karena bukan bagian dari wilayah wewenang Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen Terpilih')
                        ->action(function (Collection $records, ForceDeleteBulkAction $action) {
                            $allowedRecords = $records->filter(fn(Member $record) => auth()->user()->can('forceDelete', $record));
                            $allowedRecords->each->forceDelete();

                            if ($allowedRecords->count() < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Ditolak')
                                    ->body('Beberapa anggota gagal dihapus permanen karena bukan bagian dari wilayah wewenang Anda.')
                                    ->send();
                            } else {
                                $action->success();
                            }
                        }),
                ])->label('Aksi Massal'),
            ]);
    }

    protected static function getDynamicLevelColumns(): array
    {
        return Level::orderBy('level_number', 'desc')->get()->map(function ($level) {
            if ($level->level_number === 1) {
                return TextColumn::make('group.name')
                    ->label(ucwords(strtolower($level->name)));
            }

            return TextColumn::make("level_{$level->level_number}")
                ->label(ucwords(strtolower($level->name)))
                ->getStateUsing(fn($record) => $record->group?->getParentAtLevel($level->level_number)?->name)
                ->placeholder('-')
                ->toggleable(isToggledHiddenByDefault: true);
        })->toArray();
    }
}
