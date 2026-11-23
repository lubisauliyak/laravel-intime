<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EditSelfService extends EditRecord
{
    protected static string $resource = MeetingResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;
        return $record
            ? auth()->user()->can('editSelfService', $record)
            : auth()->user()->can('EditSelfService:Meeting');
    }

    public function getTitle(): string
    {
        return 'Ubah Pengaturan Mandiri';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->url(fn () => MeetingResource::getUrl('self-service', [$this->getRecord()])),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Presensi Mandiri')
                    ->description('Aktifkan atau nonaktifkan akses publik ke form presensi mandiri.')
                    ->schema([
                        Toggle::make('is_presence_enabled')
                            ->label('Aktifkan Presensi Mandiri')
                            ->default(false)
                            ->helperText('Jika dinonaktifkan, halaman presensi akan menampilkan "sesi berakhir".'),
                    ])
                    ->columnSpanFull(),
                Section::make('Perizinan Mandiri')
                    ->description('Aktifkan atau nonaktifkan akses publik ke form perizinan mandiri.')
                    ->schema([
                        Toggle::make('is_permission_enabled')
                            ->label('Aktifkan Perizinan Mandiri')
                            ->default(false)
                            ->helperText('Jika dinonaktifkan, halaman perizinan akan menampilkan "sesi berakhir".')
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Section::make('Status Template Surat Perizinan')
                    ->description('Aktifkan atau nonaktifkan template surat perizinan.')
                    ->schema([
                        Toggle::make('is_permission_template_active')
                            ->label('Aktifkan Template Surat')
                            ->default(false)
                            ->helperText('Jika diaktifkan, form akan menampilkan template surat perizinan.')
                            ->live(),
                    ])
                    ->columnSpanFull(),
                Section::make('Konten Surat Perizinan')
                    ->description('Atur teks yang akan ditampilkan pada surat perizinan anggota. Gunakan shortcode: [nama-pertemuan], [hari-pertemuan], [tanggal-pertemuan], [nama-penyelenggara], [tingkatan-pertemuan], [grup-pertemuan], [nama-anggota], [grup-anggota]')
                    ->schema([
                        RichEditor::make('permission_opening_text')
                            ->label('Teks Pembuka')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('permission-templates')
                            ->helperText('Akan muncul di atas form.')
                            ->columnSpanFull(),
                        RichEditor::make('permission_statement_text')
                            ->label('Teks Pernyataan')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('permission-templates')
                            ->helperText('Pernyataan sebelum kolom alasan.')
                            ->columnSpanFull(),
                        RichEditor::make('permission_closing_text')
                            ->label('Teks Penutup')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('permission-templates')
                            ->helperText('Pernyataan penutup sebelum tombol kirim.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(Get $get) => $get('is_permission_template_active'))
                    ->columnSpanFull(),
            ]);
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('self-service', [$this->getRecord()]);
    }
}
