<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Widgets\MeetingStatsTable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_scanner')
                ->label('Buka Scanner')
                ->icon('heroicon-o-qr-code')
                ->color(fn ($record) => $record->isCheckinOpen() ? 'success' : 'gray')
                ->url(fn ($record) => $record->isCheckinOpen() ? route('scanner.live', $record) : null)
                ->openUrlInNewTab(fn ($record) => $record->isCheckinOpen())
                ->disabled(fn ($record) => ! $record->isCheckinOpen())
                ->tooltip(function ($record) {
                    if (! $record->meeting_date?->isToday()) {
                        return 'Presensi hanya dapat dibuka pada hari pertemuan ('.($record->meeting_date?->format('d/m/Y') ?? '-').')';
                    }
                    if ($record->isExpired()) {
                        return 'Pertemuan telah selesai';
                    }
                    if (! $record->isCheckinOpen()) {
                        $openTime = $record->checkin_open_time?->format('H:i') ?? $record->start_time?->format('H:i') ?? '-';

                        return "Presensi baru dibuka pukul {$openTime}";
                    }

                    return 'Buka scanner QR presensi';
                })
                ->visible(fn ($record) => ! $record->isExpired() && auth()->user()->can('openScanner', $record)),
            EditAction::make(),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Informasi Umum';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-information-circle';
    }

    public function getTitle(): string
    {
        return 'Informasi Umum';
    }

    protected function getFooterWidgets(): array
    {
        return [
            MeetingStatsTable::class,
        ];
    }
}
