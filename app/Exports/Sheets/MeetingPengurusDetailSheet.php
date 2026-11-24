<?php

namespace App\Exports\Sheets;

use App\Enums\AttendanceStatus;
use App\Enums\MembershipType;
use App\Models\Meeting;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeetingPengurusDetailSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $meeting;

    protected $memberIds;

    protected $rowCount = 0;

    public function __construct(Meeting $meeting, $memberIds = null)
    {
        $this->meeting = $meeting;
        $this->memberIds = $memberIds;
    }

    public function title(): string
    {
        return 'Detail Pengurus';
    }

    public function collection()
    {
        return $this->meeting->attendances()
            ->whereIn('status', [AttendanceStatus::HADIR, AttendanceStatus::IZIN, AttendanceStatus::SAKIT])
            ->whereHas('member', function ($q) {
                $q->where(function ($q) {
                    $q->where('membership_type', MembershipType::PENGURUS)
                        ->orWhereHas('positions');
                });

                if ($this->memberIds !== null) {
                    $q->whereIn('id', $this->memberIds);
                }
            })
            ->with(['member.positions.category', 'member.positions.group.level', 'member.positions.group.parent'])
            ->get()
            ->sortBy(function ($attendance) {
                $primary = $attendance->member->getPrimaryPosition();

                return [
                    -($primary?->group?->level?->level_number ?? 0), // Level Tertinggi (Desc)
                    $primary?->category?->sort_order ?? 99,        // Kategori Pengurus
                    $primary?->sort_order ?? 99,                  // Urutan Pengurus
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
            'Pengurus',
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

        // Format pengurus like the infolist: "Position Category Level Group"
        $pengurus = $member->positions
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

        if (empty($pengurus)) {
            $pengurus = $member->membership_type instanceof MembershipType
                ? ($member->membership_type->getLabel() ?? '-')
                : ($member->membership_type ?? '-');
        }

        return [
            $this->rowCount,
            $member->member_code,
            $member->full_name,
            $pengurus,
            strtoupper($attendance->status->getLabel()),
            $attendance->checkin_time ? $attendance->checkin_time->format('H:i') : '-',
            $attendance->method ? strtoupper($attendance->method->getLabel()) : '-',
            $attendance?->notes ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5.33,   // No
            'B' => 10.67,  // ID
            'C' => 30.0,   // Nama Lengkap
            'D' => 55.0,   // Pengurus
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

        // Left align for Nama Lengkap and Pengurus
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
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'LAPORAN DETAIL PRESENSI PENGURUS');

                // $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', "Pertemuan/Penyelenggara : {$this->meeting->name} / {$this->meeting->group->name}");

                // $sheet->mergeCells('A3:H3');
                $startTime = $this->meeting->start_time?->format('H:i') ?? '-';
                $endTime = $this->meeting->end_time?->format('H:i') ?? '-';
                $sheet->setCellValue('A3', 'Tanggal/Waktu : '.$this->meeting->meeting_date->translatedFormat('d F Y')." ({$startTime} s.d {$endTime})");

                // $sheet->mergeCells('A4:H4'); // Spacer
                $sheet->setCellValue('A4', '');
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rowCount + 5;

                // Fixed height for headers
                for ($i = 1; $i <= 5; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(18.75);
                }

                // Minimal height 18.75, but auto-expand if multi-line Pengurus
                for ($i = 6; $i <= $lastRow; $i++) {
                    $pengurusValue = $sheet->getCell('D'.$i)->getValue();
                    if (strpos((string) $pengurusValue, "\n") !== false) {
                        $sheet->getRowDimension($i)->setRowHeight(-1);
                    } else {
                        $sheet->getRowDimension($i)->setRowHeight(18.75);
                    }
                }
            },
        ];
    }
}
