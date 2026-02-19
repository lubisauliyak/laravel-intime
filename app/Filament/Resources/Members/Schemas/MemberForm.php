<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\AgeGroup;
use App\Models\PositionCategory;
use App\Models\Group;
use Illuminate\Support\Carbon;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('member_code')
                    ->label('ID Anggota')
                    ->nullable()
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null)
                    ->validationMessages([
                        'unique' => 'ID Anggota ini sudah terdaftar. Silakan gunakan ID lain.',
                    ]),
                TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null),
                TextInput::make('nick_name')
                    ->label('Nama Panggilan')
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null),
                Select::make('group_id')
                    ->label('Kelompok')
                    ->relationship(
                        name: 'group',
                        titleAttribute: 'groups.name',
                        modifyQueryUsing: function ($query) {
                            $user = auth()->user();
                            $query->whereHas('level', fn ($q) => $q->where('level_number', 1))
                                ->leftJoin('groups as desa', 'groups.parent_id', '=', 'desa.id')
                                ->select('groups.*')
                                ->orderBy('desa.name', 'asc')
                                ->orderBy('groups.name', 'asc');

                            if ($user && !$user->isSuperAdmin() && $user->group_id) {
                                $descendantIds = $user->group->getAllDescendantIds();
                                $query->whereIn('groups.id', $descendantIds);
                            }
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->placeholder('HH/BB/TTTT')
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if (!$state) return;

                        $birthDate = Carbon::parse($state);
                        $age = $birthDate->age;
                        $set('age', $age);

                        $matchingGroup = AgeGroup::where('min_age', '<=', $age)
                            ->where(function ($query) use ($age) {
                                $query->where('max_age', '>=', $age)
                                    ->orWhereNull('max_age');
                            })
                            ->first();

                        if ($matchingGroup) {
                            $set('age_group_id', $matchingGroup->id);
                        }
                    }),
                TextInput::make('age')
                    ->label('Usia Saat Ini')
                    ->suffix(' Tahun')
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if (!$state) return;

                        $age = (int) $state;

                        $matchingGroup = AgeGroup::where('min_age', '<=', $age)
                            ->where(function ($query) use ($age) {
                                $query->where('max_age', '>=', $age)
                                    ->orWhereNull('max_age');
                            })
                            ->first();

                        if ($matchingGroup) {
                            $set('age_group_id', $matchingGroup->id);
                        }
                    }),
                Select::make('age_group_id')
                    ->label('Kategori Usia')
                    ->relationship('ageGroup', 'name', fn($query) => $query->orderBy('sort_order'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Kategori dipilih otomatis berdasarkan usia, namun tetap bisa diubah manual jika diperlukan.'),
                Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'male' => 'LAKI-LAKI',
                        'female' => 'PEREMPUAN',
                    ])
                    ->required(),
                Select::make('membership_type')
                    ->label('Tipe Keanggotaan')
                    ->options([
                        'anggota' => 'ANGGOTA',
                        'pengurus' => 'PENGURUS',
                    ])
                    ->required()
                    ->live()
                    ->default('anggota'),
                
                // Simple Section for positions - collapsible popup style
                Section::make('Struktur Kepengurusan')
                    ->description('Klik tombol di bawah untuk mengelola dapukan anggota.')
                    ->visible(fn (Get $get) => $get('membership_type') === 'pengurus')
                    ->collapsible()
                    ->collapsed()
                    ->headerActions([
                        // Optional header actions if needed
                    ])
                    ->schema([
                        Repeater::make('positions')
                            ->relationship('positions')
                            ->label('Dapukan')
                            ->addActionLabel('Tambah Dapukan')
                            ->schema([
                                Select::make('position_category_id')
                                    ->label('Kategori')
                                    ->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Select::make('group_id')
                                    ->label('Grup')
                                    ->relationship(
                                        name: 'group',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->with('level')->orderBy('level_id')
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('position_name')
                                    ->label('Nama Dapukan')
                                    ->placeholder('CONTOH: KETUA')
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : null),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsed()
                            ->itemLabel(function (array $state): string {
                                $positionName = $state['position_name'] ?? '';
                                $categoryName = '';
                                $levelName = '';

                                // Get category name
                                if (!empty($state['position_category_id'])) {
                                    $category = PositionCategory::find($state['position_category_id']);
                                    if ($category) {
                                        $categoryName = $category->name;
                                    }
                                }

                                // Get level name from group
                                if (!empty($state['group_id'])) {
                                    $group = Group::find($state['group_id']);
                                    if ($group && $group->level) {
                                        $levelName = $group->level->name;
                                    }
                                }

                                $parts = array_filter([$positionName, $categoryName, $levelName]);
                                return !empty($parts) ? implode(' ', $parts) : 'Dapukan';
                            }),
                    ])->columnSpanFull(),
                Toggle::make('status')
                    ->label('Status Aktif Anggota')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(true)
                    ->required(),
            ]);
    }
}
