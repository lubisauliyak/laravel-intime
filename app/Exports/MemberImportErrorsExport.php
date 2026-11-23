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

class MemberImportErrorsExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithCustomCsvSettings
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
            'ID Anggota',
            'Kelompok (Wajib)',
            'Nama Lengkap (Wajib)',
            'Nama Panggilan',
            'Tanggal Lahir',
            'L/P (Wajib)',
            'Keterangan Error',
        ];
    }

    public function map($row): array
    {
        $rawData = $row['raw_data'] ?? [];

        return [
            $rawData['id_anggota'] ?? ($row['member_code'] ?? ''),
            $rawData['kelompok_wajib'] ?? ($row['group_name'] ?? ''),
            $rawData['nama_lengkap_wajib'] ?? ($row['full_name'] ?? ''),
            $rawData['nama_panggilan'] ?? ($row['nick_name'] ?? ''),
            $rawData['tanggal_lahir'] ?? ($row['birth_date'] ?? ''),
            $rawData['lp_wajib'] ?? ($row['gender_label'] ?? ''),
            $row['error_message'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRows = count($this->invalidRows) + 1;

        // Style header row - Warna Merah/Bordeaux untuk menandai lembar perbaikan
        $sheet->getStyle('A1:G1')->applyFromArray([
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
            // Style data rows
            $sheet->getStyle("A2:G{$totalRows}")->applyFromArray([
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
            $sheet->getStyle("G2:G{$totalRows}")->applyFromArray([
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
            'A' => 20, // ID Anggota
            'B' => 25, // Kelompok
            'C' => 30, // Nama Lengkap
            'D' => 20, // Nama Panggilan
            'E' => 18, // Tanggal Lahir
            'F' => 15, // L/P
            'G' => 60, // Keterangan Error
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
