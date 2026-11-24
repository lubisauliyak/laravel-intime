<?php

namespace App\Filament\Widgets;

use App\Models\Meeting;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class UpcomingMeetingsWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 7,
    ];

    protected static ?string $heading = 'Agenda Pertemuan Terdekat';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $query = Meeting::query()->with(['group.level']);

        if ($user && ! $user->isSuperAdmin()) {
            if ($user->group_id && $user->group) {
                $descendantIds = $user->group->getAllDescendantIds();
                $ancestorIds = $user->group->getAllAncestorIds();
                $allowedGroupIds = array_unique(array_merge($descendantIds, $ancestorIds));
                $query->whereIn('group_id', $allowedGroupIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $orderExpr = "CASE WHEN meeting_date = date('now') THEN 0 WHEN meeting_date > date('now') THEN 1 ELSE 2 END, CASE WHEN meeting_date >= date('now') THEN meeting_date END ASC, meeting_date DESC";
        } else {
            $orderExpr = 'CASE WHEN meeting_date = CURDATE() THEN 0 WHEN meeting_date > CURDATE() THEN 1 ELSE 2 END, CASE WHEN meeting_date >= CURDATE() THEN meeting_date END ASC, meeting_date DESC';
        }

        $query->orderByRaw($orderExpr)->orderBy('start_time', 'asc')->limit(3);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('Pertemuan')
                    ->weight('bold')
                    ->description(fn (Meeting $record): string => $record->group?->name ?? '-')
                    ->wrap(),

                TextColumn::make('meeting_date')
                    ->label('Waktu')
                    ->formatStateUsing(function (Meeting $record): string {
                        $date = $record->meeting_date?->translatedFormat('d M Y') ?? '-';
                        $time = Carbon::parse($record->start_time)->format('H:i').' - '.Carbon::parse($record->end_time)->format('H:i');

                        return "{$date} • {$time}";
                    })
                    ->size('sm'),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(function (Meeting $record): string {
                        if ($record->isCheckinOpen()) {
                            return 'Presensi Buka';
                        }
                        if ($record->isExpired()) {
                            return 'Selesai';
                        }
                        if ($record->meeting_date?->isToday()) {
                            return 'Hari Ini';
                        }

                        return 'Mendatang';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Presensi Buka' => 'success',
                        'Hari Ini' => 'warning',
                        'Mendatang' => 'info',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Action::make('scanner')
                    ->label('Scanner')
                    ->icon('heroicon-m-qr-code')
                    ->color('success')
                    ->button()
                    ->size('xs')
                    ->url(fn (Meeting $record): string => route('scanner.live', $record))
                    ->visible(fn (Meeting $record): bool => $record->isCheckinOpen()),

                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->button()
                    ->size('xs')
                    ->url(fn (Meeting $record): string => url("/admin/meetings/{$record->uid}")),
            ])
            ->headerActions([
                Action::make('view_all')
                    ->label('Lihat Semua')
                    ->icon('heroicon-m-arrow-right')
                    ->color('gray')
                    ->url(url('/admin/meetings')),
            ])
            ->emptyStateHeading('Belum Ada Pertemuan')
            ->emptyStateDescription('Belum ada jadwal pertemuan yang tersimpan.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Buat Pertemuan')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->url(url('/admin/meetings/create')),
            ])
            ->paginated(false)
            ->defaultPaginationPageOption(3);
    }
}
