<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Enums\Gender;
use App\Filament\Resources\Members\MemberResource;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Member;
use App\Models\MemberPosition;
use App\Models\PositionCategory;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class GroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Grup')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Grup')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                TextEntry::make('full_name')
                                    ->label('Nama Lengkap')
                                    ->color('gray'),
                                TextEntry::make('level.name')
                                    ->label('Tingkat Hirarki')
                                    ->badge()
                                    ->color(fn (Group $record): string => $record->level?->getColor() ?? 'gray'),
                                TextEntry::make('parent.full_name')
                                    ->label('Induk Grup')
                                    ->placeholder('-')
                                    ->color('gray'),
                                TextEntry::make('code')
                                    ->label('Kode Grup')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('Tidak Ada'),
                                TextEntry::make('calculated_prefix')
                                    ->label('Prefix ID Terhitung')
                                    ->state(fn ($record) => app(\App\Services\Groups\GroupHierarchyService::class)->getCalculatedPrefix($record) ?? 'Tanpa ID')
                                    ->badge()
                                    ->color(fn ($state) => $state !== 'Tanpa ID' ? 'success' : 'gray'),
                                TextEntry::make('effective_template')
                                    ->label('Template KTA')
                                    ->state(function ($record) {
                                        $res = app(\App\Services\Groups\GroupHierarchyService::class)->getEffectiveCardTemplate($record);
                                        if (! $res['template']) {
                                            return 'Belum ditentukan';
                                        }
                                        $name = $res['template']->name;

                                        return $res['is_locked']
                                            ? "{$name} (🔒{$res['inherited_from']?->name} )"
                                            : "{$name}";
                                    }),
                                IconEntry::make('status')
                                    ->label('Status Aktif')
                                    ->boolean(),
                                TextEntry::make('created_at')
                                    ->label('Dibuat')
                                    ->dateTime('d M Y H:i'),
                                TextEntry::make('updated_at')
                                    ->label('Diperbarui')
                                    ->dateTime('d M Y H:i'),
                            ]),
                    ])->columnSpan(['default' => 'full', 'lg' => 2]),

                Section::make('Statistik')
                    ->schema([
                        Grid::make(['default' => 2, 'md' => 2])
                            ->schema([
                                TextEntry::make('members_count')
                                    ->label('Total Anggota')
                                    ->state(fn (Group $record) => Member::whereIn('group_id', $record->getAllDescendantIds())->count())
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-user-group'),
                                TextEntry::make('children_count')
                                    ->label('Sub Grup')
                                    ->state(fn (Group $record) => $record->children()->count())
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-o-squares-2x2'),
                                TextEntry::make('users_count')
                                    ->label('Admin / Pengguna')
                                    ->state(fn (Group $record) => $record->users()->count())
                                    ->badge()
                                    ->color('warning')
                                    ->icon('heroicon-o-shield-check'),
                                TextEntry::make('meetings_created_count')
                                    ->label('Pertemuan Dibuat')
                                    ->state(fn (Group $record) => $record->meetings()->count())
                                    ->badge()
                                    ->color('gray')
                                    ->icon('heroicon-o-calendar-days'),
                                TextEntry::make('meetings_active_count')
                                    ->label('Pertemuan Aktif')
                                    ->state(fn (Group $record) => $record->meetings()->where('meeting_date', '>=', now())->count())
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('meetings_attended_count')
                                    ->label('Pertemuan Diikuti')
                                    ->state(fn (Group $record) => \App\Models\Attendance::whereHas('member', function ($q) use ($record) {
                                        $q->whereIn('group_id', $record->getAllDescendantIds());
                                    })->distinct('meeting_id')->count('meeting_id'))
                                    ->badge()
                                    ->color('emerald')
                                    ->icon('heroicon-o-check-badge'),
                            ]),
                    ])->columnSpan(['default' => 'full', 'lg' => 1]),

                Section::make('Komposisi Anggota')
                    ->description('Persebaran komposisi anggota aktif dari grup ini.')
                    ->schema([
                        Section::make('Populasi Berdasarkan Jenis Kelamin')
                            ->compact()
                            ->schema([
                                Grid::make(['default' => 2, 'md' => 3])
                                    ->schema([
                                        TextEntry::make('male_count_comp')
                                            ->label('Laki-laki')
                                            ->state(fn (Group $record) => static::computeGenderWithPct($record, Gender::MALE))
                                            ->badge()
                                            ->color('info')
                                            ->icon('heroicon-o-user'),
                                        TextEntry::make('female_count_comp')
                                            ->label('Perempuan')
                                            ->state(fn (Group $record) => static::computeGenderWithPct($record, Gender::FEMALE))
                                            ->badge()
                                            ->color('danger')
                                            ->icon('heroicon-o-user'),
                                        TextEntry::make('total_member_comp')
                                            ->label('Total Anggota')
                                            ->state(fn (Group $record) => Member::whereIn('group_id', $record->getAllDescendantIds())->count())
                                            ->badge()
                                            ->color('primary')
                                            ->icon('heroicon-o-user-group'),
                                    ]),
                            ]),

                        Section::make('Populasi Berdasarkan Kategori Usia')
                            ->compact()
                            ->schema([
                                Grid::make(['default' => 2, 'md' => 3, 'lg' => 5])
                                    ->schema(static::getAgeGroupEntries()),
                            ]),

                        Section::make('Populasi Berdasarkan Kategori Usia per Jenis Kelamin')
                            ->compact()
                            ->schema(
                                AgeGroup::orderBy('sort_order')->get()->map(function (AgeGroup $ag) {
                                    $agId = $ag->id;

                                    return Grid::make(['default' => 1, 'sm' => 4])
                                        ->schema([
                                            TextEntry::make("ct_name_{$agId}")
                                                ->label('Kategori')
                                                ->extraAttributes(['class' => 'font-semibold text-sm'])
                                                ->state($ag->name)
                                                ->badge()
                                                ->color($ag->getColor()),
                                            TextEntry::make("ct_male_{$agId}")
                                                ->label('L')
                                                ->state(fn (Group $record) => static::computeGenderInAgeGroup($record, $agId, Gender::MALE))
                                                ->badge()
                                                ->icon('heroicon-o-user')
                                                ->color('info'),
                                            TextEntry::make("ct_female_{$agId}")
                                                ->label('P')
                                                ->state(fn (Group $record) => static::computeGenderInAgeGroup($record, $agId, Gender::FEMALE))
                                                ->badge()
                                                ->icon('heroicon-o-user')
                                                ->color('danger'),
                                            TextEntry::make("ct_total_{$agId}")
                                                ->label('Total')
                                                ->state(fn (Group $record) => static::computeAgeGroupOverallPct($record, $agId))
                                                ->badge()
                                                ->icon('heroicon-o-user-group')
                                                ->color('gray'),
                                        ]);
                                })->toArray()
                            ),

                        Section::make('Populasi Berdasarkan Status Anggota')
                            ->compact()
                            ->schema([
                                Grid::make(['default' => 2, 'md' => 4])
                                    ->schema([
                                        TextEntry::make('target_wajib_count')
                                            ->label('Anggota Aktif')
                                            ->state(fn (Group $record) => static::computeStatusPct($record, true))
                                            ->badge()
                                            ->color('info')
                                            ->icon('heroicon-o-clipboard-document-check'),
                                        TextEntry::make('permanent_count')
                                            ->label('Terverifikasi')
                                            ->state(fn (Group $record) => static::computeIsPermanentPct($record, true))
                                            ->badge()
                                            ->color('success')
                                            ->icon('heroicon-o-check-badge'),
                                        TextEntry::make('non_permanent_count')
                                            ->label('Menunggu Verifikasi')
                                            ->state(fn (Group $record) => static::computeIsPermanentPct($record, false))
                                            ->badge()
                                            ->color('warning')
                                            ->icon('heroicon-o-clock'),
                                        TextEntry::make('non_target_count')
                                            ->label('Kurang Aktif')
                                            ->state(fn (Group $record) => static::computeStatusPct($record, false))
                                            ->badge()
                                            ->color('gray')
                                            ->icon('heroicon-o-user-minus'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),

                Section::make('Komposisi Kepengurusan')
                    ->description('Struktur dan susunan pengurus pada grup ini.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->schema([
                                TextEntry::make('pengurus_this_group_count')
                                    ->label('Pengurus Tingkat Ini')
                                    ->state(function (Group $record) {
                                        $record->loadMissing(['positions.member.group', 'positions.category']);

                                        return $record->positions->count();
                                    })
                                    ->badge()
                                    ->color('warning')
                                    ->icon('heroicon-o-star'),

                                TextEntry::make('pengurus_hierarchy_count')
                                    ->label('Total Pengurus Hierarki')
                                    ->state(fn (Group $record) => static::computeMembershipTypeCount($record, \App\Enums\MembershipType::PENGURUS))
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-o-user-group'),

                                TextEntry::make('active_categories')
                                    ->label('Kategori Pengurus')
                                    ->state(function (Group $record) {
                                        $record->loadMissing(['positions.member.group', 'positions.category']);
                                        $categories = $record->positions->pluck('category.name')->filter()->unique()->values()->all();

                                        return ! empty($categories) ? $categories : ['-'];
                                    })
                                    ->badge()
                                    ->color(fn ($state): string => PositionCategory::getColorMapping()[(string) $state] ?? 'primary'),
                            ]),

                        RepeatableEntry::make('positions')
                            ->label('Daftar Susunan Pengurus')
                            ->table([
                                TableColumn::make('Nama Anggota'),
                                TableColumn::make('Pengurus'),
                                TableColumn::make('Kategori'),
                                TableColumn::make('Kelompok'),
                            ])
                            ->schema([
                                TextEntry::make('member.full_name')
                                    ->weight(FontWeight::Bold)
                                    ->color(fn (?MemberPosition $record): ?string => $record?->member?->getMemberColor())
                                    ->tooltip(fn (?MemberPosition $record): ?string => $record?->member?->getMemberColorTooltip())
                                    ->url(fn (?MemberPosition $record) => $record?->member ? MemberResource::getUrl('view', ['record' => $record->member->uid]) : null)
                                    ->openUrlInNewTab(),

                                TextEntry::make('position_name'),

                                TextEntry::make('category.name'),

                                TextEntry::make('member.group.name'),
                            ])
                            ->columnSpanFull()
                            ->placeholder('Belum ada susunan pengurus yang ditetapkan pada grup ini.'),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),
            ])->columns(['default' => 1, 'lg' => 3]);
    }

    private static function totalActiveMembers(Group $record): int
    {
        return Member::whereIn('group_id', $record->getAllDescendantIds())->count();
    }

    private static function computeGenderWithPct(Group $record, Gender $gender): string
    {
        $ids = $record->getAllDescendantIds();
        $total = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('gender', $gender)->count();
        if ($total === 0) {
            return (string) $count;
        }
        $pct = round($count / $total * 100, 1);

        return "{$count} ({$pct}%)";
    }

    private static function computeGenderRatio(Group $record): string
    {
        $ids = $record->getAllDescendantIds();
        $male = Member::whereIn('group_id', $ids)->where('gender', Gender::MALE)->count();
        $female = Member::whereIn('group_id', $ids)->where('gender', Gender::FEMALE)->count();
        $total = $male + $female;
        if ($total === 0) {
            return '-';
        }
        $malePct = round($male / $total * 100);
        $femalePct = 100 - $malePct;

        return "{$malePct}% : {$femalePct}%";
    }

    private static function getAgeGroupEntries(): array
    {
        return AgeGroup::orderBy('sort_order')->get()->map(function (AgeGroup $ag) {
            $agId = $ag->id;

            return TextEntry::make("ag_{$agId}")
                ->label($ag->name)
                ->state(fn (Group $record) => static::computeAgeGroupState($record, $agId))
                ->badge()
                ->icon('heroicon-o-user')
                ->color($ag->getColor());
        })->toArray();
    }

    private static function computeAgeGroupState(Group $record, int $agId): string
    {
        $ids = $record->getAllDescendantIds();
        $total = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('age_group_id', $agId)->count();
        if ($total === 0) {
            return (string) $count;
        }
        $pct = round($count / $total * 100, 1);

        return "{$count} ({$pct}%)";
    }

    private static function computeGenderInAgeGroup(Group $record, int $agId, Gender $gender): string
    {
        $ids = $record->getAllDescendantIds();
        $totalAll = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('age_group_id', $agId)->where('gender', $gender)->count();
        if ($totalAll === 0) {
            return (string) $count;
        }
        $pct = round($count / $totalAll * 100, 1);

        return "{$count} ({$pct}%)";
    }

    private static function computeAgeGroupOverallPct(Group $record, int $agId): string
    {
        $ids = $record->getAllDescendantIds();
        $total = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('age_group_id', $agId)->count();
        if ($total === 0) {
            return '0';
        }
        $pct = round($count / $total * 100, 1);

        return "{$count} ({$pct}%)";
    }

    private static function computeStatusPct(Group $record, bool $status): string
    {
        $ids = $record->getAllDescendantIds();
        $total = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('status', $status)->count();
        if ($total === 0) {
            return (string) $count;
        }
        $pct = round($count / $total * 100, 1);

        return "{$count} ({$pct}%)";
    }

    private static function computeMembershipTypeCount(Group $record, \App\Enums\MembershipType $type): int
    {
        $ids = $record->getAllDescendantIds();

        return Member::whereIn('group_id', $ids)->where('membership_type', $type)->count();
    }

    private static function computeMembershipTypePct(Group $record, \App\Enums\MembershipType $type): string
    {
        $ids = $record->getAllDescendantIds();
        $total = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('membership_type', $type)->count();
        if ($total === 0) {
            return (string) $count;
        }
        $pct = round($count / $total * 100, 1);

        return "{$count} ({$pct}%)";
    }

    private static function computeIsPermanentPct(Group $record, bool $permanent): string
    {
        $ids = $record->getAllDescendantIds();
        $total = static::totalActiveMembers($record);
        $count = Member::whereIn('group_id', $ids)->where('is_permanent', $permanent)->count();
        if ($total === 0) {
            return (string) $count;
        }
        $pct = round($count / $total * 100, 1);

        return "{$count} ({$pct}%)";
    }
}
