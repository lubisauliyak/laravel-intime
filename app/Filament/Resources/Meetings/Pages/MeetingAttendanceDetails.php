<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Schemas\MeetingAttendanceInfolist;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Schema;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Grid as SchemaGrid;
use Illuminate\Support\Facades\Storage;

class MeetingAttendanceDetails extends Page implements HasTable, HasSchemas
{
    use InteractsWithTable;
    use InteractsWithSchemas;

    protected static string $resource = MeetingResource::class;

    protected string $view = 'filament.resources.meetings.pages.meeting-attendance-details';

    public ?Meeting $meeting = null;
    public ?Group $group = null;

    public function mount($record): void
    {
        $this->meeting = Meeting::findOrFail($record);
        
        $groupId = request()->query('group');
        $this->group = Group::findOrFail($groupId);
    }

    protected function isMeetingOver(): bool
    {
        return $this->meeting->meeting_date->setTimeFrom($this->meeting->end_time)->isPast();
    }

    public function getTitle(): string
    {
        return "Detail Presensi";
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        return [
            $resource::getUrl() => $resource::getNavigationLabel(),
            $resource::getUrl('view', ['record' => $this->meeting]) => $this->meeting->name,
            "Detail Presensi"
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return MeetingAttendanceInfolist::configure(
            $schema, 
            $this->meeting, 
            $this->group, 
            $this->isMeetingOver()
        );
    }

    public function table(Table $table): Table
    {
        $descendantGroupIds = $this->group->getAllDescendantIds();
        $meetingId = $this->meeting->id;

        return $table
            ->query(
                Member::query()
                    ->select('members.*')
                    ->whereIn('members.group_id', $descendantGroupIds)
                    ->where('members.status', true)
                    ->when(!empty($this->meeting->target_age_groups), function ($q) {
                        return $q->whereHas('ageGroup', function ($aq) {
                            return $aq->whereIn('name', $this->meeting->target_age_groups);
                        });
                    })
                    ->leftJoin('attendances', function ($join) use ($meetingId) {
                        $join->on('attendances.member_id', '=', 'members.id')
                            ->where('attendances.meeting_id', '=', $meetingId);
                    })
                    ->orderByRaw('attendances.checkin_time IS NULL ASC')
                    ->orderBy('attendances.checkin_time', 'ASC')
                    ->orderBy('members.id', 'ASC')
            )
            ->columns([
                TextColumn::make('member_code')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable(),
                TextColumn::make('gender')
                    ->label('L/P')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'male' => 'L',
                        'female' => 'P',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ageGroup.name')
                    ->label('Kategori Usia')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains(strtolower($state), 'pra remaja') => 'info',
                        str_contains(strtolower($state), 'remaja') => 'warning',
                        str_contains(strtolower($state), 'pra nikah') => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('group.name')
                    ->label('Grup'),
                TextColumn::make('attendance_status')
                    ->label('Status Presensi')
                    ->badge()
                    ->getStateUsing(function (Member $record) {
                        $attendance = Attendance::where('meeting_id', $this->meeting->id)
                            ->where('member_id', $record->id)
                            ->first();

                        if (!$attendance) {
                            return $this->isMeetingOver() ? 'TIDAK HADIR' : 'BELUM HADIR';
                        }

                        return strtoupper($attendance->status);
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'HADIR' => 'success',
                        'IZIN', 'SAKIT' => 'warning',
                        'BELUM HADIR' => 'gray',
                        'TIDAK HADIR' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('checkin_time')
                    ->label('Waktu Kedatangan')
                    ->getStateUsing(function (Member $record) {
                        return Attendance::where('meeting_id', $this->meeting->id)
                            ->where('member_id', $record->id)
                            ->first()?->checkin_time?->format('H:i');
                    })
                    ->description(fn (Member $record) => Attendance::where('meeting_id', $this->meeting->id)
                            ->where('member_id', $record->id)
                            ->first()?->method === 'manual' ? 'Input Manual' : ''),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Filter Kehadiran')
                    ->options([
                        'hadir' => 'Hadir',
                        'izin_sakit' => 'Izin / Sakit',
                        'belum_hadir' => 'Belum Hadir / Tidak Hadir',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) return;

                        $meetingId = $this->meeting->id;

                        if ($data['value'] === 'hadir') {
                            $query->whereHas('attendances', fn ($q) => $q->where('meeting_id', $meetingId)->where('status', 'hadir'));
                        } elseif ($data['value'] === 'izin_sakit') {
                            $query->whereHas('attendances', fn ($q) => $q->where('meeting_id', $meetingId)->whereIn('status', ['izin', 'sakit']));
                        } elseif ($data['value'] === 'belum_hadir') {
                            $query->whereDoesntHave('attendances', fn ($q) => $q->where('meeting_id', $meetingId));
                        }
                    }),
                SelectFilter::make('gender')
                    ->label('Filter Gender')
                    ->options([
                        'male' => 'Laki-laki',
                        'female' => 'Perempuan',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) return;
                        $query->where('gender', $data['value']);
                    }),
                SelectFilter::make('age_group_id')
                    ->label('Filter Kategori Usia')
                    ->options(function () {
                        return \App\Models\AgeGroup::orderBy('sort_order')->pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) return;
                        $query->where('age_group_id', $data['value']);
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('set_status')
                        ->label('Set Status')
                        ->icon('heroicon-m-pencil-square')
                        ->color('info')
                        ->form([
                            FormSelect::make('status')
                                ->label('Status Presensi')
                                ->options([
                                    'hadir' => 'Hadir',
                                    'izin' => 'Izin',
                                    'sakit' => 'Sakit',
                                ])
                                ->required()
                                ->default(fn (Member $record) => 
                                    Attendance::where('meeting_id', $this->meeting->id)
                                        ->where('member_id', $record->id)
                                        ->first()?->status ?? 'hadir'
                                )
                                ->live(),
                            FileUpload::make('evidence_path')
                                ->label('Lampiran Foto/Gambar')
                                ->image()
                                ->directory('attendance-evidences')
                                ->disk('public')
                                ->visibility('public')
                                ->default(fn (Member $record) => 
                                    Attendance::where('meeting_id', $this->meeting->id)
                                        ->where('member_id', $record->id)
                                        ->first()?->evidence_path
                                )
                                ->visible(fn ($get) => in_array($get('status'), ['izin', 'sakit'])),
                            Textarea::make('notes')
                                ->label('Keterangan')
                                ->placeholder('Contoh: Sedang keluar kota / Sakit demam')
                                ->default(fn (Member $record) => 
                                    Attendance::where('meeting_id', $this->meeting->id)
                                        ->where('member_id', $record->id)
                                        ->first()?->notes
                                )
                                ->visible(fn ($get) => in_array($get('status'), ['izin', 'sakit'])),
                        ])
                        ->action(function (Member $record, array $data) {
                            Attendance::updateOrCreate(
                                ['meeting_id' => $this->meeting->id, 'member_id' => $record->id],
                                [
                                    'status' => $data['status'],
                                    'notes' => $data['notes'] ?? null,
                                    'evidence_path' => $data['evidence_path'] ?? null,
                                    'method' => 'manual',
                                    'checkin_time' => now()
                                ]
                            );
                        }),

                    Action::make('clear_status')
                        ->label('Hapus Presensi')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Member $record) => 
                            Attendance::where('meeting_id', $this->meeting->id)
                                ->where('member_id', $record->id)
                                ->exists()
                        )
                        ->action(function (Member $record) {
                            Attendance::where('meeting_id', $this->meeting->id)
                                ->where('member_id', $record->id)
                                ->delete();
                        }),
                ])
                ->visible(fn () => auth()->user()->can('update', $this->meeting))
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Menu Aksi'),

                Action::make('view_details')
                    ->label('Lihat Lampiran')
                    ->icon('heroicon-m-eye')
                    ->color('success')
                    ->visible(function (Member $record) {
                        $attendance = Attendance::where('meeting_id', $this->meeting->id)
                            ->where('member_id', $record->id)
                            ->first();
                        
                        return $attendance && ($attendance->evidence_path || $attendance->notes);
                    })
                    ->modalHeading('Detail Lampiran & Keterangan')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->infolist(function (Member $record) {
                        $attendance = Attendance::where('meeting_id', $this->meeting->id)
                            ->where('member_id', $record->id)
                            ->first();

                        return Schema::make($this)
                            ->record($attendance)
                            ->components([
                                SchemaGrid::make(1)
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('Status Presensi')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'hadir' => 'success',
                                                'izin', 'sakit' => 'warning',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn ($state) => strtoupper($state)),
                                        TextEntry::make('notes')
                                            ->label('Keterangan / Alasan')
                                            ->prose()
                                            ->placeholder('Tidak ada keterangan tambahan'),
                                        ImageEntry::make('evidence_path')
                                            ->label('Foto Lampiran')
                                            ->disk('public')
                                            ->height(250)
                                            ->extraImgAttributes([
                                                'class' => 'rounded-xl shadow-lg cursor-zoom-in border border-gray-200 dark:border-white/10',
                                            ])
                                            ->url(fn ($record) => $record->evidence_path ? Storage::url($record->evidence_path) : null)
                                            ->openUrlInNewTab()
                                            ->placeholder('Tidak ada foto lampiran'),
                                    ]),
                            ]);
                    }),
            ]);
    }
}
