<?php

namespace App\Filament\Resources\CardTemplates\Pages;

use App\Filament\Resources\CardTemplates\CardTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCardTemplate extends ViewRecord
{
    protected static string $resource = CardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editor')
                ->label('Buka Editor')
                ->icon('heroicon-o-paint-brush')
                ->color('info')
                ->url(fn ($record) => url('/card-editor/'.$record->id))
                ->openUrlInNewTab()
                ->visible(fn ($record) => auth()->user()?->can('openEditor', $record)),
            EditAction::make(),
        ];
    }
}
