<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MemberTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new TemplateSheet(),
            new DocumentationSheet(),
        ];
    }
}

class TemplateSheet implements FromArray, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnFormatting, WithColumnWidths
{
    protected array $sampleData;

    public function __construct()
    {
        // Sample data untuk template
        $this->sampleData = [
            [
                'member_code' => '',
                'full_name' => 'CONTOH ANGGOTA PERTAMA',
                'group_name' => 'KELOMPOK CONTOH',
                'nick_name' => 'CONTOH',
                'birth_date' => '15/01/1990',
                'gender' => 'male',
                'status' => 'active',
                'membership_type' => 'anggota',
            ],
            [
                'member_code' => 'M202602190001',
                'full_name' => 'CONTOH ANGGOTA KEDUA',
                'group_name' => 'KELOMPOK CONTOH',
                'nick_name' => '',
                'birth_date' => '20/06/1995',
                'gender' => 'female',
                'status' => 'active',
                'membership_type' => 'pengurus',
            ],
        ];
    }

    public function array(): array
    {
        return $this->sampleData;
    }

    public function headings(): array
    {
        return [
            'member_code',
            'full_name',
            'group_name',
            'nick_name',
            'birth_date',
            'gender',
            'status',
            'membership_type',
        ];
    }

    public function map($row): array
    {
        return [
            $row['member_code'] ?? '',
            $row['full_name'],
            $row['group_name'],
            $row['nick_name'] ?? '',
            $row['birth_date'] ?? '',
            $row['gender'] ?? 'male',
            $row['status'] ?? 'active',
            $row['membership_type'] ?? 'anggota',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Style header - Biru Indigo
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo/Blue
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Style data rows
        $sheet->getStyle('A2:H3')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Add borders
        $sheet->getStyle('A1:H3')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Template Import';
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // member_code
            'B' => 30, // full_name
            'C' => 25, // group_name
            'D' => 20, // nick_name
            'E' => 15, // birth_date
            'F' => 12, // gender
            'G' => 15, // status
            'H' => 20, // membership_type
        ];
    }
}

class DocumentationSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function array(): array
    {
        return [
            [
                'member_code',
                'Kode anggota (OPSIONAL)',
                'Kosongkan untuk anggota baru. Isi jika ingin update data anggota yang sudah ada (contoh: M202602190001)',
            ],
            [
                'full_name',
                'Nama lengkap anggota (WAJIB)',
                'Contoh: BUDI SANTOSO',
            ],
            [
                'group_name',
                'Nama grup/kelompok (WAJIB)',
                'Harus sesuai dengan nama grup yang sudah terdaftar di sistem',
            ],
            [
                'nick_name',
                'Nama panggilan (OPSIONAL)',
                'Contoh: BUDI',
            ],
            [
                'birth_date',
                'Tanggal lahir (OPSIONAL)',
                'Format: DD/MM/YYYY (contoh: 15/01/1990). Kosongkan jika tidak ada.',
            ],
            [
                'gender',
                'Jenis kelamin (OPSIONAL)',
                'Pilihan: male (Laki-laki), female (Perempuan). Default: male',
            ],
            [
                'status',
                'Status keaktifan (OPSIONAL)',
                'Pilihan: active (Aktif), inactive (Non-aktif). Default: active',
            ],
            [
                'membership_type',
                'Tipe keanggotaan (OPSIONAL)',
                'Pilihan: anggota (Anggota), pengurus (Pengurus). Default: anggota',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Kolom',
            'Keterangan',
            'Contoh/Nilai Valid',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Style header
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '059669'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Style column A (field names)
        $sheet->getStyle('A2:A9')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
        ]);

        // Add borders
        $sheet->getStyle('A1:C9')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Auto-size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Panduan';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 35,
            'C' => 40,
        ];
    }
}
