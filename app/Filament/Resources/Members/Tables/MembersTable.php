<?php

namespace App\Filament\Resources\Members\Tables;

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
use Filament\Tables\Filters\TrashedFilter;
use App\Models\Member;
use App\Models\AgeGroup;
use App\Models\Level;
use Filament\Actions\ActionGroup;
use ZipArchive;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('age_group_id', 'ASC')
            ->columns([
                TextColumn::make('member_code')
                    ->label('ID Anggota')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('nick_name')
                    ->label('Panggilan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...(static::getDynamicLevelColumns()),
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
                        str_contains(strtolower($state), 'pra remaja') => 'info',
                        str_contains(strtolower($state), 'remaja') => 'warning',
                        str_contains(strtolower($state), 'pra nikah') => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('gender')
                    ->label('L/P')
                    ->formatStateUsing(fn (string $state): string => $state === 'male' ? 'L' : 'P')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('sm'),
                TextColumn::make('membership_type')
                    ->label('Kepengurusan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'anggota' => 'gray',
                        default => 'primary',
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
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
                SelectFilter::make('group_id')
                    ->label('Kelompok')
                    ->multiple()
                    ->relationship(
                        'group', 
                        'groups.name',
                        fn ($query) => $query
                            ->whereHas('level', fn ($q) => $q->where('level_number', 1))
                            ->leftJoin('groups as desa', 'groups.parent_id', '=', 'desa.id')
                            ->select('groups.*')
                            ->orderBy('desa.name', 'asc')
                            ->orderBy('groups.name', 'asc')
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('age_group_id')
                    ->label('Kategori Usia')
                    ->multiple()
                    ->relationship('ageGroup', 'name')
                    ->preload(),
                SelectFilter::make('gender')
                    ->label('L/P')
                    ->options([
                        'male' => 'Laki-laki',
                        'female' => 'Perempuan',
                    ]),
                SelectFilter::make('membership_type')
                    ->label('Keanggotaan')
                    ->options([
                        'anggota' => 'Anggota',
                        'pengurus' => 'Pengurus',
                    ]),
                TrashedFilter::make()
                    ->label('Tempat Sampah'),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat'),
                    Action::make('download_qr')
                        ->label('Unduh QR')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn (Member $record) => Storage::disk('public')->download($record->qr_code_path, "{$record->member_code}.png"))
                        ->visible(fn (Member $record) => $record->qr_code_path && Storage::disk('public')->exists($record->qr_code_path)),
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
                ->join('groups as kelompok', 'members.group_id', '=', 'kelompok.id')
                ->leftJoin('groups as desa', 'kelompok.parent_id', '=', 'desa.id')
                ->leftJoin('groups as daerah', 'desa.parent_id', '=', 'daerah.id')
                ->select('members.*')
                ->orderBy('daerah.name', 'asc')
                ->orderBy('desa.name', 'asc')
                ->orderBy('kelompok.name', 'asc')
                ->orderByRaw("FIELD(gender, 'male', 'female')")
                ->orderBy('members.member_code', 'asc')
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('download_qrs')
                        ->label('Unduh QR Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $zipName = 'QR_Codes_' . now()->format('Ymd_His') . '.zip';
                            $zipPath = storage_path('app/public/' . $zipName);
                            $zip = new ZipArchive;
                            
                            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                                foreach ($records as $record) {
                                    if ($record->qr_code_path && Storage::disk('public')->exists($record->qr_code_path)) {
                                        $filePath = Storage::disk('public')->path($record->qr_code_path);
                                        $zip->addFile($filePath, "{$record->member_code}_{$record->full_name}.png");
                                    }
                                }
                                $zip->close();
                            }
                            
                            return response()->download($zipPath)->deleteFileAfterSend(true);
                        }),
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih'),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen Terpilih'),
                ])->label('Aksi Massal'),
            ]);
    }

    protected static function getDynamicLevelColumns(): array
    {
        return Level::orderBy('level_number', 'desc')->get()->map(function ($level) {
            if ($level->level_number === 1) {
                return TextColumn::make('group.name')
                    ->label(ucwords(strtolower($level->name)))
                    ->sortable();
            }
            
            return TextColumn::make("level_{$level->level_number}")
                ->label(ucwords(strtolower($level->name)))
                ->getStateUsing(fn ($record) => $record->group?->getParentAtLevel($level->level_number)?->name)
                ->placeholder('-')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true);
        })->toArray();
    }
}
