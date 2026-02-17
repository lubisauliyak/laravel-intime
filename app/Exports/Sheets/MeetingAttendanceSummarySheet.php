<?php

namespace App\Exports\Sheets;

use App\Models\Meeting;
use App\Models\Group;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MeetingAttendanceSummarySheet implements FromCollection, WithTitle, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $meeting;
    protected $currentRow = 0;
    protected $parentRows = [];

    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    public function title(): string
    {
        return 'Ringkasan Statistik';
    }

    public function collection()
    {
        $rows = collect();
        $this->addStatsRecursively($this->meeting->group, 0, $rows);
        return $rows;
    }

    private function addStatsRecursively(Group $group, int $depth, $rows)
    {
        $descendantIds = $group->getAllDescendantIds();
        
        $targetQuery = Member::whereIn('group_id', $descendantIds)
            ->where('status', true)
            ->when($this->meeting->target_gender !== 'all', function ($q) {
                return $q->where('gender', $this->meeting->target_gender);
            })
            ->when(!empty($this->meeting->target_age_groups), function ($q) {
                return $q->whereHas('ageGroup', function ($aq) {
                    return $aq->whereIn('name', $this->meeting->target_age_groups);
                });
            });

        $totalTarget = (clone $targetQuery)->count();
        $targetMemberIds = $targetQuery->pluck('id')->toArray();

        $attendances = Attendance::where('meeting_id', $this->meeting->id)
            ->whereIn('member_id', $targetMemberIds)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $hadir = $attendances->get('hadir', 0);
        $izinSakit = $attendances->get('izin', 0) + $attendances->get('sakit', 0);
        $tidakHadir = max(0, $totalTarget - $hadir - $izinSakit);
        
        $percentage = $totalTarget > 0 ? round(($hadir / $totalTarget) * 100, 1) : 0;

        $hasChildren = $group->children()->exists();
        
        // Track row index for styling (Header rows + Current Data Row)
        // Table starts at row 6 (Title=1, Desc=2, Date=3, Gap=4, Headings=5)
        $this->currentRow++;
        if ($hasChildren) {
            $this->parentRows[] = $this->currentRow + 5; 
        }

        $rows->push([
            'group_name' => str_repeat('    ', $depth) . $group->name,
            'level' => $group->level?->name ?? '-',
            'target' => $totalTarget,
            'hadir' => $hadir,
            'izin_sakit' => $izinSakit,
            'tidak_hadir' => $tidakHadir,
            'percentage' => $percentage . '%',
        ]);

        foreach ($group->children()->orderBy('name')->get() as $child) {
            $this->addStatsRecursively($child, $depth + 1, $rows);
        }
    }

    public function headings(): array
    {
        return [
            'Nama Grup',
            'Level',
            'Target Anggota',
            'Hadir',
            'Izin / Sakit',
            'Tidak Hadir',
            'Persentase Hadir',
        ];
    }

    public function map($row): array
    {
        return [
            $row['group_name'],
            $row['level'],
            $row['target'],
            $row['hadir'],
            $row['izin_sakit'],
            $row['tidak_hadir'],
            $row['percentage'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22.0,   // 165px
            'B' => 10.67,  // 80px
            'C' => 14.0,   // 105px
            'D' => 10.67,  // 80px
            'E' => 10.67,  // 80px
            'F' => 10.67,  // 80px
            'G' => 15.99,  // 115px
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->currentRow + 6; // +1 row for new header
        
        // Default Styles for all table cells
        $sheet->getStyle("A6:G{$lastRow}")->applyFromArray([
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

        // Left align for "Nama Grup"
        $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Bold Headings
        $sheet->getStyle('A6:G6')->getFont()->setBold(true);

        // Soft Color for Parent Rows
        foreach ($this->parentRows as $row) {
            $parentRow = $row + 1; // Shifted due to extra header line
            $sheet->getStyle("A{$parentRow}:G{$parentRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F1F5F9'); 
        }

        // Title Styling
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', 'LAPORAN RINGKASAN STATISTIK KEHADIRAN');
                
                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2', "Pertemuan/Penyelenggara : {$this->meeting->name} / {$this->meeting->group->name}");
                
                $sheet->mergeCells('A3:G3');
                $startTime = $this->meeting->start_time?->format('H:i') ?? '-';
                $endTime = $this->meeting->end_time?->format('H:i') ?? '-';
                $sheet->setCellValue('A3', "Tanggal/Waktu : " . $this->meeting->meeting_date->translatedFormat('d F Y') . " ({$startTime} s.d {$endTime})");

                $sheet->mergeCells('A4:G4'); // Spacer
                $sheet->mergeCells('A5:G5'); // Spacer
            },
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->currentRow + 6;

                for ($i = 1; $i <= $lastRow; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(18.75);
                }
            },
        ];
    }
}
