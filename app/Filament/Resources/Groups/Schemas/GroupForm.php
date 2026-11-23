<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Models\CardTemplate;
use App\Models\Group;
use App\Models\Level;
use App\Services\Groups\GroupHierarchyService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('level_id')
                    ->relationship(
                        name: 'level',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) {
                            $user = auth()->user();

                            // Super Admin can select all levels
                            if ($user->isSuperAdmin()) {
                                return $query->orderBy('level_number', 'desc');
                            }

                            // Admin/Operator can only select levels BELOW their group's level
                            $userLevelNumber = $user->group?->level?->level_number;

                            if ($userLevelNumber) {
                                return $query->where('level_number', '<', $userLevelNumber)
                                    ->orderBy('level_number', 'desc');
                            }

                            // If user has no group, show no levels
                            return $query->whereRaw('1 = 0');
                        }
                    )
                    ->label('Tingkat Hirarki (Level)')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn(Set $set) => $set('parent_id', null)),

                Select::make('parent_id')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'groups.name',
                        modifyQueryUsing: function (Builder $query, Get $get) {
                            $levelId = $get('level_id');
                            if (!$levelId) {
                                return $query->whereRaw('1 = 0');
                            }

                            $level = Level::find($levelId);
                            if (!$level) {
                                return $query;
                            }

                            // Parent must be exactly one level higher (level_number + 1)
                            $targetLevelNumber = $level->level_number + 1;
                            $user = auth()->user();

                            $query->whereHas('level', function ($q) use ($targetLevelNumber) {
                                $q->where('level_number', $targetLevelNumber);
                            });

                            if (!$user->isSuperAdmin()) {
                                if ($user->group_id) {
                                    $allowedGroupIds = $user->group->getAllDescendantIds();
                                    $query->whereIn('groups.id', $allowedGroupIds);
                                } else {
                                    $query->whereRaw('1 = 0');
                                }
                            }

                            return $query;
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                    ->label('Parent Group')
                    ->searchable()
                    ->preload()
                    ->placeholder(fn(Get $get) => $get('level_id') ? 'Pilih induk kelompok' : 'Pilih tingkat hirarki terlebih dahulu')
                    ->disabled(fn(Get $get) => !$get('level_id'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        $parentId = $get('parent_id');
                        if ($parentId) {
                            $parent = Group::find($parentId);
                            if ($parent) {
                                $res = app(GroupHierarchyService::class)->getEffectiveCardTemplate($parent);
                                if ($res['template']) {
                                    $set('card_template_id', $res['template']->id);
                                }
                            }
                        }
                    })
                    ->formatStateUsing(function ($state) {
                        if (!$state)
                            return null;
                        return Group::where('id', $state)->exists() ? $state : null;
                    })
                    ->dehydrated(fn($state) => filled($state)),

                TextInput::make('name')
                    ->label('Nama Grup')
                    ->required()
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn($state) => strtoupper($state))
                    ->rules([
                        fn (Get $get, ?\Illuminate\Database\Eloquent\Model $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $levelId = $get('level_id');
                            $parentId = $get('parent_id');
                            $name = trim((string) $value);

                            if (!empty($name) && $levelId) {
                                if (Group::isNameDuplicateInScope($name, (int) $levelId, $parentId ? (int) $parentId : null, $record?->id)) {
                                    $level = Level::find($levelId);
                                    $levelName = $level ? $level->name : 'tingkat yang sama';
                                    $fail("Nama grup '{$name}' sudah digunakan pada tingkat {$levelName} dalam wilayah wewenang ini.");
                                }
                            }
                        },
                    ])
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Kode Grup')
                    ->placeholder('Contoh: 10 atau 07')
                    ->maxLength(10)
                    ->live(debounce: 500)
                    ->helperText('Kode angka/karakter untuk grup ini. Digunakan untuk merangkai prefix ID anggota otomatis.')
                    ->unique(
                        table: Group::class,
                        column: 'code',
                        ignorable: fn($record) => $record,
                        modifyRuleUsing: function (Unique $rule, Get $get) {
                            return $rule->where('parent_id', $get('parent_id'));
                        }
                    )
                    ->validationMessages([
                        'unique' => 'Kode ini sudah digunakan oleh grup lain di bawah induk yang sama.',
                    ]),

                Placeholder::make('preview_prefix')
                    ->label('Pratinjau Prefix & Format ID')
                    ->content(function (Get $get) {
                        $parentId = $get('parent_id');
                        $currentCode = trim((string) $get('code'));

                        $parent = $parentId ? Group::with('level')->find($parentId) : null;
                        $service = app(GroupHierarchyService::class);

                        $parentChain = $parent ? $service->getHierarchyChain($parent) : collect();

                        $parentPrefix = '';
                        $firstCodeFound = false;
                        $chainBroken = false;
                        $brokenLevel = null;

                        foreach ($parentChain as $g) {
                            $c = trim((string) $g->code);
                            if (!$firstCodeFound) {
                                if ($c !== '') {
                                    $firstCodeFound = true;
                                    $parentPrefix .= $c;
                                }
                            } else {
                                if ($c === '') {
                                    $chainBroken = true;
                                    $brokenLevel = $g->level?->name ?? 'Induk';
                                    break;
                                }
                                $parentPrefix .= $c;
                            }
                        }

                        if ($chainBroken) {
                            return new HtmlString(
                                '<div class="text-sm text-danger-600 dark:text-danger-400 font-medium">' .
                                '⚠️ Rantai kode terputus pada tingkat <strong>' . e($brokenLevel) . '</strong>. Harap lengkapi kode induk terlebih dahulu.' .
                                '</div>'
                            );
                        }

                        if ($firstCodeFound && $currentCode === '') {
                            return new HtmlString(
                                '<div class="text-sm text-amber-600 dark:text-amber-400">' .
                                '⚠️ Induk memiliki kode (Prefix Induk: <strong>' . e($parentPrefix) . '</strong>). Harap isi kode grup ini agar penomoran ID anggota valid.' .
                                '</div>'
                            );
                        }

                        if ($currentCode === '' && !$firstCodeFound) {
                            return new HtmlString(
                                '<div class="text-sm text-gray-500 dark:text-gray-400">' .
                                '⚪ Seluruh hierarki belum memiliki kode. Anggota di grup ini akan disimpan tanpa nomor ID otomatis.' .
                                '</div>'
                            );
                        }

                        $totalPrefix = $parentPrefix . $currentCode;
                        $parentNote = $parentPrefix !== '' ? ' <span class="text-xs text-gray-500 font-normal">(Induk: ' . e($parentPrefix) . ' + Grup: ' . e($currentCode) . ')</span>' : '';

                        return new HtmlString(
                            '<div class="p-3 bg-gray-50 dark:bg-gray-800/60 rounded-lg border border-gray-200 dark:border-gray-700 text-sm space-y-1">' .
                            '<div>Prefix Terbentuk: <span class="font-bold text-primary-600 dark:text-primary-400 font-mono text-base">' . e($totalPrefix) . '</span>' . $parentNote . '</div>' .
                            '<div class="text-xs text-gray-600 dark:text-gray-400">Contoh ID Anggota: <span class="font-mono font-semibold">' . e($totalPrefix) . '001</span> s/d <span class="font-mono font-semibold">' . e($totalPrefix) . '999</span> (dan seterusnya)</div>' .
                            '</div>'
                        );
                    }),

                Select::make('card_template_id')
                    ->label('Template KTA')
                    ->options(fn () => CardTemplate::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Template KTA (Opsional)')
                    ->live()
                    ->disabled(function (Get $get) {
                        $parentId = $get('parent_id');
                        if (!$parentId) return false;
                        $parent = Group::find($parentId);
                        if (!$parent) return false;
                        $res = app(GroupHierarchyService::class)->getEffectiveCardTemplate($parent);
                        return $res['template'] !== null;
                    })
                    ->formatStateUsing(function ($state, Get $get) {
                        $parentId = $get('parent_id');
                        if ($parentId) {
                            $parent = Group::find($parentId);
                            if ($parent) {
                                $res = app(GroupHierarchyService::class)->getEffectiveCardTemplate($parent);
                                if ($res['template']) {
                                    return $res['template']->id;
                                }
                            }
                        }
                        return $state;
                    })
                    ->helperText(function (Get $get) {
                        $parentId = $get('parent_id');
                        if ($parentId) {
                            $parent = Group::find($parentId);
                            if ($parent) {
                                $res = app(GroupHierarchyService::class)->getEffectiveCardTemplate($parent);
                                if ($res['template']) {
                                    $ownerName = $res['inherited_from']?->name ?? $parent->name;
                                    return "🔒 Template dikunci oleh induk: {$ownerName} ({$res['template']->name}). Grup tingkat ini otomatis mewarisi template tersebut.";
                                }
                            }
                        }
                        return 'Pilih desain kartu anggota untuk grup ini dan tingkatan di bawahnya (kosongkan jika belum ingin menentukan).';
                    })
                    ->dehydrated(function (Get $get) {
                        $parentId = $get('parent_id');
                        if ($parentId) {
                            $parent = Group::find($parentId);
                            if ($parent) {
                                $res = app(GroupHierarchyService::class)->getEffectiveCardTemplate($parent);
                                if ($res['template']) {
                                    return false; // Leave null in DB for children to inherit cleanly
                                }
                            }
                        }
                        return true;
                    })
                    ->mutateDehydratedStateUsing(fn ($state) => $state ?: null),

                Toggle::make('status')
                    ->label('Status Aktif Grup')
                    ->onColor('success')
                    ->offColor('danger')
                    ->required()
                    ->default(true),
            ]);
    }
}

