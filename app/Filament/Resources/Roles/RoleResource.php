<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use BezhanSalleh\FilamentShield\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use BezhanSalleh\PluginEssentials\Concerns\Resource as Essentials;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class RoleResource extends Resource
{
    use Essentials\BelongsToParent;
    use Essentials\BelongsToTenant;
    use Essentials\HasGlobalSearch;
    use Essentials\HasLabels;
    use Essentials\HasNavigation;
    use HasShieldFormComponents;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public static function getActiveNavigationIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament-shield::filament-shield.field.name'))
                                    ->unique(
                                        ignoreRecord: true, /** @phpstan-ignore-next-line */
                                        modifyRuleUsing: fn(Unique $rule): Unique => Utils::isTenancyEnabled() ? $rule->where(Utils::getTenantModelForeignKey(), Filament::getTenant()?->id) : $rule
                                    )
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn ($record) => $record && $record->name === config('filament-shield.super_admin.name', 'super_admin'))
                                    ->helperText(fn ($record) => $record && $record->name === config('filament-shield.super_admin.name', 'super_admin') ? 'Nama role Super Admin dikunci oleh sistem demi integritas otorisasi.' : null),

                                TextInput::make('guard_name')
                                    ->label(__('filament-shield::filament-shield.field.guard_name'))
                                    ->default(Utils::getFilamentAuthGuard())
                                    ->nullable()
                                    ->maxLength(255),

                                Select::make(config('permission.column_names.team_foreign_key'))
                                    ->label(__('filament-shield::filament-shield.field.team'))
                                    ->placeholder(__('filament-shield::filament-shield.field.team.placeholder'))
                                    /** @phpstan-ignore-next-line */
                                    ->default(Filament::getTenant()?->id)
                                    ->options(fn(): array => in_array(Utils::getTenantModel(), [null, '', '0'], true) ? [] : Utils::getTenantModel()::pluck('name', 'id')->toArray())
                                    ->visible(fn(): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled())
                                    ->dehydrated(fn(): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),
                                static::getSelectAllFormComponent(),

                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                static::getShieldFormComponents(),
            ]);
    }

    public static function getShieldFormComponents(): \Filament\Schemas\Components\Component
    {
        return \Filament\Schemas\Components\Section::make('Manajemen Data & Fitur')
            ->description('Hak akses penuh terhadap Master Data dan Fitur Terkait.')
            ->schema([
                \Filament\Schemas\Components\Section::make('Beranda')
                    ->description('Hak akses untuk halaman utama dan metrik tingkat atas.')
                    ->schema([
                        static::getCheckboxListFormComponent('beranda_tab', [
                            'View:Dashboard' => 'Lihat',
                            'View:QuickActionsWidget' => 'Widget - Aksi Cepat',
                            'View:TodayAttendanceOverview' => 'Widget - Ringkasan Kehadiran',
                            'View:TodayAttendanceTrend' => 'Widget - Statistik Tren Kehadiran',
                        ], false)
                    ])
                    ->collapsible(),

                \Filament\Schemas\Components\Grid::make()
                    ->schema(static::getResourceEntitiesSchema())
                    ->columns(static::shield()->getGridColumns())
            ])
            ->collapsible()
            ->visible(fn(): bool => \BezhanSalleh\FilamentShield\Support\Utils::isResourceTabEnabled())
            ->columnSpanFull();
    }

    public static function getResourceEntitiesSchema(): ?array
    {
        return collect(\BezhanSalleh\FilamentShield\Facades\FilamentShield::getResources())
            ->map(function (array $entity): \Filament\Schemas\Components\Section {
                $sectionLabel = strval(
                    static::shield()->hasLocalizedPermissionLabels()
                    ? \BezhanSalleh\FilamentShield\Facades\FilamentShield::getLocalizedResourceLabel($entity['resourceFqcn'])
                    : $entity['model']
                );

                $options = static::getResourcePermissionOptions($entity);

                // Inject Custom Permissions and Widgets into Pertemuan (MeetingResource)
                if ($entity['resourceFqcn'] === \App\Filament\Resources\Meetings\MeetingResource::class) {

                    // Add Custom Permissions
                    $customPerms = \BezhanSalleh\FilamentShield\Facades\FilamentShield::getCustomPermissions(static::shield()->hasLocalizedPermissionLabels());
                    if (isset($customPerms['SetExcusedAttendance'])) {
                        $options['SetExcusedAttendance'] = 'Fitur - Input Izin dan Sakit';
                    }

                    // Add All Widgets Except QuickActions & Today's Overviews
                    $widgets = static::getWidgetOptions();
                    unset($widgets['View:QuickActionsWidget']);
                    unset($widgets['View:TodayAttendanceOverview']);
                    unset($widgets['View:TodayAttendanceTrend']);

                    $widgetTranslations = [
                        'View:AgeGroupParticipationWidget' => 'Widget - Partisipasi Kelompok Umur',
                        'View:AttendanceOverview' => 'Widget - Ringkasan Kehadiran Global',
                        'View:AttendanceTrend' => 'Widget - Tren Kehadiran Global',
                        'View:EarlyScansWidget' => 'Widget - Statistik Scan Awal',
                        'View:GenderActivityWidget' => 'Widget - Aktivitas Berdasarkan Gender',
                        'View:GroupRanking' => 'Widget - Peringkat Kelompok',
                        'View:PunctualityScansWidget' => 'Widget - Scan Ketepatan Waktu',
                        'View:PunctualityStatsWidget' => 'Widget - Statistik Ketepatan Waktu',
                        'View:RecentScansWidget' => 'Widget - Scan Terbaru',
                        'View:ScanningPeakTimeWidget' => 'Widget - Waktu Sibuk Scan',
                        'View:TargetGenderDistributionWidget' => 'Widget - Distribusi Gender Target',
                        'View:TargetPopulationWidget' => 'Widget - Populasi Target',
                        'View:TopInsightWidget' => 'Widget - Wawasan Teratas',
                    ];

                    foreach ($widgets as $key => $label) {
                        $options[$key] = $widgetTranslations[$key] ?? 'Widget - ' . str_replace('View Widget ', '', $label);
                    }
                }

                return \Filament\Schemas\Components\Section::make($sectionLabel)
                    ->description(fn(): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('<span style="word-break: break-word;">' . \BezhanSalleh\FilamentShield\Support\Utils::showModelPath($entity['modelFqcn']) . '</span>'))
                    ->compact()
                    ->schema([
                        static::getCheckboxListFormComponent(
                            name: $entity['resourceFqcn'],
                            options: $options,
                            searchable: false,
                            columns: static::shield()->getResourceCheckboxListColumns(),
                            columnSpan: static::shield()->getResourceCheckboxListColumnSpan()
                        ),
                    ])
                    ->columnSpan(static::shield()->getSectionColumnSpan())
                    ->collapsible();
            })
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->formatStateUsing(fn(string $state): string => Str::headline($state))
                    ->searchable(),
                TextColumn::make('guard_name')
                    ->badge()
                    ->color('warning')
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                TextColumn::make('team.name')
                    ->default('Global')
                    ->badge()
                    ->color(fn(mixed $state): string => str($state)->contains('Global') ? 'gray' : 'primary')
                    ->label(__('filament-shield::filament-shield.column.team'))
                    ->searchable()
                    ->visible(fn(): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),
                TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->color('primary'),
                TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->disabled(fn ($record) => $record->name === config('filament-shield.super_admin.name', 'super_admin') && ! (auth()->user()?->isSuperAdmin()))
                    ->tooltip(fn ($record) => $record->name === config('filament-shield.super_admin.name', 'super_admin') && ! (auth()->user()?->isSuperAdmin()) ? 'Hanya Super Admin yang dapat mengubah role ini.' : null),
                DeleteAction::make()
                    ->disabled(fn ($record) => $record->name === config('filament-shield.super_admin.name', 'super_admin'))
                    ->tooltip(fn ($record) => $record->name === config('filament-shield.super_admin.name', 'super_admin') ? 'Role Super Admin dilindungi sistem dan tidak dapat dihapus.' : null),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');
                        $filtered = $records->reject(fn ($record) => $record->name === $superAdminName);
                        $rejectedCount = $records->count() - $filtered->count();

                        $filtered->each->delete();

                        if ($rejectedCount > 0) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Aksi Massal Selesai dengan Catatan')
                                ->body("{$filtered->count()} role berhasil dihapus. {$rejectedCount} role (Super Admin) dilindungi sistem dan dilewati.")
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Berhasil Dihapus')
                                ->body("{$filtered->count()} role berhasil dihapus.")
                                ->send();
                        }
                    }),
            ]);
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getModel(): string
    {
        return Utils::getRoleModel();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return Utils::getResourceSlug();
    }

    public static function getCluster(): ?string
    {
        return Utils::getResourceCluster();
    }

    public static function getEssentialsPlugin(): ?FilamentShieldPlugin
    {
        return FilamentShieldPlugin::get();
    }
}
