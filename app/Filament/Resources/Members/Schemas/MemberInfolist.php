<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Section 1: Informasi Anggota ─────────────────────────
                Section::make('Informasi Anggota')
                    ->schema([
                        ImageEntry::make('profile_photo_path')
                            ->hiddenLabel()
                            ->disk('public')
                            ->imageHeight(50)
                            ->imageWidth(50)
                            ->circular()
                            ->extraImgAttributes([
                                'class' => 'mx-auto shadow-lg border-2 border-gray-200 dark:border-white/10 cursor-pointer',
                            ])
                            ->placeholder('Belum ada foto profil')
                            ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->full_name).'&background=e2e8f0&color=94a3b8&size=160')
                            ->action(
                                Action::make('preview_profile')
                                    ->modalHeading('Foto Profil')
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(fn ($action) => $action->label('Tutup'))
                                    ->modalWidth('sm')
                                    ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString(
                                        '<div class="flex justify-center"><img src="'.($record->profile_photo_path ? asset('storage/'.$record->profile_photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($record->full_name).'&background=e2e8f0&color=94a3b8&size=500').'" class="max-w-[250px] max-h-[300px] h-auto w-auto object-contain rounded-lg shadow-lg"></div>'
                                    ))
                            )
                            ->columnSpan(['default' => 1, 'md' => 1]),
                        TextEntry::make('member_code')
                            ->label('ID Anggota')
                            ->weight(FontWeight::Bold),
                        TextEntry::make('full_name')
                            ->label('Nama Lengkap')
                            ->weight(FontWeight::Bold)
                            ->color(fn (?Member $record): ?string => $record?->getMemberColor())
                            ->tooltip(fn (?Member $record): ?string => $record?->getMemberColorTooltip()),
                        TextEntry::make('group.name')
                            ->label('Kelompok'),
                        TextEntry::make('ageGroup.name')
                            ->label('Kategori Usia')
                            ->placeholder('Tanpa Kategori Usia')
                            ->badge()
                            ->color(fn (?Member $record) => $record?->ageGroup?->getColor() ?? 'gray'),
                        TextEntry::make('gender')
                            ->label('Jenis Kelamin')
                            ->badge(),
                        TextEntry::make('membership_type')
                            ->label('Kepengurusan')
                            ->badge(),
                        IconEntry::make('status')
                            ->label('Status Aktif')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Data Dibuat')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Data Diperbarui')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 2]),

                // ── Section 2: Identitas Digital ──────────────────
                Section::make('Identitas Digital')
                    ->schema([
                        // KTA
                        ImageEntry::make('membership_card_photo_path')
                            ->label('Kartu Tanda Anggota')
                            ->hiddenLabel()
                            ->disk('public')
                            ->imageHeight('auto')
                            ->imageWidth('100%')
                            ->extraImgAttributes([
                                'class' => 'mx-auto rounded-lg shadow-md border border-gray-200 dark:border-white/10 cursor-pointer',
                            ])
                            ->visible(fn ($record) => filled($record->membership_card_photo_path))
                            ->action(
                                Action::make('preview_kta')
                                    ->modalHeading('Kartu Tanda Anggota')
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(fn ($action) => $action->label('Tutup'))
                                    ->modalWidth('sm')
                                    ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString(
                                        '<div class="flex justify-center"><img src="'.asset('storage/'.$record->membership_card_photo_path).'" class="max-w-[250px] max-h-[350px] h-auto w-auto object-contain rounded-lg shadow-lg"></div>'
                                    ))
                            ),

                        // QR Code
                        Grid::make(1)
                            ->schema([
                                ImageEntry::make('qr_code_path')
                                    ->label('QR Code')
                                    ->hiddenLabel()
                                    ->square()
                                    ->imageWidth('100%')
                                    ->imageHeight('auto')
                                    ->disk('public')
                                    ->visible(fn ($record) => filled($record->member_code))
                                    ->extraImgAttributes([
                                        'class' => 'mx-auto max-w-[200px] md:max-w-[250px] cursor-pointer',
                                        'style' => 'image-rendering: auto;',
                                    ])
                                    ->action(
                                        Action::make('preview_qr')
                                            ->modalHeading('QR Code Presensi')
                                            ->modalSubmitAction(false)
                                            ->modalCancelAction(fn ($action) => $action->label('Tutup'))
                                            ->modalWidth('sm')
                                            ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString(
                                                '<div class="flex flex-col items-center justify-center p-4"><img src="'.asset('storage/'.$record->qr_code_path).'" class="max-w-[250px] max-h-[300px] h-auto w-auto object-contain rounded-lg bg-white p-2 shadow-lg"></div>'
                                            ))
                                    ),
                                TextEntry::make('member_code')
                                    ->label('Scan kode ini untuk presensi')
                                    ->alignCenter()
                                    ->color('gray')
                                    ->visible(fn ($record) => filled($record->member_code)),
                            ])->visible(fn ($record) => empty($record->membership_card_photo_path)),
                    ])
                    ->columnSpan(['default' => 'full', 'lg' => 1])
                    ->headerActions([
                        Action::make('download_digital_id')
                            ->label(fn ($record) => filled($record->membership_card_photo_path) ? 'Download KTA' : 'Download QR')
                            ->hiddenLabel()
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('success')
                            ->visible(fn (Member $record) => (filled($record->membership_card_photo_path) || filled($record->qr_code_path)) && (auth()->user()->can('downloadKta', $record) || auth()->user()->can('downloadQr', $record)))
                            ->action(function (Member $record) {
                                if (filled($record->membership_card_photo_path)) {
                                    return response()->download(Storage::disk('public')->path($record->membership_card_photo_path), "KTA-{$record->member_code}.png");
                                }

                                return response()->download(Storage::disk('public')->path($record->qr_code_path), "QR-{$record->member_code}.png");
                            }),
                    ]),

                // ── Section 3: Kepengurusan ─────────────────────────
                Section::make('Kepengurusan')
                    ->visible(fn (?Member $record) => (bool) $record?->isPengurus())
                    ->schema([
                        RepeatableEntry::make('positions')
                            ->hiddenLabel()
                            ->state(function (?Member $record) {
                                if (! $record) {
                                    return [];
                                }

                                $record->loadMissing(['positions.group.level', 'positions.category']);

                                return $record->positions
                                    ->sortBy([
                                        fn ($a, $b) => -($a->group?->level?->level_number <=> $b->group?->level?->level_number),
                                        fn ($a, $b) => ($a->category?->sort_order ?? 99) <=> ($b->category?->sort_order ?? 99),
                                        fn ($a, $b) => ($a->sort_order ?? 99) <=> ($b->sort_order ?? 99),
                                        fn ($a, $b) => ($a->position_name ?? '') <=> ($b->position_name ?? ''),
                                    ])
                                    ->values();
                            })
                            ->table([
                                TableColumn::make('Pengurus'),
                                TableColumn::make('Kategori'),
                                TableColumn::make('Grup'),
                                TableColumn::make('Tingkat'),
                            ])
                            ->schema([
                                TextEntry::make('position_name')
                                    ->weight(FontWeight::Bold),
                                TextEntry::make('category.name'),
                                TextEntry::make('group.name'),
                                TextEntry::make('group.level.name'),
                            ])
                            ->columnSpanFull()
                            ->placeholder('Belum ada data jabatan kepengurusan yang ditambahkan.'),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),
            ])->columns(['default' => 1, 'lg' => 3]);
    }
}
