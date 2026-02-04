<?php

namespace App\Filament\Resources\Meetings\Schemas;

use App\Models\Meeting;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MeetingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nama Pertemuan'),
                TextEntry::make('description')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('meeting_date')
                    ->label('Tanggal Pertemuan')
                    ->date('d F Y'),
                TextEntry::make('start_time')
                    ->label('Jam Dimulai')
                    ->time('H:i'),
                TextEntry::make('end_time')
                    ->label('Jam Berakhir')
                    ->time('H:i'),
                TextEntry::make('group.name')
                    ->label('Grup Penyelenggara'),
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
                TextEntry::make('creator.name')
                    ->label('Dibuat Oleh'),

                TextEntry::make('deleted_at')
                    ->label('Dihapus Pada')
                    ->dateTime()
                    ->visible(fn (Meeting $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
