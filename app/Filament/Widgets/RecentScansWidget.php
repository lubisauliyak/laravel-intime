<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentScansWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = true;

    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        $user = auth()->user();
        
        // Find reference meeting
        $meetingQuery = Meeting::where('meeting_date', '<=', now()->toDateString());
        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedMeetingGroupIds = array_merge(
                [$user->group_id],
                $user->group->getAllAncestorIds()
            );
            $meetingQuery->whereIn('group_id', $allowedMeetingGroupIds);
        }
        $refMeeting = $meetingQuery->latest('meeting_date')->first();
        $isToday = $refMeeting && $refMeeting->meeting_date->isToday();

        $heading = $isToday
            ? 'Aktivitas Presensi'
            : 'Aktivitas Presensi (' . ($refMeeting ? $refMeeting->meeting_date->format('d/m/Y') : '-') . ')';

        $query = Attendance::query()->latest('checkin_time');

        if ($refMeeting) {
            $query->where('meeting_id', $refMeeting->id);
        } else {
            $query->whereDate('checkin_time', now());
        }

        if (!$user->isSuperAdmin() && $user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            $query->whereHas('member', fn($q) => $q->whereIn('group_id', $allowedGroupIds));
        }

        return $table
            ->heading($heading)
            ->query($query->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Nama Anggota'),
                Tables\Columns\TextColumn::make('member.group.name')
                    ->label('Grup'),
                Tables\Columns\TextColumn::make('checkin_time')
                    ->label('Jam Presensi')
                    ->time('H:i:s'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'hadir' => 'Hadir',
                        'izin' => 'Izin',
                        'sakit' => 'Sakit',
                        'alpha' => 'Alpa',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'hadir' => 'success',
                        'izin', 'sakit' => 'warning',
                        'alpha' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Keterangan')
                    ->badge()
                    ->color(fn ($state) => str_contains($state ?? '', 'TERLAMBAT') ? 'danger' : 'gray'),
            ])
            ->paginated(false);
    }
}
