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
use Filament\Schemas\Components\Utilities\Get;
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
                ->action(function () {
                    $filters = $this->tableFilters;
                    // Flatten location filter if exists
                    if (isset($filters['location'])) {
                        $filters['parent_id'] = $filters['location']['parent_id'] ?? null;
                        $filters['group_id'] = $filters['location']['group_id'] ?? null;
                    }

                    return (new GlobalAttendanceReportExport(
                        $filters, 
                        auth()->user()
                    ))->download('Rekap-Kehadiran-' . now()->format('Y-m-d') . '.xlsx');
                })
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('age_group_id', 'ASC')
            ->query(function () {
                $user = auth()->user();
                $query = Member::query()
                    ->select('members.*')
                    ->join('groups', 'members.group_id', '=', 'groups.id')
                    ->leftJoin('groups as parents', 'groups.parent_id', '=', 'parents.id')
                    ->with(['group', 'ageGroup', 'group.parent'])
                    ->where('members.status', true);
                
                if (!$user->isSuperAdmin()) {
                    if ($user->group_id) {
                        $descendantIds = $user->group->getAllDescendantIds();
                        $query->whereIn('members.group_id', $descendantIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }

                // Match sorting with Excel Export
                return $query->orderBy('parents.name', 'asc')
                    ->orderBy('groups.name', 'asc')
                    ->orderBy('members.gender', 'asc')
                    ->orderBy('members.member_code', 'asc');
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
                    ->label('Kelompok')
                    ->sortable(),
                TextColumn::make('total_sessions')
                    ->label('Pertemuan')
                    ->alignCenter()
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['total']),
                TextColumn::make('attended_count')
                    ->label('Hadir')
                    ->alignCenter()
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['attended']),
                TextColumn::make('excused_count')
                    ->label('Izin/Sakit')
                    ->alignCenter()
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['excused']),
                TextColumn::make('absent_count')
                    ->label('Tidak Hadir')
                    ->alignCenter()
                    ->state(fn (Member $record): int => $this->getMemberStats($record)['absent']),
                TextColumn::make('attendance_rate')
                    ->label('% Kehadiran')
                    ->alignCenter()
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
                Filter::make('location')
                    ->form([
                        Select::make('parent_id')
                            ->label('Desa')
                            ->options(function() {
                                $user = auth()->user();
                                $query = \App\Models\Group::query()
                                    ->with('level')
                                    ->where('status', true)
                                    ->whereHas('level', fn($q) => $q->where('name', 'DESA'))
                                    ->orderBy('name', 'asc');

                                if (!$user->isSuperAdmin()) {
                                    if ($user->group_id) {
                                        $relatedIds = array_merge(
                                            [$user->group_id],
                                            $user->group->getAllAncestorIds(),
                                            $user->group->getAllDescendantIds()
                                        );
                                        $query->whereIn('id', $relatedIds);
                                    } else {
                                        return [];
                                    }
                                }

                                return $query->get()->mapWithKeys(fn ($group) => [
                                    $group->id => "[{$group->level?->code}] {$group->name}",
                                ]);
                            })
                            ->live()
                            ->searchable(),
                        Select::make('group_id')
                            ->label('Kelompok')
                            ->options(function (Get $get) {
                                $parentId = $get('parent_id');
                                if (!$parentId) return [];
                                
                                $user = auth()->user();
                                $query = \App\Models\Group::query()
                                    ->with('level')
                                    ->where('status', true)
                                    ->where('parent_id', $parentId)
                                    ->whereHas('level', fn($q) => $q->where('name', 'KELOMPOK'))
                                    ->orderBy('name', 'asc');

                                if (!$user->isSuperAdmin()) {
                                    if ($user->group_id) {
                                        $descendantIds = $user->group->getAllDescendantIds();
                                        $query->whereIn('groups.id', $descendantIds);
                                    } else {
                                        return [];
                                    }
                                }

                                return $query->get()->mapWithKeys(fn ($group) => [
                                    $group->id => "[{$group->level?->code}] {$group->name}",
                                ]);
                            })
                            ->noOptionsMessage(fn (Get $get) => $get('parent_id') ? 'Tidak ada Kelompok di Desa ini' : 'Silakan pilih Desa terlebih dahulu')
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['group_id'])) {
                            $group = \App\Models\Group::find($data['group_id']);
                            if ($group) {
                                $query->whereIn('members.group_id', $group->getAllDescendantIds());
                            }
                        } elseif (!empty($data['parent_id'])) {
                             $desa = \App\Models\Group::find($data['parent_id']);
                             if ($desa) {
                                 $query->whereIn('members.group_id', $desa->getAllDescendantIds());
                             }
                        }
                    }),
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
                    $allAgeGroupsCount = \App\Models\AgeGroup::count();
                    $query->orWhere(function($subQ) use ($record, $allAgeGroupsCount) {
                        $subQ->whereJsonContains('target_age_groups', $record->ageGroup->name)
                            ->where(function($innerQ) use ($allAgeGroupsCount) {
                                // Only apply age filter if NOT all age groups are selected
                                $innerQ->whereRaw("JSON_LENGTH(target_age_groups) < ?", [$allAgeGroupsCount]);
                            });
                    });
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
