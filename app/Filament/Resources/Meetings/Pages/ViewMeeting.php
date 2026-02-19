<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\Meetings\Widgets\MeetingStatsTable;

class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('open_scanner')
                ->label('Buka Scanner')
                ->icon('heroicon-o-qr-code')
                ->color('emerald')
                ->url(fn ($record) => route('scanner.live', $record))
                ->openUrlInNewTab()
                ->visible(fn ($record) => !$record->isExpired() && auth()->user()->can('View:ScanAttendance')),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            MeetingStatsTable::class,
        ];
    }
}
