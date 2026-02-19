<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Anggota')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nama Lengkap'),
                                TextEntry::make('member_code')
                                    ->label('ID Anggota')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('group.name')
                                    ->label('Kelompok'),
                                TextEntry::make('ageGroup.name')
                                    ->label('Kategori Usia'),
                                TextEntry::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->formatStateUsing(fn ($state) => $state === 'male' ? 'LAKI-LAKI' : 'PEREMPUAN'),
                                IconEntry::make('status')
                                    ->label('Status Aktif')
                                    ->boolean(),
                                TextEntry::make('membership_type')
                                    ->label('Kepengurusan')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                                    ->color(fn (string $state): string => match (strtolower($state)) {
                                        'anggota' => 'gray',
                                        'pengurus' => 'primary',
                                        default => 'gray',
                                    }),
                            ]),
                    ])->columnSpan(['default' => 'full', 'lg' => 2]),
                Section::make('Identitas Digital')
                    ->visible(fn ($record) => filled($record->member_code))
                    ->schema([
                        ImageEntry::make('qr_code_path')
                            ->label('QR Code')
                            ->hiddenLabel()
                            ->square()
                            ->width('100%')
                            ->height('auto')
                            ->disk('public')
                            ->extraImgAttributes([
                                'class' => 'mx-auto max-w-[200px] md:max-w-[250px]',
                                'style' => 'image-rendering: auto;',
                            ]),
                        TextEntry::make('member_code')
                            ->label('Scan kode ini untuk absensi')
                            ->alignCenter()
                            ->color('gray')
                            ->extraAttributes(['class' => 'text-xs md:text-sm']),
                    ])
                    ->columnSpan(['default' => 'full', 'lg' => 1])
                    ->headerActions([
                        Action::make('download_qr')
                            ->label('')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('success')
                            ->action(fn (Member $record) => Storage::disk('public')->download($record->qr_code_path, "{$record->member_code}.png")),
                    ]),
            ])->columns(['default' => 1, 'lg' => 3]);
    }
}
