<?php

namespace App\Filament\Resources\CardTemplates;

use App\Filament\Resources\CardTemplates\Pages\CreateCardTemplate;
use App\Filament\Resources\CardTemplates\Pages\EditCardTemplate;
use App\Filament\Resources\CardTemplates\Pages\ListCardTemplates;
use App\Filament\Resources\CardTemplates\Schemas\CardTemplateForm;
use App\Filament\Resources\CardTemplates\Schemas\CardTemplateInfolist;
use App\Filament\Resources\CardTemplates\Tables\CardTemplatesTable;
use App\Models\CardTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CardTemplateResource extends Resource
{
    protected static ?string $model = CardTemplate::class;

    protected static ?string $modelLabel = 'Template Kartu';

    protected static ?string $pluralModelLabel = 'Template Kartu';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static \UnitEnum|string|null $navigationGroup = 'Konfigurasi Sistem';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CardTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CardTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCardTemplates::route('/'),
            'create' => CreateCardTemplate::route('/create'),
            'view' => \App\Filament\Resources\CardTemplates\Pages\ViewCardTemplate::route('/{record}'),
            'edit' => EditCardTemplate::route('/{record}/edit'),
        ];
    }
}
