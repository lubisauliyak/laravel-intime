<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupImportErrorsExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithCustomCsvSettings
{
    protected array $invalidRows;

    public function __construct(array $invalidRows)
    {
        $this->invalidRows = $invalidRows;
    }

    public function array(): array
    {
        return $this->invalidRows;
    }

    public function headings(): array
    {
        return [
            'Tingkat Grup (Wajib)',
            'Nama Grup (Wajib)',
            'Induk Grup',
            'Kode Grup (Opsional)',
            'Keterangan Error',
        ];
    }

    public function map($row): array
    {
        $rawData = $row['raw_data'] ?? [];

        return [
            $rawData['tingkat_grup_wajib'] ?? ($row['tingkat_grup'] ?? ''),
            $rawData['nama_grup_wajib'] ?? ($row['nama_grup'] ?? ''),
            $rawData['induk_grup'] ?? ($row['induk_grup'] ?? ''),
            $rawData['kode_grup_opsional'] ?? ($row['kode_grup'] ?? ''),
            $row['error_message'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRows = count($this->invalidRows) + 1;

        // Style header row - Warna Merah untuk menandai data bermasalah
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DC2626'], // Red 600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($totalRows > 1) {
            $sheet->getStyle("A2:E{$totalRows}")->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
            ]);

            // Kolom Keterangan Error dibuat wrap text dan font merah
            $sheet->getStyle("E2:E{$totalRows}")->applyFromArray([
                'font' => [
                    'color' => ['rgb' => 'B91C1C'], // Red 700
                    'bold' => true,
                ],
                'alignment' => [
                    'wrapText' => true,
                ],
            ]);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // Tingkat Grup
            'B' => 35, // Nama Grup
            'C' => 35, // Induk Grup
            'D' => 25, // Kode Grup
            'E' => 60, // Keterangan Error
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => "\r\n",
            'use_bom' => true,
            'include_separator_line' => false,
            'excel_compatibility' => false,
        ];
    }
}
