<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('level_id')
                    ->relationship(
                        name: 'level',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query) {
                            $user = auth()->user();
                            
                            // Super Admin can select all levels
                            if ($user->hasRole('super_admin')) {
                                return $query->orderBy('level_number', 'desc');
                            }
                            
                            // Admin/Operator can only select levels BELOW their group's level
                            $userLevelNumber = $user->group?->level?->level_number;
                            
                            if ($userLevelNumber) {
                                return $query->where('level_number', '<', $userLevelNumber)
                                    ->orderBy('level_number', 'desc');
                            }
                            
                            // If user has no group, show no levels
                            return $query->whereRaw('1 = 0');
                        }
                    )
                    ->label('Tingkat Hirarki (Level)')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
                Select::make('parent_id')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query, Get $get) {
                            $levelId = $get('level_id');
                            if (! $levelId) {
                                return $query->whereRaw('1 = 0');
                            }

                            $level = \App\Models\Level::find($levelId);
                            if (! $level) {
                                return $query;
                            }

                            // Parent must be exactly one level higher (level_number + 1)
                            $targetLevelNumber = $level->level_number + 1;

                            return $query->whereHas('level', function ($q) use ($targetLevelNumber) {
                                $q->where('level_number', $targetLevelNumber);
                            });
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->label('Parent Group')
                    ->searchable()
                    ->preload()
                    ->placeholder(fn (Get $get) => $get('level_id') ? 'Pilih induk kelompok' : 'Pilih tingkat hirarki terlebih dahulu')
                    ->disabled(fn (Get $get) => ! $get('level_id')),
                TextInput::make('name')
                    ->label('Nama Grup')
                    ->required()
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state))
                    ->maxLength(255),
                Toggle::make('status')
                    ->label('Status Aktif Grup')
                    ->onColor('success')
                    ->offColor('danger')
                    ->required()
                    ->default(true),
            ]);
    }
}
