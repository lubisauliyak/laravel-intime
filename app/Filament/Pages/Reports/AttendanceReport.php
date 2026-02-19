<?php

namespace App\Filament\Pages\Reports;

use App\Exports\GlobalAttendanceReportExport;
use App\Models\Member;
use App\Models\Meeting;
use Filament\Actions\Action;
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
use Illuminate\Support\Carbon;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Presensi & Laporan';

    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Rekap Kehadiran';
    protected static ?string $title = 'Laporan Rekapitulasi Kehadiran';
    protected static ?string $slug = 'reports/attendance';

    protected string $view = 'filament.pages.reports.attendance-report';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->visible(fn () => auth()->user()->canExport())
                ->action(fn () => (new GlobalAttendanceReportExport(
                    $this->tableFilters, 
                    auth()->user()
                ))->download('Rekap-Kehadiran-' . now()->format('Y-m-d') . '.xlsx'))
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('age_group_id', 'ASC')
            ->query(function () {
                $user = auth()->user();
                $query = Member::query()
                    ->with(['group', 'ageGroup'])
                    ->where('status', true);
                
                if (!$user->isSuperAdmin()) {
                    if ($user->group_id) {
                        $descendantIds = $user->group->getAllDescendantIds();
                        $query->whereIn('group_id', $descendantIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
                
                return $query;
            })
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
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['total']),
                TextColumn::make('attended_count')
                    ->label('Hadir')
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['attended']),
                TextColumn::make('excused_count')
                    ->label('Izin / Sakit')
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['excused']),
                TextColumn::make('absent_count')
                    ->label('Tanpa Keterangan')
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['absent']),
                TextColumn::make('attendance_rate')
                    ->label('% Kehadiran')
                    ->state(function (Member $record): string {
                        $stats = $this->getMemberStats($record);
                        if ($stats['total'] === 0) return '0%';
                        return number_format(($stats['attended'] / $stats['total']) * 100, 1) . '%';
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
                    ->relationship('group', 'groups.name', function ($query) {
                        $user = auth()->user();
                        $query->join('levels', 'groups.level_id', '=', 'levels.id')
                            ->select('groups.*')
                            ->orderBy('levels.level_number', 'desc')
                            ->orderBy('groups.name', 'asc');

                        if (!$user->isSuperAdmin()) {
                            if ($user->group_id) {
                                $descendantIds = $user->group->getAllDescendantIds();
                                $query->whereIn('groups.id', $descendantIds);
                            } else {
                                $query->whereRaw('1 = 0');
                            }
                        }
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->preload()
                    ->searchable(),
                Filter::make('meeting_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->maxDate(now()),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal')
                            ->maxDate(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query;
                    })
            ]);
    }

    protected array $statsCache = [];

    protected function getMemberStats(Member $record): array
    {
        if (isset($this->statsCache[$record->id])) {
            return $this->statsCache[$record->id];
        }

        $filters = $this->tableFilters;
        $from = $filters['meeting_date']['from'] ?? null;
        $until = $filters['meeting_date']['until'] ?? null;

        // 1. Total targeted sessions
        $groupIds = array_merge([$record->group_id], $record->group->getAllAncestorIds());
        $total = Meeting::whereIn('group_id', $groupIds)
            ->where(function ($query) use ($record) {
                $query->where('target_gender', 'all')
                    ->orWhere('target_gender', $record->gender);
            })
            ->where(function ($query) use ($record) {
                $query->where(function ($q) {
                    $q->whereNull('target_age_groups')
                        ->orWhereJsonLength('target_age_groups', 0);
                });

                if ($record->ageGroup) {
                    $query->orWhereJsonContains('target_age_groups', $record->ageGroup->name);
                }
            })
            ->when($from, fn ($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('meeting_date', '<=', $until))
            ->whereDate('meeting_date', '>=', $record->created_at->toDateString())
            ->count();

        // 2. Attended count
        $attended = $record->attendances()
            ->where('status', 'hadir')
            ->when($from, fn ($q) => $q->whereDate('checkin_time', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('checkin_time', '<=', $until))
            ->count();

        // 3. Excused count
        $excused = $record->attendances()
            ->whereIn('status', ['izin', 'sakit'])
            ->when($from, fn ($q) => $q->whereDate('checkin_time', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('checkin_time', '<=', $until))
            ->count();

        return $this->statsCache[$record->id] = [
            'total' => $total,
            'attended' => $attended,
            'excused' => $excused,
            'absent' => max(0, $total - $attended - $excused),
        ];
    }
}
