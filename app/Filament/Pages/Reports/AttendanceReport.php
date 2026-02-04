<?php

namespace App\Filament\Pages\Reports;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Meeting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Rekap Kehadiran';
    protected static ?string $title = 'Laporan Rekapitulasi Kehadiran';
    protected static ?string $slug = 'reports/attendance';

    protected string $view = 'filament.pages.reports.attendance-report';

    public function table(Table $table): Table
    {
        return $table
            ->query(Member::query())
            ->columns([
                TextColumn::make('member_code')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Nama Anggota')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('group.name')
                    ->label('Grup')
                    ->sortable(),
                TextColumn::make('total_sessions')
                    ->label('Total Sesi')
                    ->state(function (Member $record): int {
                         return Meeting::where('group_id', $record->group_id)->count();
                    }),
                TextColumn::make('attended_count')
                    ->label('Hadir')
                    ->counts('attendances')
                    ->sortable(),
                TextColumn::make('attendance_rate')
                    ->label('% Kehadiran')
                    ->state(function (Member $record): string {
                        $total = Meeting::where('group_id', $record->group_id)->count();
                        if ($total === 0) return '0%';
                        $attended = $record->attendances_count ?? $record->attendances()->count();
                        return number_format(($attended / $total) * 100, 1) . '%';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        floatval($state) >= 80 => 'success',
                        floatval($state) >= 50 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('group_id')
                    ->label('Grup')
                    ->relationship('group', 'name')
                    ->preload()
                    ->searchable(),
                Filter::make('meeting_date')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['from'],
                            fn (Builder $query, $date): Builder => $query->whereHas('attendances', fn ($q) => $q->whereDate('checkin_time', '>=', $date)),
                        )->when(
                            $data['until'],
                            fn (Builder $query, $date): Builder => $query->whereHas('attendances', fn ($q) => $q->whereDate('checkin_time', '<=', $date)),
                        );
                    })
            ]);
    }
}
