<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Models\Level;
use App\Models\Meeting;
use App\Models\Member;
use App\Enums\AttendanceStatus;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

class GroupMeetingHistory extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = GroupResource::class;

    protected string $view = 'filament.resources.groups.pages.group-meeting-history';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        return $record
            ? auth()->user()->can('viewMeetingHistory', $record)
            : auth()->user()->can('ViewMeetingHistory:Group');
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
        $group = $this->getRecord();
        return [
            $resource::getUrl() => $resource::getNavigationLabel(),
            $resource::getUrl('view', ['record' => $group]) => $group->name,
            'Riwayat Pertemuan',
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
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value'])
                            return;
                        $query->whereHas('group', fn($q) => $q->where('level_id', $data['value']));
                    }),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('date_to')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['date_from'], fn($q) => $q->whereDate('meeting_date', '>=', $data['date_from']))
                            ->when($data['date_to'], fn($q) => $q->whereDate('meeting_date', '<=', $data['date_to']));
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view_attendance_details')
                        ->label('Detail Kehadiran Grup')
                        ->icon('heroicon-m-users')
                        ->color('grey')
                        ->url(fn(Meeting $record) => \App\Filament\Resources\Meetings\Pages\MeetingAttendanceDetails::getUrl(['record' => $record->uid]) . '?group=' . $this->record->uid)
                        ->openUrlInNewTab()
                        ->visible(fn () => auth()->user()->can('openAttendanceDetails', $this->getRecord())),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Menu Aksi'),
            ])
            ->defaultSort('meeting_date', 'desc');
    }

    protected array $scopeGroupIds = [];

    protected function getTableQuery(): Builder
    {
        $group = $this->getRecord();
        $this->scopeGroupIds = $group->getAllDescendantIds();

        $groupIds = array_unique(array_merge(
            [$group->id],
            $this->scopeGroupIds,
            $group->getAllAncestorIds()
        ));

        return Meeting::query()
            ->whereIn('group_id', $groupIds)
            ->with(['group.level']);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nama Pertemuan')
                ->searchable()
                ->url(fn(Meeting $record) => MeetingResource::getUrl('view', ['record' => $record]))
                ->openUrlInNewTab(),
            TextColumn::make('meeting_date')
                ->label('Tanggal')
                ->date('d/m/Y')
                ->sortable(),
            TextColumn::make('group.level.name')
                ->label('Tingkat')
                ->badge()
                ->color(fn (Meeting $record): string => $record->group?->level?->getColor() ?? 'gray'),
            TextColumn::make('target_count')
                ->label('Target Anggota')
                ->state(fn(Meeting $record) => $this->getMeetingStats($record)['target'])
                ->badge()
                ->color('gray')
                ->alignCenter(),
            TextColumn::make('hadir_count')
                ->label('Hadir')
                ->state(fn(Meeting $record) => $this->getMeetingStats($record)['hadir'])
                ->badge()
                ->color('success')
                ->alignCenter(),
            TextColumn::make('izin_count')
                ->label('Izin')
                ->state(fn(Meeting $record) => $this->getMeetingStats($record)['izin'])
                ->badge()
                ->color('warning')
                ->alignCenter(),
            TextColumn::make('sakit_count')
                ->label('Sakit')
                ->state(fn(Meeting $record) => $this->getMeetingStats($record)['sakit'])
                ->badge()
                ->color('danger')
                ->alignCenter(),
            TextColumn::make('alfa_count')
                ->label('Tidak Hadir')
                ->state(fn(Meeting $record) => $this->getMeetingStats($record)['alfa'])
                ->badge()
                ->color(fn($state) => $state > 0 ? 'danger' : 'gray')
                ->alignCenter(),
            TextColumn::make('percentage')
                ->label('% Hadir')
                ->state(fn(Meeting $record) => $this->getMeetingStats($record)['percentage'] . '%')
                ->badge()
                ->color(fn($state) => (float) rtrim($state, '%') >= 75 ? 'success' : ((float) rtrim($state, '%') >= 50 ? 'warning' : 'danger'))
                ->alignCenter(),
        ];
    }

    protected array $statsCache = [];

    protected function getMeetingStats(Meeting $record): array
    {
        if (isset($this->statsCache[$record->id])) {
            return $this->statsCache[$record->id];
        }

        $memberIds = Member::whereIn('group_id', $this->scopeGroupIds)
            ->where('status', true)
            ->whereDate('created_at', '<=', $record->meeting_date)
            ->pluck('id');

        $target = $memberIds->count();

        $attendances = $record->attendances()
            ->whereIn('member_id', $memberIds)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $hadir = $attendances[AttendanceStatus::HADIR->value] ?? 0;
        $izin = $attendances[AttendanceStatus::IZIN->value] ?? 0;
        $sakit = $attendances[AttendanceStatus::SAKIT->value] ?? 0;

        $alfa = max(0, $target - $hadir - $izin - $sakit);
        $percentage = $target > 0 ? round(($hadir / $target) * 100, 1) : 0;

        return $this->statsCache[$record->id] = [
            'target' => $target,
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alfa' => $alfa,
            'percentage' => $percentage,
        ];
    }
}
