<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MemberTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new TemplateSheet,
            new DocumentationSheet,
        ];
    }
}

class TemplateSheet implements FromArray, WithColumnFormatting, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected array $sampleData;

    public function __construct()
    {
        // Sample data untuk template
        $this->sampleData = [
            [
                'id_anggota' => '',
                'kelompok' => 'KELOMPOK CONTOH',
                'nama_lengkap' => 'CONTOH ANGGOTA PERTAMA',
                'nama_panggilan' => 'CONTOH',
                'tanggal_lahir' => '15/01/1990',
                'l_p' => 'L',
            ],
            [
                'id_anggota' => 'M202602190001',
                'kelompok' => 'KELOMPOK CONTOH',
                'nama_lengkap' => 'CONTOH ANGGOTA KEDUA',
                'nama_panggilan' => '',
                'tanggal_lahir' => '20/06/1995',
                'l_p' => 'P',
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
            'ID Anggota',
            'Kelompok (Wajib)',
            'Nama Lengkap (Wajib)',
            'Nama Panggilan',
            'Tanggal Lahir',
            'L/P (Wajib)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['id_anggota'] ?? '',
            $row['kelompok'],
            $row['nama_lengkap'],
            $row['nama_panggilan'] ?? '',
            $row['tanggal_lahir'] ?? '',
            $row['l_p'] ?? 'L',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Style header - Biru Indigo
        $sheet->getStyle('A1:F1')->applyFromArray([
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
        $sheet->getStyle('A2:F3')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Add borders
        $sheet->getStyle('A1:F3')->applyFromArray([
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
        return 'Data Anggota Baru';
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // ID Anggota
            'B' => 25, // Kelompok
            'C' => 30, // Nama Lengkap
            'D' => 20, // Nama Panggilan
            'E' => 15, // Tanggal Lahir
            'F' => 15, // L/P
        ];
    }
}

class DocumentationSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            [
                'ID Anggota',
                'Kode anggota (OPSIONAL)',
                "Kosongkan jika tujuannya membuat anggota baru.\nIsi jika ingin update data anggota yang sudah ada (contoh: M202602190001). Perhatian: sistem akan menimpa data lama dengan data baru di Excel, dan jangan gunakan ID anggota yang pernah dihapus sebelumnya (soft delete). Pastikan tidak ada spasi tersembunyi.",
            ],
            [
                'Kelompok (Wajib)',
                'Nama grup/kelompok (WAJIB)',
                'Harus sesuai dengan nama grup yang sudah terdaftar di sistem. Pastikan tidak ada spasi di awal/akhir nama.',
            ],
            [
                'Nama Lengkap (Wajib)',
                'Nama lengkap anggota (WAJIB)',
                "Contoh: BUDI SANTOSO\nMaksimal 255 karakter.",
            ],
            [
                'Nama Panggilan',
                'Nama panggilan (OPSIONAL)',
                'Contoh: BUDI',
            ],
            [
                'Tanggal Lahir',
                'Tanggal lahir (OPSIONAL)',
                'Format: DD/MM/YYYY (contoh: 15/01/1990). Kosongkan jika tidak ada.',
            ],
            [
                'L/P (Wajib)',
                'Jenis kelamin (WAJIB)',
                "Format wajib dan kaku:\nL = Laki-laki\nP = Perempuan\nHindari penulisan seperti 'Pria', 'Laki-laki', dsb untuk mencegah sistem memaksa nilai keliru.",
            ],
            [
                'PERHATIAN LAINNYA',
                'Batasan Skala Impor',
                'Harap jangan mengunggah data dalam jumlah sangat masif (misal 10.000+ baris) sekaligus tanpa dipisah, untuk mencegah Timeout Gateway/kehabisan memori.',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Kolom',
            'Keterangan',
            'Penjelasan & Aturan',
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
        $sheet->getStyle('A2:A8')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
        ]);

        $sheet->getStyle('A2:C8')->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        // Add borders
        $sheet->getStyle('A1:C8')->applyFromArray([
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
        return 'Panduan Pengisian';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 30,
            'C' => 70,
        ];
    }
}
