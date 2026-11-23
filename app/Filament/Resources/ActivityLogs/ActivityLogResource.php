<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogTable;
use App\Models\ActivityLog;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?string $modelLabel = 'Log Aktivitas';

    protected static ?string $pluralModelLabel = 'Log Aktivitas';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static string|UnitEnum|null $navigationGroup = 'Akses Pengguna';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return ActivityLogTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['group']);

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $manageableGroupIds = $user->getManageableGroupIds();

        if (empty($manageableGroupIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('activity_logs.group_id', $manageableGroupIds);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
