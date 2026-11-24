<?php

namespace App\Filament\Resources\AgeGroups;

use App\Filament\Resources\AgeGroups\Pages\ManageAgeGroups;
use App\Models\AgeGroup;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AgeGroupResource extends Resource
{
    protected static ?string $model = AgeGroup::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Konfigurasi Sistem';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralLabel = 'Kategori Usia';

    protected static ?string $modelLabel = 'Kategori Usia';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kategori')
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
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state)),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('min_age')
                    ->label('Usia Minimum')
                    ->numeric()
                    ->required(),
                TextInput::make('max_age')
                    ->label('Usia Maksimum')
                    ->numeric()
                    ->helperText('Kosongkan jika tidak ada batas atas (misal: Lansia)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'ASC')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('name')
                    ->label('Kategori Usia')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->alignCenter(),
                TextColumn::make('min_age')
                    ->label('Min')
                    ->numeric()
                    ->alignCenter()
                    ->suffix(' Thn'),
                TextColumn::make('max_age')
                    ->label('Max')
                    ->numeric()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state ?? '∞')
                    ->suffix(fn ($state) => $state ? ' Thn' : ''),
                TextColumn::make('members_count')
                    ->label('Jumlah')
                    ->counts('members')
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
            'index' => ManageAgeGroups::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
