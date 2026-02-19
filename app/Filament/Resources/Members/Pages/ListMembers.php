<?php

namespace App\Filament\Resources\Members\Pages;

use App\Exports\MemberTemplateExport;
use App\Filament\Imports\MemberImporter;
use App\Filament\Resources\Members\MemberResource;
use Exception;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import Anggota')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('success')
                ->visible(fn () => auth()->user()->can('Import:Member'))
                ->modalHeading('Import Data Anggota dari Excel')
                ->modalDescription('Pastikan format data sesuai dengan template yang disediakan.')
                ->schema([
                    Section::make('Panduan Import')
                        ->description('Ikuti langkah-langkah berikut untuk mengimport data anggota')
                        ->icon('heroicon-m-information-circle')
                        ->schema([
                            FormActions::make([
                                Action::make('download_template')
                                    ->label('Download Template Excel')
                                    ->icon('heroicon-m-arrow-down-tray')
                                    ->color('info')
                                    ->outlined()
                                    ->action(function (): BinaryFileResponse {
                                        $fileName = 'Template_Import_Anggota_' . now()->format('Ymd_His') . '.xlsx';
                                        
                                        return Excel::download(new MemberTemplateExport(), $fileName);
                                    }),
                            ])->alignCenter()->columnSpan('full'),
                        ])->columnSpan('full'),
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                        ->maxSize(10240)
                        ->disk('public')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $filePath = $data['file'];
                        $fullPath = storage_path('app/public/' . $filePath);

                        if (!file_exists($fullPath)) {
                            throw new Exception('File tidak ditemukan: ' . $fullPath);
                        }

                        // Import the Excel file
                        Excel::import(
                            new MemberImporter(),
                            $fullPath
                        );

                        // Delete uploaded file after import
                        Storage::disk('public')->delete($filePath);

                        Notification::make()
                            ->title('Import Berhasil')
                            ->body('Data anggota berhasil diimport. QR Code akan di-generate otomatis.')
                            ->success()
                            ->send();

                        // Refresh the page
                        $this->dispatch('$refresh');
                    } catch (Exception $e) {
                        // Cleanup file on error
                        if (isset($filePath)) {
                            Storage::disk('public')->delete($filePath);
                        }

                        Notification::make()
                            ->title('Import Gagal')
                            ->body('Terjadi kesalahan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
