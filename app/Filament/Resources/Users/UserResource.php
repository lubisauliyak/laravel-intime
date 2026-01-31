<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UserTable;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static string|UnitEnum|null $navigationGroup = 'Keanggotaan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Super Admin sees everything
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Non-Super Admins NEVER see Super Admins
        $query->where('role', '!=', 'super_admin');

        // Admin/Operator sees users in their own group, descendant groups, OR users with no group
        if ($user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            return $query->where(function($q) use ($allowedGroupIds) {
                $q->whereIn('group_id', $allowedGroupIds)
                  ->orWhereNull('group_id');
            });
        }

        // If user has no group and is not super_admin, they can only see themselves
        return $query->where('id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
