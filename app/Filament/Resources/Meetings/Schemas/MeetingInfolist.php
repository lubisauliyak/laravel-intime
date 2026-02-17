<?php

namespace App\Filament\Resources\Meetings\Schemas;

use App\Models\Meeting;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

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
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
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
