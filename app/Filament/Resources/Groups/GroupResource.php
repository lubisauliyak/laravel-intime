<?php

namespace App\Filament\Resources\Groups;

use App\Filament\Resources\Groups\Pages\ManageGroups;
use App\Filament\Resources\Groups\Schemas\GroupForm;
use App\Filament\Resources\Groups\Tables\GroupsTable;
use App\Models\Group;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $modelLabel = 'Grup';

    protected static ?string $pluralModelLabel = 'Grup';

    protected static ?string $navigationLabel = 'Grup';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return !auth()->user()->hasRole('operator');
    }

    public static function form(Schema $schema): Schema
    {
        return GroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        
        // Super Admin can always create
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        // Check if user has a group with a level
        $userLevelNumber = $user->group?->level?->level_number;
        
        if (!$userLevelNumber) {
            return false;
        }
        
        // Check if there are any levels below the user's level
        $lowestLevelNumber = \App\Models\Level::min('level_number');
        
        // If user is at the lowest level, they cannot create groups
        return $userLevelNumber > $lowestLevelNumber;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ($user->hasRole('admin') || $user->hasRole('operator')) && $user->group_id) {
            $descendantIds = $user->group->getAllDescendantIds();
            $ancestorIds = $user->group->getAllAncestorIds();
            
            $query->where(function ($q) use ($descendantIds, $ancestorIds) {
                // Show all descendants (including potential inactive ones the admin might need to fix)
                $q->whereIn('id', $descendantIds)
                  // But only show active ancestors
                  ->orWhere(function ($sq) use ($ancestorIds) {
                      $sq->whereIn('id', $ancestorIds)
                         ->where('status', true);
                  });
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGroups::route('/'),
        ];
    }
}
