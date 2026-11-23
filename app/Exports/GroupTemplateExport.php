<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class GroupTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new GroupTemplateSheet(),
            new GroupDocumentationSheet(),
        ];
    }
}

class GroupTemplateSheet implements FromArray, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected array $sampleData;

    public function __construct()
    {
        // Sample data untuk template
        $this->sampleData = [
            [
                'tingkat_grup' => 'DAERAH',
                'nama_grup' => 'DAERAH CONTOH 1',
                'induk_grup' => '',
                'kode_grup' => '10',
            ],
            [
                'tingkat_grup' => 'DESA',
                'nama_grup' => 'DESA CONTOH 1',
                'induk_grup' => 'DAERAH CONTOH 1',
                'kode_grup' => '07',
            ],
            [
                'tingkat_grup' => 'KELOMPOK',
                'nama_grup' => 'KELOMPOK CONTOH 1',
                'induk_grup' => 'DESA CONTOH 1',
                'kode_grup' => '01',
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
            'Tingkat Grup (Wajib)',
            'Nama Grup (Wajib)',
            'Induk Grup',
            'Kode Grup (Opsional)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['tingkat_grup'],
            $row['nama_grup'],
            $row['induk_grup'] ?? '',
            $row['kode_grup'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Style header - Biru Indigo
        $sheet->getStyle('A1:D1')->applyFromArray([
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

        // Explicit format column D as text to preserve leading zeros (e.g. '01', '07')
        $sheet->getStyle('D2:D1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Style data rows
        $sheet->getStyle('A2:D4')->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Add borders
        $sheet->getStyle('A1:D4')->applyFromArray([
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
        return 'Data Grup Baru';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // Tingkat Grup
            'B' => 35, // Nama Grup
            'C' => 35, // Induk Grup
            'D' => 25, // Kode Grup
        ];
    }
}

class GroupDocumentationSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function array(): array
    {
        return [
            [
                'Tingkat Grup (Wajib)',
                'Nama Tingkatan Grup (WAJIB)',
                "Harus sesuai dengan data yang ada pada menu 'Tingkatan Grup' di sistem.\nContoh: DAERAH, DESA, KELOMPOK, dsb.",
            ],
            [
                'Nama Grup (Wajib)',
                'Nama grup (WAJIB)',
                "Contoh: DESA SRUNI\nMaksimal 255 karakter.",
            ],
            [
                'Induk Grup',
                'Nama grup induk/atasan (OPSIONAL / TERGANTUNG TINGKATAN)',
                "Isi dengan nama grup di atasnya jika ada (harus sama persis dengan yang ada di sistem atau di baris atas).\nContoh: DAERAH SIDOARJO.\nUntuk tingkatan tertinggi, kolom ini bisa dikosongkan.",
            ],
            [
                'Kode Grup (Opsional)',
                'Kode/Prefix grup (OPSIONAL)',
                "Kode angka/karakter grup untuk merangkai prefix ID anggota otomatis (misal Daerah: 10, Desa: 07, Kelompok: 01 -> Prefix: 100701).\nAturan: Maksimal 10 karakter alfanumerik. Kode harus unik di bawah Induk Grup yang sama.\nJika dikosongkan, grup tidak memiliki kode dan anggota di dalamnya disimpan tanpa nomor ID otomatis.",
            ],
            [
                'ATURAN PENTING (Hirarki & Urutan Baris)',
                'Urutan baris sangat krusial jika mengimpor Induk dan Anak sekaligus!',
                "Sistem memproses baris dari ATAS ke BAWAH. Jika ingin membuat grup Anak dan Induknya secara bersamaan di file yang sama, pastikan baris data INDUK berada DI ATAS/SEBELUM baris data ANAK.\n\nContoh yang BENAR:\nBaris 2: DAERAH SIDOARJO (Induk)\nBaris 3: DESA SRUNI (Anak, Induknya: DAERAH SIDOARJO)\n\nJika terbalik (Anak di atas Induk), sistem akan menolak baris Anak dengan pesan error 'Induk Grup tidak ditemukan'."
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
        $sheet->getStyle('A2:A6')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'],
            ],
        ]);

        $sheet->getStyle('A2:C6')->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        // Highlight for ATURAN PENTING
        $sheet->getStyle('A6:C6')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF2F2'], // Red-50
            ],
            'font' => [
                'color' => ['rgb' => '991B1B'], // Red-800
            ]
        ]);

        // Add borders
        $sheet->getStyle('A1:C6')->applyFromArray([
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
            'C' => 80,
        ];
    }
}
