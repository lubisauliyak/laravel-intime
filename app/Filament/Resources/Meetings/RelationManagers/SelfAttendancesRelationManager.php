<?php

namespace App\Filament\Resources\Meetings\RelationManagers;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class SelfAttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Daftar Presensi Mandiri';

    protected static string|BackedEnum|null $icon = 'heroicon-o-check-circle';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->attendances()
            ->where('method', AttendanceMethod::SELF)
            ->where('status', AttendanceStatus::HADIR)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn ($query) => $query
                    ->where('method', AttendanceMethod::SELF)
                    ->where('status', AttendanceStatus::HADIR)
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
                    ->label('Tanggal & Waktu Presensi')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                ImageColumn::make('evidence_path')
                    ->label('Selfie')
                    ->disk('public')
                    ->width(32)
                    ->height(32)
                    ->action(
                        Action::make('viewSelfie')
                            ->modalHeading('Foto Selfie')
                            ->modalWidth('md')
                            ->modalContent(fn (Attendance $record) => $record->evidence_path
                                ? new HtmlString('<div class="aspect-[3/4] w-full"><img src="'.Storage::url($record->evidence_path).'" class="w-full h-full object-cover rounded-lg" /></div>')
                                : null
                            )
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                    ),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->striped();
    }
}
