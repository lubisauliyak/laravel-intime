<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\GroupResource;
use App\Exports\GroupMemberActivityExport;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\MeetingTargetGender;
use App\Models\AgeGroup;
use App\Models\Level;
use App\Models\Member;
use App\Models\Meeting;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GroupMemberActivity extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = GroupResource::class;

    protected string $view = 'filament.resources.groups.pages.group-member-activity';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        return $record
            ? auth()->user()->can('viewMemberActivity', $record)
            : auth()->user()->can('ViewMemberActivity:Group');
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->tableFilters['date_range'] = [
            'date_from' => now()->subMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Ekspor Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->hiddenLabel()
                ->outlined()
                ->color('success')
                ->tooltip('Ekspor Rekapitulasi Kehadiran (Excel)')
                ->visible(fn() => auth()->user()?->can('export', $this->getRecord()) ?? false)
                ->action(function () {
                    $memberIds = $this->getFilteredTableQuery()->pluck('members.id')->toArray();

                    if (empty($memberIds)) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Tidak Ada Data')
                            ->body('Tidak ditemukan data anggota yang dapat diekspor.')
                            ->send();
                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Ekspor Dimulai')
                        ->body('File rekapitulasi kehadiran sedang dipersiapkan dan akan segera diunduh.')
                        ->send();

                    $rawFilename = 'Aktivitas-Anggota-' . $this->getRecord()->name . '-' . now()->format('Y-m-d') . '.xlsx';
                    $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $rawFilename);
                    $filename = preg_replace('/-+/', '-', $filename);

                    return (new GroupMemberActivityExport(
                        $this->getRecord(),
                        $this->tableFilters,
                        $memberIds
                    ))->download($filename);
                }),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Aktifitas Anggota';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public function getTitle(): string
    {
        return 'Aktifitas Anggota';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();
        $group = $this->getRecord();
        return [
            $resource::getUrl() => $resource::getNavigationLabel(),
            $resource::getUrl('view', ['record' => $group]) => $group->name,
            'Aktifitas Anggota',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters([
                SelectFilter::make('level')
                    ->label('Tingkat Pertemuan')
                    ->options(fn() => Level::orderBy('level_number', 'desc')->pluck('name', 'id'))
                    ->query(fn($query) => $query),
                SelectFilter::make('age_group')
                    ->label('Kategori Usia')
                    ->multiple()
                    ->options(fn() => AgeGroup::orderBy('name')->pluck('name', 'id'))
                    ->query(fn($query, $state) => $query->when($state['values'] ?? null, fn($q) => $q->whereIn('age_group_id', $state['values']))),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal')
                            ->default(now()->subMonth()->format('Y-m-d')),
                        DatePicker::make('date_to')
                            ->label('Sampai Tanggal')
                            ->default(now()->format('Y-m-d')),
                    ]),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('view_meeting_history')
                        ->label('Riwayat Pertemuan Anggota')
                        ->icon('heroicon-m-clock')
                        ->color('grey')
                        ->url(function (Member $record) {
                            $url = \App\Filament\Resources\Members\Pages\MemberMeetingHistory::getUrl(['record' => $record->uid]);

                            $filters = $this->tableFilters ?? [];
                            $dateFrom = data_get($filters, 'date_range.date_from');
                            $dateTo = data_get($filters, 'date_range.date_to');

                            $query = [];
                            if ($dateFrom)
                                $query['date_from'] = $dateFrom;
                            if ($dateTo)
                                $query['date_to'] = $dateTo;

                            if (!empty($query)) {
                                $url .= '?' . urldecode(http_build_query($query));
                            }

                            return $url;
                        })
                        ->openUrlInNewTab()
                        ->visible(fn () => auth()->user()->can('viewMemberMeetingHistory', $this->getRecord())),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Menu Aksi'),
            ])
            ->defaultSort('full_name');
    }

    protected array $statsCache = [];
    protected ?Collection $cachedMeetings = null;
    protected ?string $cachedMeetingsFilterKey = null;
    protected ?int $allAgeGroupsCount = null;

    protected function getAgeGroupsCount(): int
    {
        return $this->allAgeGroupsCount ??= AgeGroup::count();
    }

    protected function getRelevantMeetings(): Collection
    {
        $filters = $this->tableFilters ?? [];
        $from = data_get($filters, 'date_range.date_from');
        $until = data_get($filters, 'date_range.date_to');

        $levelValue = $filters['level'] ?? null;
        $levelId = null;
        if (is_array($levelValue)) {
            $levelId = $levelValue['value'] ?? $levelValue['is'] ?? $levelValue[0] ?? null;
        } elseif ($levelValue !== null && $levelValue !== '') {
            $levelId = $levelValue;
        }

        $filterKey = md5(json_encode([$from, $until, $levelId]));
        if ($this->cachedMeetings !== null && $this->cachedMeetingsFilterKey === $filterKey) {
            return $this->cachedMeetings;
        }

        $group = $this->getRecord();
        $relevantGroupIds = array_unique(array_merge(
            [$group->id],
            $group->getAllAncestorIds(),
            $group->getAllDescendantIds()
        ));

        $this->cachedMeetings = Meeting::query()
            ->with('group')
            ->whereIn('group_id', $relevantGroupIds)
            ->when($levelId, fn($q) => $q->whereHas('group', fn($g) => $g->where('level_id', $levelId)))
            ->when($from, fn($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($until, fn($q) => $q->whereDate('meeting_date', '<=', $until))
            ->orderBy('meeting_date', 'asc')
            ->get();

        $this->cachedMeetingsFilterKey = $filterKey;

        return $this->cachedMeetings;
    }

    protected function isMemberTargetForMeeting(Member $member, Meeting $meeting): bool
    {
        if (!$member->status) {
            return false;
        }

        // 1. Group check: Meeting's group must be the member's group or one of its ancestors
        $memberGroupAncestors = array_merge([$member->group_id], $member->group->getAllAncestorIds());
        if (!in_array($meeting->group_id, $memberGroupAncestors)) {
            return false;
        }

        // 2. Date check: Member must have been created on or before meeting date
        if ($member->created_at->startOfDay()->gt(Carbon::parse($meeting->meeting_date)->startOfDay())) {
            return false;
        }

        // 3. Gender check
        $targetGender = $meeting->target_gender instanceof MeetingTargetGender ? $meeting->target_gender->value : $meeting->target_gender;
        $memberGender = $member->gender instanceof Gender ? $member->gender->value : $member->gender;
        if ($targetGender !== 'all' && $targetGender !== $memberGender) {
            return false;
        }

        // 4. Age Group check
        $targetAgeGroups = $meeting->target_age_groups;
        if (!empty($targetAgeGroups)) {
            $targetAgeArray = (array) $targetAgeGroups;
            $allCount = $this->getAgeGroupsCount();
            if (count($targetAgeArray) < $allCount) {
                if (!$member->ageGroup || !in_array($member->ageGroup->name, $targetAgeArray, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function getMemberStats(Member $record): array
    {
        if (isset($this->statsCache[$record->id])) {
            return $this->statsCache[$record->id];
        }

        $relevantMeetings = $this->getRelevantMeetings();

        // 1. Calculate targeted meetings in-memory
        $targetedMeetings = $record->status
            ? $relevantMeetings->filter(fn($meeting) => $this->isMemberTargetForMeeting($record, $meeting))
            : collect();

        $targetedTotal = $targetedMeetings->count();

        // 2. Attendances are eager-loaded via relation
        $attendances = $record->attendances;

        // Total = max of targeted meetings vs distinct meetings with any attendance record
        $attendedMeetingIds = $attendances->pluck('meeting_id')->unique()->count();
        $total = max($targetedTotal, $attendedMeetingIds);

        $hadir = 0;
        $telat = 0;
        $izin = 0;
        $sakit = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->status === AttendanceStatus::HADIR) {
                $hadir++;
                if ($attendance->meeting && $attendance->checkin_time > Carbon::parse($attendance->meeting->meeting_date)->format('Y-m-d') . ' ' . Carbon::parse($attendance->meeting->start_time)->format('H:i:s')) {
                    $telat++;
                }
            } elseif ($attendance->status === AttendanceStatus::IZIN) {
                $izin++;
            } elseif ($attendance->status === AttendanceStatus::SAKIT) {
                $sakit++;
            }
        }

        $alfa = max(0, $total - $hadir - $izin - $sakit);

        // Last 5 meetings: take the last 5 targeted meetings ordered by meeting_date desc, then asc for display
        $lastFiveMeetings = $targetedMeetings->sortByDesc('meeting_date')->take(5)->sortBy('meeting_date')->values();
        $lastFive = [];

        foreach ($lastFiveMeetings as $m) {
            $att = $attendances->firstWhere('meeting_id', $m->id);
            if (!$att) {
                $lastFive[] = 'A';
            } else {
                if ($att->status === AttendanceStatus::HADIR) {
                    if ($att->checkin_time > Carbon::parse($m->meeting_date)->format('Y-m-d') . ' ' . Carbon::parse($m->start_time)->format('H:i:s')) {
                        $lastFive[] = 'T';
                    } else {
                        $lastFive[] = 'H';
                    }
                } elseif ($att->status === AttendanceStatus::IZIN) {
                    $lastFive[] = 'I';
                } elseif ($att->status === AttendanceStatus::SAKIT) {
                    $lastFive[] = 'S';
                } elseif ($att->status === AttendanceStatus::ALFA) {
                    $lastFive[] = 'A';
                } else {
                    $lastFive[] = '-';
                }
            }
        }

        // Pad left with '-' if less than 5 meetings
        while (count($lastFive) < 5) {
            array_unshift($lastFive, '-');
        }

        $percentage = $total > 0 ? round(($hadir / $total) * 100, 1) : 0;

        return $this->statsCache[$record->id] = [
            'total' => $total,
            'hadir' => $hadir,
            'telat' => $telat,
            'izin' => $izin,
            'sakit' => $sakit,
            'alfa' => $alfa,
            'percentage' => $percentage,
            'last_five' => implode(' ', $lastFive),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $group = $this->getRecord();
        $descendantIds = $group->getAllDescendantIds();

        $filters = $this->tableFilters ?? [];
        $from = data_get($filters, 'date_range.date_from');
        $until = data_get($filters, 'date_range.date_to');

        $levelValue = $filters['level'] ?? null;
        $levelId = null;
        if (is_array($levelValue)) {
            $levelId = $levelValue['value'] ?? $levelValue['is'] ?? $levelValue[0] ?? null;
        } elseif ($levelValue !== null && $levelValue !== '') {
            $levelId = $levelValue;
        }

        return Member::query()
            ->whereIn('members.group_id', $descendantIds)
            ->with([
                'group',
                'ageGroup',
                'attendances' => function ($q) use ($levelId, $from, $until) {
                    $q->with('meeting.group')
                        ->when($levelId, fn($mq) => $mq->whereHas('meeting.group', fn($g) => $g->where('level_id', $levelId)))
                        ->when($from, fn($mq) => $mq->whereDate('checkin_time', '>=', $from))
                        ->when($until, fn($mq) => $mq->whereDate('checkin_time', '<=', $until));
                },
            ]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('member_code')
                ->label('ID Anggota')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('full_name')
                ->label('Nama Anggota')
                ->searchable()
                ->color(fn(Member $record): ?string => $record->getMemberColor())
                ->tooltip(fn(Member $record): ?string => $record->getMemberColorTooltip()),
            TextColumn::make('total_targeted')
                ->label('Pertemuan')
                ->state(fn(Member $record): int => $this->getMemberStats($record)['total'])
                ->badge()
                ->color('gray')
                ->alignCenter(),
            TextColumn::make('hadir_count')
                ->label('Hadir')
                ->state(fn(Member $record): int => $this->getMemberStats($record)['hadir'])
                ->badge()
                ->color('success')
                ->alignCenter(),
            TextColumn::make('telat_count')
                ->label('Telat')
                ->state(fn(Member $record): int => $this->getMemberStats($record)['telat'])
                ->badge()
                ->color('warning')
                ->alignCenter(),
            TextColumn::make('izin_count')
                ->label('Izin')
                ->state(fn(Member $record): int => $this->getMemberStats($record)['izin'])
                ->badge()
                ->color('warning')
                ->alignCenter(),
            TextColumn::make('sakit_count')
                ->label('Sakit')
                ->state(fn(Member $record): int => $this->getMemberStats($record)['sakit'])
                ->badge()
                ->color('danger')
                ->alignCenter(),
            TextColumn::make('alfa_count')
                ->label('Tidak Hadir')
                ->state(fn(Member $record): int => $this->getMemberStats($record)['alfa'])
                ->badge()
                ->color(fn($state) => $state > 0 ? 'danger' : 'gray')
                ->alignCenter(),
            TextColumn::make('attendance_percentage')
                ->label('% Kehadiran')
                ->state(fn(Member $record): string => $this->getMemberStats($record)['percentage'] . '%')
                ->badge()
                ->color(fn($state) => $state >= 75 ? 'success' : ($state >= 50 ? 'warning' : 'danger'))
                ->alignCenter(),
            TextColumn::make('last_five')
                ->label('Kehadiran Terakhir')
                ->state(fn(Member $record): string => $this->getMemberStats($record)['last_five'])
                ->badge()
                ->color('gray')
                ->alignCenter(),
        ];
    }
}
