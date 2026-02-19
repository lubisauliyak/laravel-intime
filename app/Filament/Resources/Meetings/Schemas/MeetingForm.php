<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\Level;
use App\Models\Group;
use App\Models\AgeGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Carbon;

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
                    ])->columns(2),
                Section::make('Target Peserta')
                    ->schema([
                        Select::make('target_gender')
                            ->label('Target Gender')
                            ->options([
                                'all' => 'Semua',
                                'male' => 'Laki-laki',
                                'female' => 'Perempuan'
                            ])
                            ->default('all')
                            ->required(),
                        CheckboxList::make('target_age_groups')
                            ->label('Target Kategori Usia')
                            ->options(AgeGroup::orderBy('sort_order')->pluck('name', 'name'))
                            ->columns(2)
                            ->gridDirection('vertical'),
                    ]),
                Section::make('Waktu & Penyelenggara')
                    ->schema([
                        DatePicker::make('meeting_date')
                            ->label('Tanggal Pertemuan')
                            ->required()
                            ->columnSpan(2),
                        TimePicker::make('start_time')
                            ->label('Jam Dimulai')
                            ->seconds(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $checkinTime = Carbon::parse($state)->subMinutes(30)->format('H:i');
                                    $set('checkin_open_time', $checkinTime);
                                }
                            }),
                        TimePicker::make('end_time')
                            ->label('Jam Berakhir')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('checkin_open_time')
                            ->label('Presensi Dibuka')
                            ->seconds(false)
                            ->nullable()
                            ->helperText('Otomatis 30 menit sebelum jam dimulai. Bisa diubah manual. Jika dikosongkan, presensi dibuka saat jam dimulai.')
                            ->before('start_time')
                            ->columnSpan(2),
                        Select::make('level_id')
                            ->label('Tingkat Grup')
                            ->options(function () {
                                $user = auth()->user();
                                $query = Level::orderBy('level_number', 'desc');
                                
                                if ($user && $user->group_id && !$user->isSuperAdmin()) {
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
                            ->options(fn (Get $get) => 
                                Group::query()
                                    ->when($get('level_id'), fn ($query) => $query->where('level_id', $get('level_id')))
                                    ->when(auth()->user() && auth()->user()->group_id && !auth()->user()->isSuperAdmin(), function ($query) {
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
                    ])->columns(2),

                Hidden::make('created_by')
                    ->default(auth()->id())
                    ->required(),
            ]);
    }
}
