<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Meeting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
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
            $authGroupIds = array_merge([$user->group_id], $user->group->getAllAncestorIds());

            $query->whereHas('member', function($q) use ($allowedGroupIds, $authGroupIds) {
                $q->whereIn('group_id', $allowedGroupIds)
                  ->orWhere(function($sq) use ($authGroupIds) {
                      $sq->where('membership_type', 'pengurus')
                         ->whereHas('positions', fn($pq) => $pq->whereIn('group_id', $authGroupIds));
                  });
            });
        }

        return $table
            ->heading($heading)
            ->query($query->limit(10))
            ->columns([
                TextColumn::make('member.full_name')
                    ->label('Nama Anggota'),
                TextColumn::make('member.group.name')
                    ->label('Grup'),
                TextColumn::make('checkin_time')
                    ->label('Jam Presensi')
                    ->time('H:i:s'),
                TextColumn::make('status')
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
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->badge()
                    ->color(fn ($state) => str_contains($state ?? '', 'TERLAMBAT') ? 'danger' : 'gray'),
            ])
            ->paginated(false);
    }
}
