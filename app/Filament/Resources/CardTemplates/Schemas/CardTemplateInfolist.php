<?php

namespace App\Filament\Resources\CardTemplates\Schemas;

use App\Filament\Resources\Groups\GroupResource;
use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Member;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group as SchemaGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class CardTemplateInfolist
{
    /**
     * Cache for template usage calculations during the request.
     *
     * @var array<string, array>
     */
    protected static array $usageCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaGroup::make([
                    Section::make('Informasi Template')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Nama Template')
                                ->weight(FontWeight::Bold),
                            IconEntry::make('is_active')
                                ->label('Aktif')
                                ->boolean(),
                            TextEntry::make('canvas_width')
                                ->label('Lebar Canvas (px)'),
                            TextEntry::make('canvas_height')
                                ->label('Tinggi Canvas (px)'),
                            TextEntry::make('description')
                                ->label('Deskripsi')
                                ->placeholder('Tidak ada deskripsi')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Section::make('Grup yang Menggunakan')
                        ->description('Informasi grup pengadopsi template ini beserta cakupan hierarki wilayahnya.')
                        ->icon('heroicon-o-user-group')
                        ->collapsed()
                        ->schema([
                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema([
                                    TextEntry::make('direct_group_name')
                                        ->label('Grup Penentu (Langsung)')
                                        ->state(function (CardTemplate $record) {
                                            return $record->groups->pluck('name')->all();
                                        })
                                        ->badge()
                                        ->color('success')
                                        ->icon('heroicon-o-check-badge')
                                        ->placeholder('Belum ada grup penentu')
                                        ->url(function ($state, CardTemplate $record) {
                                            $g = $record->groups->firstWhere('name', $state);
                                            return $g ? GroupResource::getUrl('view', ['record' => $g->uid]) : null;
                                        })
                                        ->openUrlInNewTab(),

                                    TextEntry::make('direct_group_level')
                                        ->label('Tingkat Grup Penentu')
                                        ->state(function (CardTemplate $record) {
                                            return $record->groups->map(fn ($g) => $g->level?->name)->filter()->unique()->values()->all();
                                        })
                                        ->badge()
                                        ->color('primary')
                                        ->placeholder('-'),
                                ]),

                            Grid::make(['default' => 1, 'sm' => 2])
                                ->schema([
                                    TextEntry::make('total_impacted_groups')
                                        ->label('Total Grup Terdampak')
                                        ->state(function (CardTemplate $record) {
                                            $data = static::getUsageData($record);
                                            return $data['has_groups']
                                                ? "{$data['total_groups_count']} Grup"
                                                : '-';
                                        })
                                        ->badge()
                                        ->color(fn ($state) => $state !== '-' ? 'info' : 'gray')
                                        ->icon('heroicon-o-squares-2x2'),

                                    TextEntry::make('total_card_holders')
                                        ->label('Total Pemegang KTA')
                                        ->state(function (CardTemplate $record) {
                                            $data = static::getUsageData($record);
                                            if (!$data['has_groups']) {
                                                return '-';
                                            }
                                            $total = number_format($data['total_members_count'], 0, ',', '.');
                                            $active = number_format($data['active_members_count'], 0, ',', '.');
                                            return "{$total} Anggota ({$active} Aktif)";
                                        })
                                        ->badge()
                                        ->color(fn ($state) => $state !== '-' ? 'primary' : 'gray')
                                        ->icon('heroicon-o-identification'),
                                ]),

                            TextEntry::make('level_breakdown')
                                ->label('Rincian per Tingkat Hierarki')
                                ->state(function (CardTemplate $record) {
                                    $data = static::getUsageData($record);
                                    if (!$data['has_groups']) {
                                        return [];
                                    }
                                    $items = [];
                                    foreach ($data['level_breakdown'] as $level => $count) {
                                        $items[] = "{$count} {$level}";
                                    }
                                    return $items;
                                })
                                ->badge()
                                ->color('gray')
                                ->placeholder('-')
                                ->visible(fn (CardTemplate $record) => static::getUsageData($record)['has_groups']),

                            TextEntry::make('sub_regions')
                                ->label('Wilayah yang Mewarisi (Tingkat Desa / Sub-Induk)')
                                ->state(function (CardTemplate $record) {
                                    $data = static::getUsageData($record);
                                    return $data['sub_regions']->pluck('name')->all();
                                })
                                ->badge()
                                ->color('info')
                                ->placeholder('Tidak ada')
                                ->url(function ($state, CardTemplate $record) {
                                    $data = static::getUsageData($record);
                                    $sub = $data['sub_regions']->firstWhere('name', $state);
                                    return $sub ? GroupResource::getUrl('view', ['record' => $sub->uid]) : null;
                                })
                                ->openUrlInNewTab()
                                ->visible(fn (CardTemplate $record) => static::getUsageData($record)['has_groups'] && static::getUsageData($record)['sub_regions']->isNotEmpty()),
                        ]),
                ])
                    ->columnSpan(['default' => 3, 'lg' => 2]),

                Section::make('Pratinjau Kartu')
                    ->schema([
                        ImageEntry::make('preview_path')
                            ->label('')
                            ->hiddenLabel()
                            ->disk('public')
                            ->imageHeight(400)
                            ->extraImgAttributes(['class' => 'object-contain mx-auto']),
                    ])
                    ->columnSpan(['default' => 3, 'lg' => 1]),
            ])
            ->columns(['default' => 1, 'lg' => 3]);
    }

    /**
     * Compute and cache usage metrics for the given card template.
     */
    protected static function getUsageData(CardTemplate $template): array
    {
        if (isset(static::$usageCache[$template->id])) {
            return static::$usageCache[$template->id];
        }

        $directGroups = Group::where('card_template_id', $template->id)
            ->with('level')
            ->get();

        if ($directGroups->isEmpty()) {
            return static::$usageCache[$template->id] = [
                'has_groups' => false,
                'direct_groups' => collect(),
                'total_groups_count' => 0,
                'total_members_count' => 0,
                'active_members_count' => 0,
                'level_breakdown' => [],
                'sub_regions' => collect(),
            ];
        }

        $allGroupIds = [];
        foreach ($directGroups as $g) {
            $allGroupIds = array_merge($allGroupIds, $g->getAllDescendantIds());
        }
        $allGroupIds = array_unique($allGroupIds);

        $totalMembers = Member::whereIn('group_id', $allGroupIds)->count();
        $activeMembers = Member::whereIn('group_id', $allGroupIds)->where('status', true)->count();

        $allGroups = Group::whereIn('id', $allGroupIds)->with(['level', 'parent'])->get();

        $levelCounts = [];
        foreach ($allGroups as $g) {
            $lvlName = $g->level?->name ?? 'Lainnya';
            $levelCounts[$lvlName] = ($levelCounts[$lvlName] ?? 0) + 1;
        }

        $directGroupIds = $directGroups->pluck('id')->toArray();
        $subRegions = $allGroups->filter(function ($g) use ($directGroupIds) {
            return in_array($g->parent_id, $directGroupIds);
        })->values();

        return static::$usageCache[$template->id] = [
            'has_groups' => true,
            'direct_groups' => $directGroups,
            'all_group_ids' => $allGroupIds,
            'total_groups_count' => count($allGroupIds),
            'total_members_count' => $totalMembers,
            'active_members_count' => $activeMembers,
            'level_breakdown' => $levelCounts,
            'sub_regions' => $subRegions,
        ];
    }
}
