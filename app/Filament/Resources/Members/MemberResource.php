<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ImportMembers;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Pages\MemberMeetingHistory;
use App\Filament\Resources\Members\Pages\MemberActivityLog;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Schemas\MemberInfolist;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use BackedEnum;
use UnitEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $pluralLabel = 'Anggota';

    protected static ?string $modelLabel = 'Anggota';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('is_permanent', false)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MemberInfolist::configure($schema);
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'group.level', 
                'group.parent.level', 
                'group.parent.parent.level',
                'group.parent.parent.parent.level',
                'group.parent.parent.parent.parent.level',
                'ageGroup',
                'positions.group.level',
                'positions.category',
            ]);
        $user = auth()->user();

        if ($user && !$user->isSuperAdmin()) {
            if ($user->group_id && $user->group) {
                $descendantGroupIds = $user->getManageableGroupIds();
                $query->whereIn('members.group_id', $descendantGroupIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    /**
     * Mengambil ringkasan statistik dan tren anggota dengan cache 60 detik.
     */
    public static function getMemberCountsAndTrends(): array
    {
        $user = auth()->user();
        $cacheKey = 'member_counts_trends_' . ($user?->id ?? 'guest') . '_' . ($user?->group_id ?? 'all');

        return Cache::remember($cacheKey, 60, function () {
            $query = static::getEloquentQuery()
                ->withTrashed()
                ->setEagerLoads([]);

            $stats = (clone $query)
                ->selectRaw('
                    COUNT(CASE WHEN members.deleted_at IS NULL THEN 1 END) as total,
                    COUNT(CASE WHEN members.deleted_at IS NULL AND members.status = 1 AND members.is_permanent = 1 THEN 1 END) as active_count,
                    COUNT(CASE WHEN members.deleted_at IS NULL AND members.is_permanent = 0 THEN 1 END) as pending_count,
                    COUNT(CASE WHEN members.deleted_at IS NULL AND members.status = 0 THEN 1 END) as inactive_count,
                    COUNT(CASE WHEN members.deleted_at IS NOT NULL THEN 1 END) as trashed_count
                ')
                ->first();

            // Tren registrasi anggota 6 bulan terakhir untuk sparkline
            $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
            $trendsQuery = (clone $query)
                ->whereNull('members.deleted_at')
                ->where('members.created_at', '>=', now()->subMonths(5)->startOfMonth())
                ->selectRaw('
                    DATE_FORMAT(members.created_at, "%Y-%m") as month,
                    COUNT(*) as total,
                    COUNT(CASE WHEN members.status = 1 AND members.is_permanent = 1 THEN 1 END) as active,
                    COUNT(CASE WHEN members.is_permanent = 0 THEN 1 END) as pending,
                    COUNT(CASE WHEN members.status = 0 THEN 1 END) as inactive
                ')
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            $totalChart = $months->map(fn ($m) => (int) ($trendsQuery[$m]->total ?? 0))->values()->toArray();
            $activeChart = $months->map(fn ($m) => (int) ($trendsQuery[$m]->active ?? 0))->values()->toArray();
            $pendingChart = $months->map(fn ($m) => (int) ($trendsQuery[$m]->pending ?? 0))->values()->toArray();
            $inactiveChart = $months->map(fn ($m) => (int) ($trendsQuery[$m]->inactive ?? 0))->values()->toArray();

            return [
                'total' => (int) ($stats->total ?? 0),
                'active' => (int) ($stats->active_count ?? 0),
                'pending' => (int) ($stats->pending_count ?? 0),
                'inactive' => (int) ($stats->inactive_count ?? 0),
                'trashed' => (int) ($stats->trashed_count ?? 0),
                'total_chart' => $totalChart,
                'active_chart' => $activeChart,
                'pending_chart' => $pendingChart,
                'inactive_chart' => $inactiveChart,
            ];
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'import' => ImportMembers::route('/import'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
            'meeting-history' => MemberMeetingHistory::route('/{record}/meeting-history'),
            'activity-log' => MemberActivityLog::route('/{record}/activity-log'),
        ];
    }

    public static function getRecordSubNavigation(\Filament\Resources\Pages\Page $page): array
    {
        return $page->generateNavigationItems([
            ViewMember::class,
            MemberMeetingHistory::class,
            MemberActivityLog::class,
        ]);
    }
}
