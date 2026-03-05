<?php

namespace App\Exports\Sheets;

use App\Models\Meeting;
use App\Models\Group;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\MemberPosition;
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

    // Track table ranges for styling
    protected $table1StartRow;
    protected $table1EndRow;
    protected $table2TitleRow;
    protected $table2HeadingRow;
    protected $table2StartRow;
    protected $table2EndRow;
    protected $table2ParentRows = [];

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

        // === TABLE 1: Statistik Hierarki ===
        $this->addStatsRecursively($this->meeting->group, 0, $rows);
        // Table 1 data occupies rows from currentRow=1 to currentRow
        // Actual Excel rows: header at row 6, data starts at row 7
        $this->table1StartRow = 6; // heading row
        $this->table1EndRow = $this->currentRow + 6; // last data row

        // === SPACER: 3 empty rows ===
        $emptyRow = ['group_name' => '', 'level' => '', 'target' => '', 'hadir' => '', 'izin_sakit' => '', 'tidak_hadir' => '', 'percentage' => ''];
        $rows->push($emptyRow);
        $rows->push($emptyRow);
        $rows->push($emptyRow);
        $this->currentRow += 3;

        // === TABLE 2: Rekapitulasi Pengurus ===
        // Title row
        $rows->push(['group_name' => 'REKAPITULASI KEHADIRAN PENGURUS', 'level' => '', 'target' => '', 'hadir' => '', 'izin_sakit' => '', 'tidak_hadir' => '', 'percentage' => '']);
        $this->currentRow++;
        $this->table2TitleRow = $this->currentRow + 6;

        // Heading row
        $rows->push(['group_name' => 'Nama Grup', 'level' => 'Level', 'target' => '', 'hadir' => 'Hadir', 'izin_sakit' => '', 'tidak_hadir' => '', 'percentage' => '']);
        $this->currentRow++;
        $this->table2HeadingRow = $this->currentRow + 6;

        // Data rows
        $this->table2StartRow = $this->table2HeadingRow;
        $this->addPengurusStats($this->meeting->group, 0, $rows);
        $this->table2EndRow = $this->currentRow + 6;

        return $rows;
    }

    private function addStatsRecursively(Group $group, int $depth, $rows)
    {
        $descendantIds = $group->getAllDescendantIds();

        $allAgeGroupsCount = \App\Models\AgeGroup::count();
        $selectedAgeGroupsCount = empty($this->meeting->target_age_groups) ? 0 : count($this->meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

        $targetQuery = Member::whereIn('group_id', $descendantIds)
            ->where('status', true)
            ->when($this->meeting->target_gender !== 'all', function ($q) {
                return $q->where('gender', $this->meeting->target_gender);
            })
            ->when($shouldFilterByAge, function ($q) {
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
        
        $this->currentRow++;
        if ($hasChildren) {
            $this->parentRows[] = $this->currentRow + 6; // actual excel row
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

    /**
     * Add pengurus attendance stats.
     * Counts pengurus by their MemberPosition records (not just member).
     * A pengurus with positions in multiple groups counts in each.
     * At the lowest level (KELOMPOK), consolidate all children as "SEMUA".
     */
    private function addPengurusStats(Group $group, int $depth, $rows)
    {
        $hadir = $this->getPengurusHadirCountForGroup($group);
        
        $hasChildren = $group->children()->exists();
        $this->currentRow++;
        if ($hasChildren) {
            $this->table2ParentRows[] = $this->currentRow + 6;
        }

        $rows->push([
            'group_name' => str_repeat('    ', $depth) . $group->name,
            'level' => $group->level?->name ?? '-',
            'target' => '',
            'hadir' => $hadir,
            'izin_sakit' => '',
            'tidak_hadir' => '',
            'percentage' => '',
        ]);

        $children = $group->children()->orderBy('name')->get();
        if ($children->isNotEmpty()) {
            $firstChild = $children->first();
            // Jika anak adalah Level 1 (KELOMPOK), konsolidasi menjadi satu baris "SEMUA"
            if ($firstChild->level?->level_number === 1) {
                $totalHadir = 0;
                foreach ($children as $child) {
                    $totalHadir += $this->getPengurusHadirCountForGroup($child);
                }
                
                $this->currentRow++;
                $rows->push([
                    'group_name' => str_repeat('    ', $depth + 1) . 'SEMUA',
                    'level' => $firstChild->level?->name ?? 'KELOMPOK',
                    'target' => '',
                    'hadir' => $totalHadir,
                    'izin_sakit' => '',
                    'tidak_hadir' => '',
                    'percentage' => '',
                ]);
            } else {
                foreach ($children as $child) {
                    $this->addPengurusStats($child, $depth + 1, $rows);
                }
            }
        }
    }

    /**
     * Count pengurus who attended and have a position in this group's hierarchy.
     * Uses MemberPosition to identify pengurus, so members with multiple positions
     * across different groups are counted in each relevant group.
     */
    private function getPengurusHadirCountForGroup(Group $group)
    {
        $descendantIds = $group->getAllDescendantIds();

        // Get member IDs from MemberPosition that have positions in this group's descendants
        $pengurusMemberIds = MemberPosition::whereIn('group_id', $descendantIds)
            ->pluck('member_id')
            ->unique();

        // Also include members with membership_type 'pengurus' in these groups
        $pengurusByType = Member::whereIn('group_id', $descendantIds)
            ->where('status', true)
            ->whereIn('membership_type', ['pengurus', 'PENGURUS'])
            ->pluck('id');

        $allPengurusIds = $pengurusMemberIds->merge($pengurusByType)->unique();

        return Attendance::where('meeting_id', $this->meeting->id)
            ->whereIn('member_id', $allPengurusIds)
            ->where('status', 'hadir')
            ->count();
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
        // === TABLE 1 STYLES ===
        $sheet->getStyle("A{$this->table1StartRow}:G{$this->table1EndRow}")->applyFromArray([
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

        // Left align for "Nama Grup" in table 1
        $t1DataStart = $this->table1StartRow + 1;
        $sheet->getStyle("A{$t1DataStart}:A{$this->table1EndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Bold Headings for table 1
        $sheet->getStyle("A{$this->table1StartRow}:G{$this->table1StartRow}")->getFont()->setBold(true);

        // Soft Color for Parent Rows in table 1
        foreach ($this->parentRows as $row) {
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F1F5F9'); 
        }

        // === TABLE 2 STYLES ===
        // Title row: bold, no color, merge across columns
        $sheet->getStyle("A{$this->table2TitleRow}")->getFont()->setBold(true)->setSize(11);

        // Heading row + data rows: borders
        $sheet->getStyle("A{$this->table2HeadingRow}:G{$this->table2EndRow}")->applyFromArray([
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

        // Bold heading for table 2
        $sheet->getStyle("A{$this->table2HeadingRow}:G{$this->table2HeadingRow}")->getFont()->setBold(true);

        // Left align for "Nama Grup" in table 2
        $t2DataStart = $this->table2HeadingRow + 1;
        $sheet->getStyle("A{$t2DataStart}:A{$this->table2EndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Soft Color for Parent Rows in table 2
        foreach ($this->table2ParentRows as $row) {
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F1F5F9'); 
        }

        // === GLOBAL ===
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

                // Merge title row for table 2
                $sheet->mergeCells("A{$this->table2TitleRow}:G{$this->table2TitleRow}");
            },
        ];
    }
}
