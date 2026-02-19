<?php

namespace App\Filament\Resources\PositionCategories\Pages;

use App\Filament\Resources\PositionCategories\PositionCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePositionCategories extends ManageRecords
{
    protected static string $resource = PositionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
