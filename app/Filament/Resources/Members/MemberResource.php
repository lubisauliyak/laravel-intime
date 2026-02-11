<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $pluralLabel = 'Anggota';

    protected static ?string $modelLabel = 'Anggota';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Keanggotaan';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function canViewAny(): bool
    {
        return !auth()->user()->hasRole('operator');
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Anggota')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('full_name')
                                    ->label('Nama Lengkap'),
                                \Filament\Infolists\Components\TextEntry::make('member_code')
                                    ->label('ID Anggota')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                \Filament\Infolists\Components\TextEntry::make('group.name')
                                    ->label('Kelompok'),
                                \Filament\Infolists\Components\TextEntry::make('ageGroup.name')
                                    ->label('Kategori Usia'),
                                \Filament\Infolists\Components\TextEntry::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->formatStateUsing(fn ($state) => $state === 'male' ? 'LAKI-LAKI' : 'PEREMPUAN'),
                                \Filament\Infolists\Components\IconEntry::make('status')
                                    ->label('Status Aktif')
                                    ->boolean(),
                            ]),
                    ])->columnSpan(['default' => 'full', 'lg' => 2]),
                \Filament\Schemas\Components\Section::make('QR Identity')
                    ->schema([
                        \Filament\Infolists\Components\ImageEntry::make('qr_code_path')
                            ->label('QR Code')
                            ->hiddenLabel()
                            ->square()
                            ->width('100%')
                            ->height('auto')
                            ->disk('public')
                            ->extraImgAttributes([
                                'class' => 'mx-auto max-w-[200px] md:max-w-[250px]',
                                'style' => 'image-rendering: auto;',
                            ]),
                        \Filament\Infolists\Components\TextEntry::make('member_code')
                            ->label('Scan kode ini untuk absensi')
                            ->alignCenter()
                            ->color('gray')
                            ->extraAttributes(['class' => 'text-xs md:text-sm']),
                    ])->columnSpan(['default' => 'full', 'lg' => 1]),
            ])->columns(['default' => 1, 'lg' => 3]);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'group.level', 
                'group.parent.level', 
                'group.parent.parent.level',
                'group.parent.parent.parent.level',
                'group.parent.parent.parent.parent.level',
                'ageGroup'
            ]);
        $user = auth()->user();

        if ($user && ($user->hasRole('admin') || $user->hasRole('operator')) && $user->group_id) {
            $descendantGroupIds = $user->group->getAllDescendantIds();
            $query->whereIn('group_id', $descendantGroupIds);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
