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
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->whereHas('level', fn ($q) => $q->where('level_number', 1))
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection()
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
                    ->readOnly()
                    ->numeric(),
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
                Select::make('membership_type')
                    ->label('Tipe Keanggotaan')
                    ->options([
                        'anggota' => 'ANGGOTA',
                        'pengurus' => 'PENGURUS',
                    ])
                    ->default('anggota')
                    ->required(),
                Toggle::make('status')
                    ->label('Status Aktif Anggota')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(true)
                    ->required(),
            ]);
    }
}
