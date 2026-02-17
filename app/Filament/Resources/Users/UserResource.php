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

    protected static string|UnitEnum|null $navigationGroup = 'Akses Pengguna';

    protected static ?int $navigationSort = 1;

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
        $query = parent::getEloquentQuery()
            ->leftJoin('groups', 'users.group_id', '=', 'groups.id')
            ->leftJoin('levels', 'groups.level_id', '=', 'levels.id')
            ->select('users.*')
            ->orderByRaw('CASE WHEN users.group_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('levels.level_number', 'desc')
            ->orderByRaw("FIELD(users.role, 'super_admin', 'admin', 'operator')");

        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Super Admin sees everything
        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Non-Super Admins NEVER see Super Admins
        $query->where('role', '!=', config('filament-shield.super_admin.name', 'super_admin'));

        // Operator can ONLY see themselves
        if ($user->isOperator()) {
            return $query->where('id', $user->id);
        }

        // Admin sees users in their own group, descendant groups, OR users with no group
        if ($user->group_id) {
            $allowedGroupIds = $user->group->getAllDescendantIds();
            $query->where(function($q) use ($allowedGroupIds) {
                $q->whereIn('users.group_id', $allowedGroupIds)
                  ->orWhereNull('users.group_id');
            });
            return $query;
        }

        // If user has no group and is not super_admin, they can only see themselves
        return $query->where('users.id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
