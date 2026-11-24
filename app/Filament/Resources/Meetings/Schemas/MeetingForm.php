<?php

namespace App\Filament\Resources\Meetings\Schemas;

use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Level;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MeetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pertemuan')
                            ->required()
                            ->columnSpan(2),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),
                Section::make('Target Peserta')
                    ->schema([
                        Select::make('target_gender')
                            ->label('Jenis Kelamin')
                            ->options(\App\Enums\MeetingTargetGender::class)
                            ->default(\App\Enums\MeetingTargetGender::ALL)
                            ->required(),
                        CheckboxList::make('target_age_groups')
                            ->label('Kategori Usia')
                            ->options(function () {
                                $ageGroups = AgeGroup::orderBy('sort_order')->pluck('name', 'name')->toArray();

                                return $ageGroups;
                            })
                            ->columns(3)
                            ->gridDirection('vertical')
                            ->default([])
                            ->helperText('Pilih satu atau lebih kategori usia. Kosongkan untuk menargetkan semua kategori.'),
                    ])->columnSpanFull(),
                Section::make('Waktu & Penyelenggara')
                    ->schema([
                        DatePicker::make('meeting_date')
                            ->label('Tanggal Pertemuan')
                            ->required(),
                        TimePicker::make('checkin_open_time')
                            ->label('Presensi Dibuka')
                            ->seconds(false)
                            ->required()
                            ->helperText('Waktu dimulainya akses pemindaian presensi scanner.')
                            ->beforeOrEqual('start_time'),
                        TimePicker::make('start_time')
                            ->label('Jam Dimulai')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Jam Berakhir')
                            ->seconds(false)
                            ->required()
                            ->after('start_time'),
                        Select::make('level_id')
                            ->label('Tingkat Grup')
                            ->options(function () {
                                $user = auth()->user();
                                $query = Level::orderBy('level_number', 'desc');

                                if ($user && $user->group_id && ! $user->isSuperAdmin()) {
                                    $userLevelNumber = $user->group->level->level_number;
                                    $query->where('level_number', '<=', $userLevelNumber);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Select $component, $record) {
                                if ($record && $record->group_id) {
                                    $component->state($record->group?->level_id);
                                }
                            })
                            ->afterStateUpdated(fn (Select $component) => $component->getContainer()->getComponent('group_id')->state(null)),

                        Select::make('group_id')
                            ->id('group_id')
                            ->label('Grup Penyelenggara')
                            ->options(
                                fn (Get $get) => Group::query()
                                    ->when($get('level_id'), fn ($query) => $query->where('level_id', $get('level_id')))
                                    ->when(auth()->user() && auth()->user()->group_id && ! auth()->user()->isSuperAdmin(), function ($query) {
                                        $groupIds = auth()->user()->group->getAllDescendantIds();
                                        $query->whereIn('id', $groupIds);
                                    })
                                    ->pluck('name', 'id')
                            )
                            ->disabled(fn (Get $get) => ! $get('level_id'))
                            ->helperText(fn (Get $get) => ! $get('level_id') ? 'Pilih Tingkat Grup terlebih dahulu' : null)
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2)->columnSpanFull(),

                Hidden::make('created_by')
                    ->default(auth()->id())
                    ->required(),
            ]);
    }
}
