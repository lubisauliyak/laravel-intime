<?php

namespace App\Filament\Resources\Meetings;

use App\Filament\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Filament\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Filament\Resources\Meetings\Pages\MeetingAttendanceDetails;
use App\Filament\Resources\Meetings\Schemas\MeetingForm;
use App\Filament\Resources\Meetings\Schemas\MeetingInfolist;
use App\Filament\Resources\Meetings\Tables\MeetingsTable;
use App\Filament\Resources\Meetings\RelationManagers;
use App\Models\Meeting;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MeetingResource extends Resource
{
    protected static ?string $model = Meeting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static string|UnitEnum|null $navigationGroup = 'Presensi & Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pertemuan';

    protected static ?string $pluralModelLabel = 'Pertemuan';

    protected static ?string $navigationLabel = 'Pertemuan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MeetingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MeetingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeetings::route('/'),
            'create' => CreateMeeting::route('/create'),
            'attendance-details' => MeetingAttendanceDetails::route('/{record}/attendance-details'),
            'view' => ViewMeeting::route('/{record}'),
            'edit' => EditMeeting::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(auth()->user(), function (Builder $query, $user) {
                if (!$user->isSuperAdmin()) {
                    if ($user->group_id) {
                        $descendantIds = $user->group->getAllDescendantIds();
                        $ancestorIds = $user->group->getAllAncestorIds();
                        $allowedGroupIds = array_unique(array_merge($descendantIds, $ancestorIds));
                        
                        $query->whereIn('group_id', $allowedGroupIds);
                    } else {
                        // Non-super admin with no group sees nothing
                        $query->whereRaw('1 = 0');
                    }
                }
            });
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
