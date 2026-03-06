<?php

namespace App\Exports\Sheets;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\Attendance;
use App\Models\Member;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GlobalSummarySheet implements FromCollection, WithTitle, WithMapping, WithHeadings, WithStyles, WithColumnWidths, WithEvents, WithCustomStartCell
{
    protected $filters;
    protected $user;
    protected $meetings;
    protected $groupsData = [];
    protected $rowCount = 0;

    public function __construct(array $filters, $user, Collection $meetings)
    {
        $this->filters = $filters;
        $this->user = $user;
        $this->meetings = $meetings;
        $this->buildHierarchicalGroups();
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function startCell(): string
    {
        return 'A7';
    }

    protected function buildHierarchicalGroups(): void
    {
        $roots = $this->getRootGroups();
        foreach ($roots as $root) {
            $this->addGroupsRecursively($root, 0);
        }
    }

    protected function getRootGroups(): Collection
    {
        $groupId = $this->filters['group_id'] ?? null;
        if (is_array($groupId)) $groupId = $groupId['value'] ?? null;
        if ($groupId) return Group::where('id', $groupId)->where('status', true)->get();

        $desaId = $this->filters['parent_id'] ?? $this->filters['desa_id'] ?? null;
        if (is_array($desaId)) $desaId = $desaId['value'] ?? null;
        if ($desaId) return Group::where('id', $desaId)->where('status', true)->get();

        if (!$this->user->isSuperAdmin() && $this->user->group_id) {
            return Group::where('id', $this->user->group_id)->where('status', true)->get();
        }

        // For Superadmin with no filters:
        // Search levels from highest to lowest until we find at least one active group.
        $levels = \App\Models\Level::orderBy('level_number', 'desc')->get();
        foreach ($levels as $level) {
            $groups = Group::where('level_id', $level->id)->where('status', true)->orderBy('name')->get();
            if ($groups->isNotEmpty()) {
                return $groups;
            }
        }

        return collect();
    }

    protected function addGroupsRecursively(Group $group, int $depth): void
    {
        $this->groupsData[] = [
            'group' => $group,
            'depth' => $depth,
        ];

        foreach ($group->children()->where('status', true)->orderBy('name')->get() as $child) {
            $this->addGroupsRecursively($child, $depth + 1);
        }
    }

    public function collection()
    {
        return collect($this->groupsData);
    }

    public function headings(): array
    {
        $headers = ['Nama Grup', 'Level'];
        
        // We'll handle the sub-headings (H, I/S, A, T, %) in the events/styles
        // because we need merged cells for the Meeting dates.
        // This headings() just sets the text for Row 5/6 (depending on startCell).
        // Actually, we'll do the full header in registerEvents for better control.
        return [];
    }

    public function map($data): array
    {
        $this->rowCount++;
        $group = $data['group'];
        $depth = $data['depth'];
        
        $row = [
            str_repeat('    ', $depth) . $group->name,
            $group->level?->name ?? '-',
        ];

        $ageGroupsCount = \App\Models\AgeGroup::count();
        $groupDescendantIds = $group->getAllDescendantIds();

        foreach ($this->meetings as $meeting) {
            // 1. Determine common target group IDs (Overlap between group scope and meeting scope)
            $meetingTargetGroupIds = $meeting->getTargetGroupIds();
            $commonGroupIds = array_intersect($groupDescendantIds, $meetingTargetGroupIds);

            if (empty($commonGroupIds)) {
                $row = array_merge($row, ['-', '-', '-', '-', '-']);
                continue;
            }

            // 2. Query target members within the meeting's scope and current group
            $membersQuery = Member::whereIn('group_id', $commonGroupIds)
                ->where('status', true)
                ->whereDate('created_at', '<=', $meeting->meeting_date->toDateString());

            if ($meeting->target_gender !== 'all') {
                $membersQuery->where('gender', $meeting->target_gender);
            }

            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $ageGroupsCount;

            if ($shouldFilterByAge) {
                $membersQuery->whereHas('ageGroup', fn($q) => $q->whereIn('name', (array) $meeting->target_age_groups));
            }

            $targetMemberIds = $membersQuery->pluck('id');
            $totalTarget = $targetMemberIds->count();

            if ($totalTarget === 0) {
                // Not a target for this specific meeting instance (e.g. gender/age mismatch for this entire group)
                $row = array_merge($row, ['-', '-', '-', '-', '-']);
            } else {
                // 3. Calculate attendance stats
                $attendances = Attendance::where('meeting_id', $meeting->id)
                    ->whereIn('member_id', $targetMemberIds)
                    ->get();

                $hadir = $attendances->where('status', 'hadir')->count();
                $izinSakit = $attendances->whereIn('status', ['izin', 'sakit'])->count();
                $alpha = max(0, $totalTarget - $hadir - $izinSakit);
                $pct = round(($hadir / $totalTarget) * 100, 1) . '%';

                $row = array_merge($row, [$hadir, $izinSakit, $alpha, $totalTarget, $pct]);
            }
        }

        return $row;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 35,
            'B' => 15,
        ];
        
        $colCount = 2 + (count($this->meetings) * 5);
        for ($i = 3; $i <= $colCount; $i++) {
            $widths[Coordinate::stringFromColumnIndex($i)] = 7;
        }
        
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->rowCount + 6;
        $totalCols = 2 + (count($this->meetings) * 5);
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        // Data range styling
        if ($this->rowCount > 0) {
            $sheet->getStyle("A7:{$lastColLetter}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);
            $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            // Parent rows styling (Soft background)
            $rowIdx = 7;
            foreach ($this->groupsData as $data) {
                if ($data['group']->children()->where('status', true)->exists()) {
                    $sheet->getStyle("A{$rowIdx}:{$lastColLetter}{$rowIdx}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('CEDCEA');
                }
                
                // Percent color dots
                for ($m = 0; $m < count($this->meetings); $m++) {
                    $pctCell = Coordinate::stringFromColumnIndex(2 + (($m + 1) * 5)) . $rowIdx;
                    $val = $sheet->getCell($pctCell)->getValue();
                    if ($val && $val !== '-' && strpos($val, '%') !== false) {
                        $pct = (float) str_replace('%', '', $val);
                        $color = 'FFDC2626'; // Red
                        if ($pct >= 80) $color = 'FF15803D'; // Green
                        elseif ($pct >= 50) $color = 'FFB45309'; // Orange
                        $sheet->getStyle($pctCell)->getFont()->getColor()->setARGB($color);
                    }
                }
                $rowIdx++;
            }
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalCols = 2 + (count($this->meetings) * 5);
                $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

                // Title
                // $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->setCellValue('A1', 'RINGKASAN REKAPITULASI KEHADIRAN');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                // Periode
                $meetingDateFilter = $this->filters['meeting_date'] ?? [];
                $from = $meetingDateFilter['from'] ?? null;
                $until = $meetingDateFilter['until'] ?? null;
                $periodeText = 'Semua Waktu';
                if ($from && $until) {
                    $periodeText = Carbon::parse($from)->translatedFormat('d F Y') . ' s.d ' . Carbon::parse($until)->translatedFormat('d F Y');
                } elseif ($from) $periodeText = 'Mulai ' . Carbon::parse($from)->translatedFormat('d F Y');
                elseif ($until) $periodeText = 'Sampai ' . Carbon::parse($until)->translatedFormat('d F Y');

                // $sheet->mergeCells("A2:{$lastColLetter}2");
                $sheet->setCellValue('A2', "Periode: {$periodeText}");

                // Printer
                // $sheet->mergeCells("A3:{$lastColLetter}3");
                $sheet->setCellValue('A3', "Dicetak oleh: " . $this->user->name);
                
                // Headers Row 5-6
                $sheet->mergeCells("A5:A6");
                $sheet->setCellValue('A5', 'Nama Grup');
                $sheet->mergeCells("B5:B6");
                $sheet->setCellValue('B5', 'Level');

                $colIdx = 3;
                foreach ($this->meetings as $meeting) {
                    $startCol = Coordinate::stringFromColumnIndex($colIdx);
                    $endCol = Coordinate::stringFromColumnIndex($colIdx + 4);
                    $sheet->mergeCells("{$startCol}5:{$endCol}5");
                    $sheet->setCellValue("{$startCol}5", $meeting->meeting_date->format('d/m/y') . "\n" . $meeting->name);

                    $subHeaders = ['H', 'I/S', 'A', 'T', '%'];
                    for ($s = 0; $s < 5; $s++) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + $s) . '6', $subHeaders[$s]);
                    }
                    $colIdx += 5;
                }

                // Global Header Style
                $sheet->getStyle("A5:{$lastColLetter}6")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'font' => ['bold' => true],
                ]);
                
                // Top Align for meeting headers
                if (count($this->meetings) > 0) {
                    $sheet->getStyle("C5:{$lastColLetter}5")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $this->rowCount + 6;
                for ($i = 1; $i <= $lastRow; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }
            }
        ];
    }
}
