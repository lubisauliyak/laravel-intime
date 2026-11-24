<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\MembershipType;
use App\Exports\MeetingAttendanceExport;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid as SchemaGrid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeetingAttendanceDetails extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MeetingResource::class;

    protected string $view = 'filament.resources.meetings.pages.meeting-attendance-details';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if (is_string($record) || is_int($record)) {
            $record = Meeting::where('uid', $record)->orWhere('id', $record)->first();
        }

        if ($record instanceof Meeting) {
            return auth()->user()?->can('viewAttendanceDetails', $record) ?? false;
        }

        return auth()->user()?->can('viewAny', Meeting::class) ?? false;
    }

    public ?Meeting $meeting = null;

    public ?Group $group = null;

    public function mount($record): void
    {
        $this->meeting = Meeting::where('uid', $record)->firstOrFail();

        $groupId = request()->query('group');
        $this->group = $groupId ? Group::where('uid', $groupId)->firstOrFail() : $this->meeting->group;
    }

    protected static function getPermittedGroupIds(Group $group): array
    {
        $descendantIds = $group->getAllDescendantIds();
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin() && $user->group_id) {
            $userDescendantIds = $user->group->getAllDescendantIds();
            $descendantIds = array_intersect($descendantIds, $userDescendantIds);
        }

        return empty($descendantIds) ? [0] : $descendantIds;
    }

    protected function isMeetingOver(): bool
    {
        return $this->meeting->isExpired();
    }

    public static function getNavigationLabel(): string
    {
        return 'Detail Kehadiran';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-list-bullet';
    }

    public function getTitle(): string
    {
        return 'Detail Kehadiran';
    }

    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    public function getSubNavigationParameters(): array
    {
        return [
            'record' => $this->meeting,
        ];
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [
            $resource::getUrl() => $resource::getNavigationLabel(),
            $resource::getUrl('view', ['record' => $this->meeting]) => $this->meeting->name,
            'Detail Kehadiran',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $meetingId = request()->route('record');
        if (! $meetingId) {
            return null;
        }

        $meeting = Meeting::find($meetingId);
        if (! $meeting) {
            return null;
        }

        $descendantGroupIds = static::getPermittedGroupIds($meeting->group);

        $hadir = Attendance::where('meeting_id', $meetingId)
            ->where('status', AttendanceStatus::HADIR)
            ->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantGroupIds))
            ->count();

        $confirmed = Attendance::where('meeting_id', $meetingId)
            ->whereIn('status', [AttendanceStatus::HADIR, AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
            ->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantGroupIds))
            ->count();

        if ($confirmed === 0) {
            return null;
        }

        return "{$hadir}/{$confirmed}";
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    protected function getExportMemberIds(): array
    {
        return $this->getFilteredTableQuery()
            ->pluck('members.id')
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->hiddenLabel()
                ->outlined()
                ->color('info')
                ->tooltip('Cetak Laporan Kehadiran (PDF)')
                ->visible(fn () => auth()->user()->can('exportPdf', $this->meeting))
                ->action(function () {
                    $memberIds = $this->getExportMemberIds();

                    if (empty($memberIds)) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Tidak Ada Data')
                            ->body('Tidak ditemukan data kehadiran anggota yang dapat dicetak.')
                            ->send();

                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Membuka Laporan PDF')
                        ->body('Laporan kehadiran PDF sedang disiapkan di tab baru.')
                        ->send();

                    $cacheKey = 'pdf_export_'.Str::random(16);
                    Cache::put($cacheKey, $memberIds, now()->addMinutes(30));

                    $url = route('meeting.report.pdf', ['meeting' => $this->meeting, 'filter_key' => $cacheKey]);
                    $this->js("window.open('{$url}', '_blank')");
                }),
            Action::make('export_excel')
                ->label('Ekspor Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->hiddenLabel()
                ->outlined()
                ->color('success')
                ->tooltip('Ekspor Detail Kehadiran (Excel)')
                ->visible(fn () => auth()->user()->can('exportExcel', $this->meeting))
                ->action(function () {
                    $memberIds = $this->getExportMemberIds();

                    if (empty($memberIds)) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Tidak Ada Data')
                            ->body('Tidak ditemukan data kehadiran anggota yang dapat diekspor.')
                            ->send();

                        return;
                    }

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Ekspor Dimulai')
                        ->body('File kehadiran Excel sedang dipersiapkan dan akan segera diunduh.')
                        ->send();

                    $rawFilename = "Kehadiran-{$this->meeting->name}-{$this->meeting->group->name}-".$this->meeting->meeting_date->format('Y-m-d').'.xlsx';
                    $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $rawFilename);
                    $filename = preg_replace('/-+/', '-', $filename);

                    return (new MeetingAttendanceExport($this->meeting->id, $memberIds))
                        ->download($filename);
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters())
            ->actions($this->getTableActions());
    }

    protected function getTableQuery(): Builder
    {
        $descendantGroupIds = static::getPermittedGroupIds($this->group);
        $meetingId = $this->meeting->id;
        $allAgeGroupsCount = AgeGroup::count();
        $selectedAgeGroupsCount = empty($this->meeting->target_age_groups) ? 0 : count($this->meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

        return Member::query()
            ->select([
                'members.*',
                'attendances.status as attendance_status',
                'attendances.checkin_time',
                'attendances.method',
                'attendances.notes',
                'attendances.evidence_path',
            ])
            ->leftJoin('attendances', function ($join) use ($meetingId) {
                $join->on('attendances.member_id', '=', 'members.id')
                    ->where('attendances.meeting_id', '=', $meetingId)
                    ->whereNull('attendances.deleted_at');
            })
            ->whereIn('members.group_id', $descendantGroupIds)
            ->where(function ($query) use ($shouldFilterByAge) {
                // Anggota Target Wajib (status = true)
                $query->where(function ($sub) use ($shouldFilterByAge) {
                    $sub->where('members.status', true)
                        ->when($shouldFilterByAge, function ($aq) {
                            return $aq->whereHas('ageGroup', function ($q) {
                                return $q->whereIn('name', $this->meeting->target_age_groups);
                            });
                        });
                })
                // ATAU anggota non-target yang hadir/mengajukan perizinan di pertemuan ini
                    ->orWhereNotNull('attendances.status');
            })
            ->with(['ageGroup', 'group.parent.parent.parent', 'positions.group.level', 'positions.category'])
            ->orderByRaw('attendances.checkin_time IS NULL ASC')
            ->orderBy('attendances.checkin_time', 'ASC')
            ->orderBy('members.id', 'ASC');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('member_code')
                ->label('ID Anggota')
                ->searchable(),
            TextColumn::make('full_name')
                ->label('Nama Lengkap')
                ->searchable()
                ->color(fn (Member $record): ?string => $record->getMemberColor())
                ->tooltip(fn (Member $record): ?string => $record->getMemberColorTooltip()),
            TextColumn::make('gender')
                ->label('L/P')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof Gender ? $state->getCode() : (Gender::tryFrom($state)?->getCode() ?? $state)),
            TextColumn::make('ageGroup.name')
                ->label('Kategori Usia')
                ->badge()
                ->color(fn ($state): string => AgeGroup::getColorMapping()[(string) $state] ?? 'gray'),
            TextColumn::make('group.name')
                ->label('Kelompok'),
            TextColumn::make('attendance_status')
                ->label('Status Kehadiran')
                ->badge()
                ->getStateUsing(function (Member $record) {
                    if (! $record->attendance_status) {
                        return $this->isMeetingOver() ? 'TIDAK HADIR' : 'BELUM HADIR';
                    }

                    return AttendanceStatus::tryFrom($record->attendance_status)?->getLabel() ?? strtoupper($record->attendance_status);
                })
                ->color(function ($state, Member $record) {
                    if (! $record->attendance_status) {
                        return $this->isMeetingOver() ? 'danger' : 'gray';
                    }

                    return AttendanceStatus::tryFrom($record->attendance_status)?->getColor() ?? 'gray';
                })
                ->icon(function (Member $record) {
                    return AttendanceStatus::tryFrom($record->attendance_status)?->getIcon();
                }),
            TextColumn::make('method')
                ->label('Metode')
                ->badge()
                ->color(fn ($state) => AttendanceMethod::tryFrom($state)?->getColor() ?? 'gray')
                ->icon(fn ($state) => AttendanceMethod::tryFrom($state)?->getIcon())
                ->formatStateUsing(fn ($state) => AttendanceMethod::tryFrom($state)?->getLabel() ?? '-')
                ->placeholder('-'),
            TextColumn::make('checkin_time')
                ->label('Waktu')
                ->formatStateUsing(function ($state, Member $record) {
                    if (! $state) {
                        return '-';
                    }
                    $carbon = Carbon::parse($state);
                    if ($record->attendance_status === AttendanceStatus::HADIR->value) {
                        return $carbon->format('H:i');
                    }

                    return $carbon->format('d/m/Y H:i');
                })
                ->placeholder('-'),
            TextColumn::make('notes')
                ->label('Keterangan')
                ->placeholder('-')
                ->limit(50)
                ->toggleable(isToggledHiddenByDefault: false),
            ImageColumn::make('evidence_path')
                ->label('Lampiran')
                ->disk('public')
                ->width(32)
                ->imageHeight(32)
                ->action(
                    Action::make('viewEvidence')
                        ->modalHeading('Detail Lampiran & Keterangan')
                        ->modalWidth('md')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->hidden(fn (Member $record) => ! $record->evidence_path)
                        ->infolist(function (Member $record) {
                            return Schema::make($this)
                                ->record(new Attendance([
                                    'status' => $record->attendance_status,
                                    'notes' => $record->notes,
                                    'evidence_path' => $record->evidence_path,
                                ]))
                                ->components([
                                    SchemaGrid::make(1)
                                        ->schema([
                                            TextEntry::make('status')
                                                ->label('Status Kehadiran')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? strtoupper($state->value) : '-'),
                                            TextEntry::make('notes')
                                                ->label('Keterangan / Alasan')
                                                ->prose()
                                                ->placeholder('Tidak ada keterangan tambahan'),
                                            ImageEntry::make('evidence_path')
                                                ->label('Foto Lampiran')
                                                ->disk('public')
                                                ->imageHeight(250)
                                                ->extraImgAttributes([
                                                    'class' => 'rounded-xl shadow-lg cursor-zoom-in border border-gray-200 dark:border-white/10',
                                                ])
                                                ->url(fn ($record) => $record->evidence_path ? Storage::url($record->evidence_path) : null)
                                                ->openUrlInNewTab()
                                                ->placeholder('Tidak ada foto lampiran'),
                                        ]),
                                ]);
                        }),
                )
                ->toggleable(isToggledHiddenByDefault: false),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status Kehadiran')
                ->options([
                    AttendanceStatus::HADIR->value => AttendanceStatus::HADIR->getLabel(),
                    'izin_sakit' => 'Izin / Sakit',
                    'belum_hadir' => 'Belum Hadir / Tidak Hadir',
                ])
                ->query(function (Builder $query, array $data) {
                    if (! $data['value']) {
                        return;
                    }

                    $meetingId = $this->meeting->id;

                    if ($data['value'] === AttendanceStatus::HADIR->value) {
                        $query->whereHas('attendances', fn ($q) => $q->where('meeting_id', $meetingId)->where('status', AttendanceStatus::HADIR));
                    } elseif ($data['value'] === 'izin_sakit') {
                        $query->whereHas('attendances', fn ($q) => $q->where('meeting_id', $meetingId)->whereIn('status', [AttendanceStatus::IZIN, AttendanceStatus::SAKIT]));
                    } elseif ($data['value'] === 'belum_hadir') {
                        $query->whereDoesntHave('attendances', fn ($q) => $q->where('meeting_id', $meetingId));
                    }
                }),
            SelectFilter::make('gender')
                ->label('Jenis Kelamin')
                ->options([
                    Gender::MALE->value => 'Laki-laki',
                    Gender::FEMALE->value => 'Perempuan',
                ])
                ->query(function (Builder $query, array $data) {
                    if (! $data['value']) {
                        return;
                    }
                    $query->where('gender', $data['value']);
                }),
            SelectFilter::make('age_group_id')
                ->label('Kategori Usia')
                ->options(function () {
                    return AgeGroup::orderBy('sort_order')->pluck('name', 'id');
                })
                ->query(function (Builder $query, array $data) {
                    if (! $data['value']) {
                        return;
                    }
                    $query->where('age_group_id', $data['value']);
                }),
            SelectFilter::make('membership_type')
                ->label('Keanggotaan')
                ->options(MembershipType::class),
            SelectFilter::make('kelompok')
                ->label('Kelompok')
                ->multiple()
                ->options(function () {
                    return Group::whereIn('id', static::getPermittedGroupIds($this->group))
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data) {
                    if (empty($data['values'])) {
                        return;
                    }
                    $query->whereIn('members.group_id', $data['values']);
                }),
            SelectFilter::make('desa')
                ->label('Desa')
                ->multiple()
                ->options(function () {
                    $descendantIds = static::getPermittedGroupIds($this->group);

                    return Group::whereHas('level', fn ($q) => $q->where('level_number', 2))
                        ->whereHas('children', fn ($q) => $q->whereIn('id', $descendantIds))
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data) {
                    if (empty($data['values'])) {
                        return;
                    }
                    $query->whereHas('group.parent', fn ($q) => $q->whereIn('id', $data['values']));
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('set_status')
                    ->label('Ubah Status')
                    ->icon('heroicon-m-pencil-square')
                    ->color('success')
                    ->modalWidth('md')
                    ->form([
                        FormSelect::make('status')
                            ->label('Status Kehadiran')
                            ->options(AttendanceStatus::class)
                            ->required()
                            ->default(fn (Member $record) => $record->attendance_status ?? AttendanceStatus::HADIR->value)
                            ->live(),
                        FileUpload::make('evidence_path')
                            ->label('Lampiran')
                            ->image()
                            ->directory('attendance-evidence')
                            ->disk('public')
                            ->visibility('public')
                            ->default(fn (Member $record) => $record->evidence_path)
                            ->visible(function ($get) {
                                $status = $get('status');
                                $val = $status instanceof AttendanceStatus ? $status->value : $status;

                                return in_array($val, ['izin', 'sakit']);
                            }),
                        Textarea::make('notes')
                            ->label('Keterangan')
                            ->placeholder('Contoh: Sedang keluar kota / Sakit demam')
                            ->default(fn (Member $record) => $record->notes)
                            ->visible(function ($get) {
                                $status = $get('status');
                                $val = $status instanceof AttendanceStatus ? $status->value : $status;

                                return in_array($val, ['izin', 'sakit']);
                            }),
                    ])
                    ->action(function (Member $record, array $data) {
                        Attendance::withTrashed()->updateOrCreate(
                            ['meeting_id' => $this->meeting->id, 'member_id' => $record->id],
                            [
                                'status' => $data['status'],
                                'notes' => $data['notes'] ?? null,
                                'evidence_path' => $data['evidence_path'] ?? null,
                                'method' => AttendanceMethod::MANUAL,
                                'checkin_time' => now(),
                                'deleted_at' => null,
                            ]
                        );
                    }),

                Action::make('clear_status')
                    ->label('Hapus Presensi')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Member $record) => (bool) $record->attendance_status)
                    ->action(function (Member $record) {
                        Attendance::withTrashed()
                            ->where('meeting_id', $this->meeting->id)
                            ->where('member_id', $record->id)
                            ->forceDelete();
                    }),
            ])
                ->visible(fn () => auth()->user()->can('update', $this->meeting))
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Menu Aksi'),

        ];
    }
}
