<?php

namespace App\Filament\Resources\CardTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class CardTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview_path')
                    ->label('Pratinjau')
                    ->disk('public')
                    ->height(52)
                    ->width(33)
                    ->alignCenter()
                    ->extraImgAttributes(['class' => 'rounded shadow-xs object-cover border border-gray-200 dark:border-gray-700'])
                    ->defaultImageUrl(fn($record) => $record->background_path ? asset('storage/' . $record->background_path) : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="66" height="104" viewBox="0 0 66 104" fill="%23f3f4f6"><rect width="66" height="104" rx="4" fill="%23f1f5f9"/><rect x="13" y="16" width="40" height="40" rx="20" fill="%23cbd5e1"/><rect x="10" y="66" width="46" height="6" rx="3" fill="%23cbd5e1"/><rect x="16" y="78" width="34" height="5" rx="2.5" fill="%23cbd5e1"/></svg>'),
                TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('dimensions')
                    ->label('Ukuran Canvas')
                    ->state(fn($record) => $record->canvas_width . ' x ' . $record->canvas_height . ' px')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter()
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([

                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('editor')
                        ->label('Buka Editor')
                        ->icon('heroicon-o-paint-brush')
                        ->color('info')
                        ->tooltip('Buka Canvas Editor di tab baru')
                        ->url(fn($record) => url('/card-editor/' . $record->id))
                        ->openUrlInNewTab()
                        ->visible(fn($record) => auth()->user()?->can('openEditor', $record)),
                    DeleteAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
