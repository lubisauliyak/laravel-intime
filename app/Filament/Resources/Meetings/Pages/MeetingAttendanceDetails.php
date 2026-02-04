<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class MeetingAttendanceDetails extends Page implements HasTable, HasInfolists
{
    use InteractsWithTable;
    use InteractsWithInfolists;

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
        return "Detail Presensi Grup: {$this->group->name}";
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

    public function infolist(Infolist $infolist): Infolist
    {
        $descendantIds = $this->group->getAllDescendantIds();
        $totalMembers = Member::whereIn('group_id', $descendantIds)->where('status', true)->count();
        $present = Attendance::where('meeting_id', $this->meeting->id)
            ->whereIn('member_id', Member::whereIn('group_id', $descendantIds)->pluck('id'))
            ->where('status', 'hadir')
            ->count();

        return $infolist
            ->state([
                'meeting_name' => $this->meeting->name,
                'meeting_date' => $this->meeting->meeting_date->translatedFormat('l, d F Y'),
                'group_name' => $this->group->name,
                'total_members' => $totalMembers,
                'present_count' => $present,
                'absent_count' => $totalMembers - $present,
            ])
            ->schema([
                Section::make('Informasi Pertemuan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('meeting_name')
                                    ->label('Nama Pertemuan')
                                    ->weight('bold'),
                                TextEntry::make('meeting_date')
                                    ->label('Tanggal Pelaksanaan'),
                                TextEntry::make('group_name')
                                    ->label('Grup Penyelenggara'),
                            ]),
                    ]),
                Section::make('Ringkasan Kehadiran Grup')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_members')
                                    ->label('Total Seluruh Anggota')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('present_count')
                                    ->label('Anggota Hadir')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('absent_count')
                                    ->label($this->isMeetingOver() ? 'Anggota Tidak Hadir' : 'Belum Melakukan Scan')
                                    ->badge()
                                    ->color('danger'),
                            ]),
                    ])
                    ->compact(),
            ]);
    }

    public function table(Table $table): Table
    {
        $descendantGroupIds = $this->group->getAllDescendantIds();

        return $table
            ->query(
                Member::query()
                    ->whereIn('group_id', $descendantGroupIds)
                    ->where('status', true)
            )
            ->columns([
                TextColumn::make('member_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),
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
                \Filament\Tables\Filters\SelectFilter::make('status')
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
                    })
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('set_status')
                        ->label('Set Status')
                        ->icon('heroicon-m-pencil-square')
                        ->color('info')
                        ->form([
                            \Filament\Forms\Components\Select::make('status')
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
                            \Filament\Forms\Components\FileUpload::make('evidence_path')
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
                            \Filament\Forms\Components\Textarea::make('notes')
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

                    Action::make('view_evidence')
                        ->label('Lihat Lampiran')
                        ->icon('heroicon-m-eye')
                        ->color('success')
                        ->url(function (Member $record) {
                            $attendance = Attendance::where('meeting_id', $this->meeting->id)
                                ->where('member_id', $record->id)
                                ->first();
                            return $attendance ? \Illuminate\Support\Facades\Storage::url($attendance->evidence_path) : '#';
                        })
                        ->openUrlInNewTab()
                        ->visible(function (Member $record) {
                            return Attendance::where('meeting_id', $this->meeting->id)
                                ->where('member_id', $record->id)
                                ->whereNotNull('evidence_path')
                                ->exists();
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
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Menu Aksi')
            ]);
    }
}
