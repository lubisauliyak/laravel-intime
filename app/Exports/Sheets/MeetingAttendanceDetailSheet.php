<?php

namespace App\Exports\Sheets;

use App\Models\Meeting;
use App\Models\Member;
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

class MeetingAttendanceDetailSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $meeting;
    protected $rowCount = 0;

    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    public function title(): string
    {
        return 'Detail Anggota';
    }

    public function collection()
    {
        $allowedGroupIds = $this->meeting->group->getAllDescendantIds();
        
        return Member::whereIn('group_id', $allowedGroupIds)
            ->where('status', true)
            ->with(['group.parent.parent', 'attendances' => function ($q) {
                $q->where('meeting_id', $this->meeting->id);
            }])
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'ID',
            'Nama Lengkap',
            'Daerah',
            'Desa',
            'Grup / Kelompok',
            'Status Presensi',
            'Waktu Absen',
            'Metode',
            'Keterangan',
        ];
    }

    public function map($member): array
    {
        $this->rowCount++;
        $attendance = $member->attendances->first();
        $isOver = $this->meeting->meeting_date->setTimeFrom($this->meeting->end_time)->isPast();

        $status = 'BELUM HADIR';
        if ($attendance) {
            $status = strtoupper($attendance->status);
        } elseif ($isOver) {
            $status = 'TIDAK HADIR';
        }

        // Parent Groups & Base Group
        $daerah = $member->group->getParentAtLevel(1)?->name ?? '-';
        $desa = $member->group->getParentAtLevel(2)?->name ?? '-';
        $kelompok = $member->group->name; // Base level group name

        return [
            $this->rowCount,
            $member->member_code,
            $member->full_name,
            $daerah,
            $desa,
            $kelompok,
            $status,
            $attendance?->checkin_time ? $attendance->checkin_time->format('H:i') : '-',
            $attendance ? str_replace('_', ' ', strtoupper($attendance->method)) : '-',
            $attendance->notes ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5.33,  // No (40px)
            'B' => 10.67, // ID (80px)
            'C' => 33.33, // Nama (250px)
            'D' => 20.0,  // Daerah
            'E' => 20.0,  // Desa
            'F' => 20.0,  // Kelompok
            'G' => 16.0,  // Status (120px)
            'H' => 13.33, // Waktu (100px)
            'I' => 10.67, // Metode (80px)
            'J' => 33.33, // Keterangan (250px)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->rowCount + 6; // Shifted due to extra header line
        
        // Hide columns Daerah (D) and Desa (E) as requested
        $sheet->getColumnDimension('D')->setVisible(false);
        $sheet->getColumnDimension('E')->setVisible(false);

        // Global styles
        $sheet->getStyle("A6:J{$lastRow}")->applyFromArray([
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

        // Left align for Full Name
        $sheet->getStyle("C7:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Bold Heading
        $sheet->getStyle('A6:J6')->getFont()->setBold(true);

        // Title Styling
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1', 'LAPORAN DETAIL PRESENSI ANGGOTA');
                
                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2', "Pertemuan/Penyelenggara : {$this->meeting->name} / {$this->meeting->group->name}");
                
                $sheet->mergeCells('A3:J3');
                $startTime = $this->meeting->start_time?->format('H:i') ?? '-';
                $endTime = $this->meeting->end_time?->format('H:i') ?? '-';
                $sheet->setCellValue('A3', "Tanggal/Waktu : " . $this->meeting->meeting_date->translatedFormat('d F Y') . " ({$startTime} s.d {$endTime})");

                $sheet->mergeCells('A4:J4'); // Spacer
                $sheet->mergeCells('A5:J5'); // Spacer
            },
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rowCount + 6;

                // Set row height to 25px
                for ($i = 1; $i <= $lastRow; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(18.75);
                }
            },
        ];
    }
}
