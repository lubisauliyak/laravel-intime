<?php

namespace App\Filament\Resources\Meetings\Schemas;

use App\Models\Meeting;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;

class MeetingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Pertemuan')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Pertemuan')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('group.name')
                                    ->label('Grup Penyelenggara'),
                                TextEntry::make('meeting_date')
                                    ->label('Tanggal Pertemuan')
                                    ->date('d F Y')
                                    ->icon('heroicon-m-calendar'),
                                TextEntry::make('target_gender')
                                    ->label('Target Gender')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'all' => 'Semua',
                                        'male' => 'Laki-laki',
                                        'female' => 'Perempuan',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        'male' => 'info',
                                        'female' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('target_age_groups')
                                    ->label('Target Kategori Usia')
                                    ->state(function ($record) {
                                        $groups = $record->target_age_groups;
                                        if (empty($groups)) return null;
                                        return \App\Models\AgeGroup::whereIn('name', (array) $groups)
                                            ->orderBy('sort_order')
                                            ->pluck('name')
                                            ->toArray();
                                    })
                                    ->placeholder('Semua')
                                    ->badge()
                                    ->color(fn (string $state): string => match (true) {
                                        str_contains(strtolower($state), 'pra remaja') => 'info',
                                        str_contains(strtolower($state), 'remaja') => 'warning',
                                        str_contains(strtolower($state), 'pra nikah') => 'success',
                                        default => 'gray',
                                    }),
                                TextEntry::make('start_time')
                                    ->label('Jam Dimulai')
                                    ->time('H:i')
                                    ->icon('heroicon-m-clock'),
                                TextEntry::make('checkin_open_time')
                                    ->label('Presensi Dibuka')
                                    ->time('H:i')
                                    ->icon('heroicon-m-clock')
                                    ->placeholder('Saat jam dimulai'),
                                TextEntry::make('end_time')
                                    ->label('Jam Berakhir')
                                    ->time('H:i')
                                    ->icon('heroicon-m-clock'),
                                TextEntry::make('creator.name')
                                    ->label('Dibuat Oleh')
                                    ->icon('heroicon-m-user'),
                                TextEntry::make('created_at')
                                    ->label('Waktu Dibuat')
                                    ->dateTime('d F Y H:i')
                                    ->color('gray'),
                            ]),
                        TextEntry::make('description')
                            ->label('Keterangan Tambahan')
                            ->placeholder('Tidak ada keterangan tambahan.')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
