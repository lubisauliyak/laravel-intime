<?php

namespace App\Filament\Resources\Levels;

use App\Filament\Resources\Levels\Pages\ManageLevels;
use App\Models\Level;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use UnitEnum;

class LevelResource extends Resource
{
    protected static ?string $model = Level::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Konfigurasi Sistem';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Tingkatan Grup';

    protected static ?string $pluralModelLabel = 'Tingkatan Grup';

    protected static ?string $navigationLabel = 'Tingkatan Grup';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Tingkatan')
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state)),
                TextInput::make('code')
                    ->label('Kode')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state))
                    ->validationMessages([
                        'unique' => 'Kode tingkatan ini sudah digunakan. Mohon gunakan kode lain yang berbeda.',
                    ]),
                TextInput::make('level_number')
                    ->label('Urutan')
                    ->required()
                    ->numeric()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Angka hirarki ini sudah terdaftar. Silakan gunakan angka lain untuk membedakan urutan tingkatan.',
                    ])
                    ->helperText('Angka hirarki harus unik. Angka lebih besar berarti posisi lebih tinggi.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('level_number', 'desc')
            ->columns([
                TextColumn::make('level_number')
                    ->label('Tingkatan Grup')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('name')
                    ->label('Nama Tingkatan')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('groups_count')
                    ->label('Jumlah')
                    ->counts('groups')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Tempat Sampah'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Ubah'),
                    DeleteAction::make()
                        ->label('Hapus'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                    RestoreBulkAction::make()
                        ->label('Pulihkan Terpilih'),
                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen Terpilih'),
                ])->label('Aksi Massal'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLevels::route('/'),
        ];
    }
}
