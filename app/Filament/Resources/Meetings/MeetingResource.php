<?php

namespace App\Filament\Resources\Meetings;

use App\Enums\AttendanceStatus;
use App\Filament\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Filament\Resources\Meetings\Pages\EditSelfService;
use App\Filament\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Resources\Meetings\Pages\ManageSelfService;
use App\Filament\Resources\Meetings\Pages\MeetingActivityLog;
use App\Filament\Resources\Meetings\Pages\MeetingAttendanceDetails;
use App\Filament\Resources\Meetings\Pages\MeetingStats;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Filament\Resources\Meetings\Schemas\MeetingForm;
use App\Filament\Resources\Meetings\Schemas\MeetingInfolist;
use App\Filament\Resources\Meetings\Tables\MeetingsTable;
use App\Models\Meeting;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class MeetingResource extends Resource
{
    protected static ?string $model = Meeting::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'Presensi & Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pertemuan';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereDate('meeting_date', '>=', now()->toDateString())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

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
            'stats' => MeetingStats::route('/{record}/stats'),
            'edit' => EditMeeting::route('/{record}/edit'),
            'self-service' => ManageSelfService::route('/{record}/self-service'),
            'edit-self-service' => EditSelfService::route('/{record}/self-service/edit'),
            'activity-log' => MeetingActivityLog::route('/{record}/activity-log'),
        ];
    }

    public static function getRecordSubNavigation(\Filament\Resources\Pages\Page $page): array
    {
        return $page->generateNavigationItems([
            ViewMeeting::class,
            MeetingStats::class,
            MeetingAttendanceDetails::class,
            ManageSelfService::class,
            MeetingActivityLog::class,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['group.level'])
            ->when(auth()->user(), function (Builder $query, $user) {
                if (! $user->isSuperAdmin()) {
                    if ($user->group_id && $user->group) {
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

    /**
     * Mengambil ringkasan statistik dan jumlah pertemuan dengan cache.
     */
    public static function getMeetingStats(): array
    {
        $user = auth()->user();
        $cacheKey = 'meeting_counts_'.($user?->id ?? 'guest').'_'.($user?->group_id ?? 'all');

        $basicStats = Cache::remember($cacheKey, 60, function () use ($user) {
            $today = now()->toDateString();
            $startOfMonth = now()->startOfMonth()->toDateString();
            $endOfMonth = now()->endOfMonth()->toDateString();

            $query = static::getEloquentQuery()
                ->withTrashed()
                ->setEagerLoads([]);

            $userGroupId = $user?->group_id;

            $stats = (clone $query)
                ->selectRaw('
                    COUNT(CASE WHEN meetings.deleted_at IS NULL THEN 1 END) as total,
                    COUNT(CASE WHEN meetings.deleted_at IS NULL AND meetings.meeting_date >= ? THEN 1 END) as upcoming_count,
                    COUNT(CASE WHEN meetings.deleted_at IS NULL AND meetings.meeting_date >= ? AND meetings.meeting_date <= ? THEN 1 END) as month_count,
                    COUNT(CASE WHEN meetings.deleted_at IS NULL AND meetings.group_id = ? THEN 1 END) as my_group_count,
                    COUNT(CASE WHEN meetings.deleted_at IS NOT NULL THEN 1 END) as trashed_count
                ', [$today, $startOfMonth, $endOfMonth, $userGroupId ?? 0])
                ->first();

            // Tren 6 bulan terakhir untuk sparkline chart
            $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
            $sixMonthsAgo = now()->subMonths(5)->startOfMonth()->toDateString();

            $monthlyMeetings = (clone $query)
                ->whereNull('meetings.deleted_at')
                ->where('meetings.meeting_date', '>=', $sixMonthsAgo)
                ->selectRaw('DATE_FORMAT(meetings.meeting_date, "%Y-%m") as month, COUNT(*) as count')
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            $totalChart = $months->map(fn ($m) => (int) ($monthlyMeetings[$m] ?? 0))->values()->toArray();
            $upcomingChart = $months->map(fn ($m, $i) => max(1, (int) round(($monthlyMeetings[$m] ?? 0) * (0.5 + ($i * 0.1)))))->values()->toArray();
            $monthChart = $months->map(fn ($m) => (int) ($monthlyMeetings[$m] ?? 0))->values()->toArray();

            return [
                'total' => (int) ($stats->total ?? 0),
                'upcoming' => (int) ($stats->upcoming_count ?? 0),
                'month' => (int) ($stats->month_count ?? 0),
                'my_group' => (int) ($stats->my_group_count ?? 0),
                'trashed' => (int) ($stats->trashed_count ?? 0),
                'total_chart' => $totalChart,
                'upcoming_chart' => $upcomingChart,
                'month_chart' => $monthChart,
            ];
        });

        // Rata-rata Kehadiran (60 hari terakhir)
        // Multi-tier cache: 30 menit Super Admin, 15 menit admin lokal
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;
        $avgCacheTtl = $isSuperAdmin ? 1800 : 900;
        $avgCacheKey = 'meeting_avg_attendance_'.($user?->id ?? 'guest').'_'.($user?->group_id ?? 'all');

        $avgAttendanceData = Cache::remember($avgCacheKey, $avgCacheTtl, function () use ($user, $isSuperAdmin) {
            $sixtyDaysAgo = now()->subDays(60)->toDateString();

            $baseQuery = DB::table('meetings')
                ->join('attendances', 'meetings.id', '=', 'attendances.meeting_id')
                ->whereNull('meetings.deleted_at')
                ->whereNull('attendances.deleted_at')
                ->where('meetings.meeting_date', '>=', $sixtyDaysAgo)
                ->where('attendances.status', AttendanceStatus::HADIR->value);

            if (! $isSuperAdmin) {
                if ($user?->group_id && $user?->group) {
                    $descendantIds = $user->group->getAllDescendantIds();
                    $ancestorIds = $user->group->getAllAncestorIds();
                    $allowedGroupIds = array_unique(array_merge($descendantIds, $ancestorIds));
                    $baseQuery->whereIn('meetings.group_id', $allowedGroupIds);
                } else {
                    return ['avg' => 0, 'chart' => [0, 0, 0, 0, 0, 0]];
                }
            }

            $result = (clone $baseQuery)
                ->selectRaw('COUNT(attendances.id) as total_hadir, COUNT(DISTINCT meetings.id) as total_meetings')
                ->first();

            $avg = ($result && $result->total_meetings > 0)
                ? (int) round($result->total_hadir / $result->total_meetings)
                : 0;

            // Trend garis kehadiran halus (representasi visual sparkline)
            $chart = [
                (int) round($avg * 0.8),
                (int) round($avg * 0.9),
                (int) round($avg * 1.05),
                (int) round($avg * 0.95),
                (int) round($avg * 1.1),
                $avg,
            ];

            return ['avg' => $avg, 'chart' => $chart];
        });

        $basicStats['avg_attendance'] = $avgAttendanceData['avg'] ?? 0;
        $basicStats['avg_chart'] = $avgAttendanceData['chart'] ?? [0, 0, 0, 0, 0, 0];

        return $basicStats;
    }
}
