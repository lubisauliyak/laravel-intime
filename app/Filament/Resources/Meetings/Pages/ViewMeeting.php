<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            \Filament\Actions\Action::make('open_scanner')
                ->label('Buka Scanner')
                ->icon('heroicon-o-qr-code')
                ->color('emerald')
                ->url(fn ($record) => route('scanner.live', $record))
                ->openUrlInNewTab(),
        ];
    }
}
