<?php

namespace App\Filament\Resources\Meetings\RelationManagers;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PermissionAttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Daftar Perizinan';

    protected static string|BackedEnum|null $icon = 'heroicon-o-document-text';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->attendances()
            ->where('method', AttendanceMethod::SELF)
            ->whereIn('status', [AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn ($query) => $query
                    ->where('method', AttendanceMethod::SELF)
                    ->whereIn('status', [AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('member.member_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member.full_name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->color(fn (Attendance $record) => $record->member?->getMemberColor())
                    ->tooltip(fn (Attendance $record) => $record->member?->getMemberColorTooltip()),
                TextColumn::make('member.group.name')
                    ->label('Kelompok')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AttendanceStatus ? $state->getLabel() : $state)
                    ->color(fn ($state) => $state instanceof AttendanceStatus ? $state->getColor() : 'gray')
                    ->icon(fn ($state) => $state instanceof AttendanceStatus ? $state->getIcon() : null),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Tanggal & Waktu Izin')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('evidence_path')
                    ->label('Bukti')
                    ->formatStateUsing(fn ($state) => $state ? 'Ada' : '-')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->icon(fn ($state) => $state ? 'heroicon-o-paper-clip' : null),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->striped();
    }
}
