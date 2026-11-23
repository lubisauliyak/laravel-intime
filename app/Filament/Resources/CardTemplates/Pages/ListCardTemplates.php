<?php

namespace App\Filament\Resources\CardTemplates\Pages;

use App\Filament\Resources\CardTemplates\CardTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCardTemplates extends ListRecords
{
    protected static string $resource = CardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
