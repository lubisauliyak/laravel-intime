<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

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
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state))
                    ->validationMessages([
                        'unique' => 'ID Anggota ini sudah terdaftar. Silakan gunakan ID lain.',
                    ]),
                TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state)),
                TextInput::make('nick_name')
                    ->label('Nama Panggilan')
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => strtoupper($state)),
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
                        
                        $birthDate = \Carbon\Carbon::parse($state);
                        $age = $birthDate->age;
                        $set('age', $age);

                        // Auto-select category based on new age
                        $matchingGroup = \App\Models\AgeGroup::where('min_age', '<=', $age)
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
                        
                        // Auto-select category based on manual age entry
                        $matchingGroup = \App\Models\AgeGroup::where('min_age', '<=', $age)
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
                    ->relationship('ageGroup', 'name')
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
                TextInput::make('membership_type')
                    ->label('Kepengurusan')
                    ->placeholder('Contoh: Ketua, Sekretaris (Biarkan kosong jika Anggota)')
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->mutateDehydratedStateUsing(fn ($state) => $state ? strtoupper($state) : 'ANGGOTA')
                    ->default('ANGGOTA'),
                Toggle::make('status')
                    ->label('Status Aktif Anggota')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(true)
                    ->required(),
            ]);
    }
}
