<?php

namespace App\Exports\Sheets;

use App\Models\Meeting;
use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MeetingPengurusDetailSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $meeting;
    protected $rowCount = 0;

    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    public function title(): string
    {
        return 'Detail Pengurus';
    }

    public function collection()
    {
        return $this->meeting->attendances()
            ->whereIn('status', ['hadir', 'izin', 'sakit'])
            ->whereHas('member', function ($q) {
                $q->whereIn('membership_type', ['pengurus', 'PENGURUS'])
                  ->orWhereHas('positions');
            })
            ->with(['member.positions.category', 'member.positions.group.level', 'member.positions.group.parent'])
            ->get()
            ->sortBy(function ($attendance) {
                $primary = $attendance->member->getPrimaryPosition();
                return [
                    -($primary?->group?->level?->level_number ?? 0), // Level Tertinggi (Desc)
                    $primary?->category?->sort_order ?? 99,        // Kategori Dapukan
                    $primary?->sort_order ?? 99,                  // Urutan Dapukan
                    $attendance->member->full_name,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'No',
            'ID',
            'Nama Lengkap',
            'Dapukan',
            'Status Presensi',
            'Waktu',
            'Metode',
            'Keterangan',
        ];
    }

    public function map($attendance): array
    {
        $this->rowCount++;

        $member = $attendance->member;

        // Format dapukan like the infolist: "Position Category Level Group"
        $dapukan = $member->positions
            ->sortBy([
                fn ($a, $b) => -($a->group?->level?->level_number <=> $b->group?->level?->level_number),
                fn ($a, $b) => ($a->category?->sort_order ?? 99) <=> ($b->category?->sort_order ?? 99),
                fn ($a, $b) => ($a->sort_order ?? 99) <=> ($b->sort_order ?? 99),
            ])
            ->map(function ($pos) {
                $parts = array_filter([
                    $pos->position_name ?? '-',
                    $pos->category?->name ?? '-',
                    $pos->group?->level?->name ?? '-',
                    $pos->group?->name ?? '-',
                ]);
                return implode(' ', $parts);
            })
            ->implode("\n");

        if (empty($dapukan)) {
            $dapukan = $member->membership_type ?? '-';
        }

        return [
            $this->rowCount,
            $member->member_code,
            $member->full_name,
            $dapukan,
            strtoupper($attendance->status),
            $attendance->checkin_time ? $attendance->checkin_time->format('H:i') : '-',
            $attendance->method ? str_replace('_', ' ', strtoupper($attendance->method)) : '-',
            $attendance->notes ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5.33,   // No
            'B' => 10.67,  // ID
            'C' => 30.0,   // Nama Lengkap
            'D' => 55.0,   // Dapukan
            'E' => 16.0,   // Status Presensi
            'F' => 10.67,  // Waktu
            'G' => 12.0,   // Metode
            'H' => 30.0,   // Keterangan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->rowCount + 5;

        // Global styles
        $sheet->getStyle("A5:H{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Left align for Nama Lengkap and Dapukan
        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("D6:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Left align for Keterangan
        $sheet->getStyle("H6:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Bold Heading
        $sheet->getStyle('A5:H5')->getFont()->setBold(true);

        // Title Styling
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'LAPORAN DETAIL PRESENSI PENGURUS');

                // $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', "Pertemuan/Penyelenggara : {$this->meeting->name} / {$this->meeting->group->name}");

                // $sheet->mergeCells('A3:H3');
                $startTime = $this->meeting->start_time?->format('H:i') ?? '-';
                $endTime = $this->meeting->end_time?->format('H:i') ?? '-';
                $sheet->setCellValue('A3', "Tanggal/Waktu : " . $this->meeting->meeting_date->translatedFormat('d F Y') . " ({$startTime} s.d {$endTime})");

                // $sheet->mergeCells('A4:H4'); // Spacer
                $sheet->setCellValue('A4', "");
            },
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rowCount + 5;

                // Fixed height for headers
                for ($i = 1; $i <= 5; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(18.75);
                }

                // Minimal height 18.75, but auto-expand if multi-line Dapukan
                for ($i = 6; $i <= $lastRow; $i++) {
                    $dapukanValue = $sheet->getCell('D' . $i)->getValue();
                    if (strpos((string)$dapukanValue, "\n") !== false) {
                        $sheet->getRowDimension($i)->setRowHeight(-1);
                    } else {
                        $sheet->getRowDimension($i)->setRowHeight(18.75);
                    }
                }
            },
        ];
    }
}
