<?php

namespace App\Filament\Resources\CardTemplates\Pages;

use App\Filament\Resources\CardTemplates\CardTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCardTemplate extends EditRecord
{
    protected static string $resource = CardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editor')
                ->label('Buka Editor')
                ->icon('heroicon-o-paint-brush')
                ->color('info')
                ->url(fn () => url('/card-editor/'.$this->record->id))
                ->openUrlInNewTab()
                ->visible(fn () => auth()->user()?->can('openEditor', $this->record)),
            // DeleteAction::make(),
        ];
    }
}
