<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Attendance;
use App\Models\Level;
use App\Models\Meeting;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid as SchemaGrid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class MemberMeetingHistory extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.members.pages.member-meeting-history';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        if (is_string($record) || is_int($record)) {
            $record = Member::where('uid', $record)->orWhere('id', $record)->first();
        }

        if ($record instanceof Member) {
            return auth()->user()?->can('viewMeetingHistory', $record) ?? false;
        }

        return auth()->user()?->can('viewAny', Member::class) ?? false;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function getNavigationLabel(): string
    {
        return 'Riwayat Pertemuan';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-calendar-days';
    }

    public function getTitle(): string
    {
        return 'Riwayat Pertemuan';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();
        $member = $this->getRecord();

        return [
            $resource::getUrl() => $resource::getNavigationLabel(),
            $resource::getUrl('view', ['record' => $member]) => $member->full_name,
            'Riwayat Pertemuan',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Kehadiran')
                    ->options([
                        'hadir' => 'Hadir',
                        'izin_sakit' => 'Izin / Sakit',
                        'tidak_hadir' => 'Tidak Hadir',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return;
                        }
                        if ($data['value'] === 'hadir') {
                            $query->where('attendances.status', AttendanceStatus::HADIR);
                        } elseif ($data['value'] === 'izin_sakit') {
                            $query->whereIn('attendances.status', [AttendanceStatus::IZIN, AttendanceStatus::SAKIT]);
                        } elseif ($data['value'] === 'tidak_hadir') {
                            $query->whereNull('attendances.id');
                        }
                    }),
                SelectFilter::make('level')
                    ->label('Tingkat')
                    ->options(fn () => Level::orderBy('level_number', 'desc')->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return;
                        }
                        $query->whereHas('group', fn ($q) => $q->where('level_id', $data['value']));
                    }),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal')
                            ->default(request()->query('date_from')),
                        DatePicker::make('date_to')
                            ->label('Sampai Tanggal')
                            ->default(request()->query('date_to')),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['date_from'], fn ($q) => $q->whereDate('meeting_date', '>=', $data['date_from']))
                            ->when($data['date_to'], fn ($q) => $q->whereDate('meeting_date', '<=', $data['date_to']));
                    }),
            ])
            ->defaultSort('meeting_date', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        $member = $this->getRecord();
        $groupIds = array_merge([$member->group_id], $member->group->getAllAncestorIds());
        $memberId = $member->id;

        $pengurusGroupIds = $member->positions->pluck('group_id')->toArray();
        if ($member->membership_type === \App\Enums\MembershipType::PENGURUS) {
            $pengurusGroupIds[] = $member->group_id;
        }
        $pengurusGroupIds = array_unique(array_filter($pengurusGroupIds));
        $allAllowedGroupIds = array_unique(array_merge($groupIds, $pengurusGroupIds));

        return Meeting::query()
            ->select([
                'meetings.*',
                'attendances.status as attendance_status',
                'attendances.checkin_time',
                'attendances.method',
                'attendances.notes',
            ])
            ->leftJoin('attendances', function ($join) use ($memberId) {
                $join->on('attendances.meeting_id', '=', 'meetings.id')
                    ->where('attendances.member_id', '=', $memberId);
            })
            ->where(function ($q) use ($allAllowedGroupIds, $member, $pengurusGroupIds) {
                $q->where(function ($qTarget) use ($allAllowedGroupIds, $member, $pengurusGroupIds) {
                    $qTarget->whereIn('meetings.group_id', $allAllowedGroupIds)
                        ->whereDate('meeting_date', '>=', $member->created_at->toDateString());

                    $qTarget->where(function ($qMatch) use ($member, $pengurusGroupIds) {
                        $qMatch->where(function ($qTargetMatch) use ($member) {
                            $qTargetMatch->where(function ($qGender) use ($member) {
                                $genderValue = $member->gender instanceof \App\Enums\Gender ? $member->gender->value : $member->gender;
                                $qGender->whereNull('meetings.target_gender')
                                    ->orWhere('meetings.target_gender', \App\Enums\MeetingTargetGender::ALL->value)
                                    ->orWhere('meetings.target_gender', $genderValue);
                            });

                            $qTargetMatch->where(function ($qAge) use ($member) {
                                $qAge->whereNull('meetings.target_age_groups')
                                    ->orWhereJsonLength('meetings.target_age_groups', 0);

                                if ($member->ageGroup) {
                                    $qAge->orWhereJsonContains('meetings.target_age_groups', $member->ageGroup->name);
                                }
                            });
                        });

                        if (! empty($pengurusGroupIds)) {
                            $qMatch->orWhereIn('meetings.group_id', $pengurusGroupIds);
                        }
                    });
                });

                $q->orWhereNotNull('attendances.id');
            })
            ->with(['group.level'])
            ->orderBy('meeting_date', 'desc');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nama Pertemuan')
                ->searchable(),
            TextColumn::make('meeting_date')
                ->label('Tanggal')
                ->date('d/m/Y')
                ->sortable(),
            TextColumn::make('group.level.name')
                ->label('Tingkat')
                ->badge()
                ->color(fn (Meeting $record): string => $record->group?->level?->getColor() ?? 'gray'),
            TextColumn::make('attendance_status')
                ->label('Status Kehadiran')
                ->badge()
                ->getStateUsing(function (Meeting $record) {
                    if (! $record->attendance_status) {
                        return 'TIDAK HADIR';
                    }

                    return AttendanceStatus::tryFrom($record->attendance_status)?->getLabel() ?? strtoupper($record->attendance_status);
                })
                ->color(function ($state, Meeting $record) {
                    if (! $record->attendance_status) {
                        return 'danger';
                    }

                    return AttendanceStatus::tryFrom($record->attendance_status)?->getColor() ?? 'gray';
                })
                ->icon(function (Meeting $record) {
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
                ->formatStateUsing(function ($state, Meeting $record) {
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
                        ->hidden(fn (Meeting $record) => ! $record->evidence_path)
                        ->infolist(function (Meeting $record) {
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
}
