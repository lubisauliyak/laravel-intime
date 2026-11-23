<?php

namespace App\Filament\Resources\CardTemplates\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CardTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Template')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Template')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('canvas_width')
                            ->label('Lebar (px)')
                            ->required()
                            ->numeric()
                            ->default(600),
                        TextInput::make('canvas_height')
                            ->label('Tinggi (px)')
                            ->required()
                            ->numeric()
                            ->default(950),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(false),
                    ])->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
