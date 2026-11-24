<?php

namespace App\Exports\Sheets;

use App\Enums\AttendanceStatus;
use App\Enums\MeetingTargetGender;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupMemberSummarySheet implements FromCollection, WithColumnWidths, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Group $group;

    protected $filters;

    protected $meetings;

    protected $groupsData = [];

    protected $rowCount = 0;

    protected ?Collection $allMembers = null;

    protected ?Collection $attendancesByMeeting = null;

    protected int $ageGroupsCount = 0;

    protected array $meetingTargetGroupIds = [];

    public function __construct(Group $group, array $filters, Collection $meetings)
    {
        $this->group = $group;
        $this->filters = $filters;
        $this->meetings = $meetings;
        $this->buildHierarchicalGroups();
        $this->preloadData();
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function startCell(): string
    {
        return 'A7';
    }

    protected function preloadData(): void
    {
        $this->ageGroupsCount = \App\Models\AgeGroup::count();

        $ageGroupIds = data_get($this->filters, 'age_group.values');
        $allDescendantIds = $this->group->getAllDescendantIds();

        $this->allMembers = Member::whereIn('group_id', $allDescendantIds)
            ->where('status', true)
            ->when($ageGroupIds, fn ($q) => $q->whereIn('age_group_id', $ageGroupIds))
            ->with('ageGroup')
            ->get();

        $meetingIds = $this->meetings->pluck('id');
        $memberIds = $this->allMembers->pluck('id');

        $this->attendancesByMeeting = Attendance::whereIn('meeting_id', $meetingIds)
            ->whereIn('member_id', $memberIds)
            ->get(['id', 'meeting_id', 'member_id', 'status'])
            ->groupBy('meeting_id');
    }

    protected function buildHierarchicalGroups(): void
    {
        $descendantIds = $this->group->getAllDescendantIds();
        $groupsByParent = Group::whereIn('id', $descendantIds)
            ->with('level')
            ->where('status', true)
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        $root = Group::with('level')->find($this->group->id) ?? $this->group;
        $this->addGroupsRecursively($root, 0, $groupsByParent);
    }

    protected function addGroupsRecursively(Group $group, int $depth, Collection $groupsByParent): void
    {
        $this->groupsData[] = [
            'group' => $group,
            'depth' => $depth,
        ];

        $children = $groupsByParent->get($group->id, collect());
        foreach ($children as $child) {
            $this->addGroupsRecursively($child, $depth + 1, $groupsByParent);
        }
    }

    public function collection()
    {
        return collect($this->groupsData);
    }

    public function headings(): array
    {
        // Headers are built in registerEvents for better control with merged cells
        return [];
    }

    public function map($data): array
    {
        $this->rowCount++;
        $group = $data['group'];
        $depth = $data['depth'];

        $row = [
            str_repeat('    ', $depth).$group->name,
            $group->level?->name ?? '-',
        ];

        $groupDescendantIds = $group->getAllDescendantIds();

        foreach ($this->meetings as $meeting) {
            // 1. Determine common target group IDs
            $meetingTargetGroupIds = $this->meetingTargetGroupIds[$meeting->id] ??= $meeting->getTargetGroupIds();
            $commonGroupIds = array_intersect($groupDescendantIds, $meetingTargetGroupIds);

            if (empty($commonGroupIds)) {
                $row = array_merge($row, ['-', '-', '-', '-', '-']);

                continue;
            }

            $commonGroupLookup = array_flip($commonGroupIds);
            $meetingDateStr = $meeting->meeting_date->toDateString();
            $targetGender = $meeting->target_gender instanceof MeetingTargetGender ? $meeting->target_gender : MeetingTargetGender::tryFrom($meeting->target_gender);
            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count((array) $meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $this->ageGroupsCount;
            $targetAgeGroups = $shouldFilterByAge ? (array) $meeting->target_age_groups : [];

            // 2. Filter target members in-memory
            $targetMemberLookup = [];
            foreach ($this->allMembers as $member) {
                if (! isset($commonGroupLookup[$member->group_id])) {
                    continue;
                }
                if ($member->created_at->toDateString() > $meetingDateStr) {
                    continue;
                }
                if ($targetGender && $targetGender !== MeetingTargetGender::ALL) {
                    $mGender = $member->gender instanceof \App\Enums\Gender ? $member->gender->value : $member->gender;
                    if ($targetGender->value !== $mGender) {
                        continue;
                    }
                }
                if ($shouldFilterByAge) {
                    if (! $member->ageGroup || ! in_array($member->ageGroup->name, $targetAgeGroups, true)) {
                        continue;
                    }
                }
                $targetMemberLookup[$member->id] = true;
            }

            $totalTarget = count($targetMemberLookup);

            if ($totalTarget === 0) {
                $row = array_merge($row, ['-', '-', '-', '-', '-']);
            } else {
                // 3. Calculate attendance stats in-memory
                $meetingAttendances = $this->attendancesByMeeting->get($meeting->id, collect());
                $hadir = 0;
                $izinSakit = 0;

                foreach ($meetingAttendances as $attendance) {
                    if (isset($targetMemberLookup[$attendance->member_id])) {
                        if ($attendance->status === AttendanceStatus::HADIR) {
                            $hadir++;
                        } elseif ($attendance->status === AttendanceStatus::IZIN || $attendance->status === AttendanceStatus::SAKIT) {
                            $izinSakit++;
                        }
                    }
                }

                $alpha = max(0, $totalTarget - $hadir - $izinSakit);
                $pct = round(($hadir / $totalTarget) * 100, 1).'%';

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

            // Parent rows styling
            $rowIdx = 7;
            foreach ($this->groupsData as $data) {
                if ($data['group']->children()->where('status', true)->exists()) {
                    $sheet->getStyle("A{$rowIdx}:{$lastColLetter}{$rowIdx}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('CEDCEA');
                }

                // Percent color dots
                for ($m = 0; $m < count($this->meetings); $m++) {
                    $pctCell = Coordinate::stringFromColumnIndex(2 + (($m + 1) * 5)).$rowIdx;
                    $val = $sheet->getCell($pctCell)->getValue();
                    if ($val && $val !== '-' && strpos($val, '%') !== false) {
                        $pct = (float) str_replace('%', '', $val);
                        $color = 'FFDC2626'; // Red
                        if ($pct >= 80) {
                            $color = 'FF15803D';
                        } // Green
                        elseif ($pct >= 50) {
                            $color = 'FFB45309';
                        } // Orange
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
                $sheet->setCellValue('A1', 'RINGKASAN REKAPITULASI KEHADIRAN - '.$this->group->name);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                // Periode
                $from = data_get($this->filters, 'date_range.date_from');
                $until = data_get($this->filters, 'date_range.date_to');
                $periodeText = 'Semua Waktu';
                if ($from && $until) {
                    $periodeText = Carbon::parse($from)->translatedFormat('d F Y').' s.d '.Carbon::parse($until)->translatedFormat('d F Y');
                } elseif ($from) {
                    $periodeText = 'Mulai '.Carbon::parse($from)->translatedFormat('d F Y');
                } elseif ($until) {
                    $periodeText = 'Sampai '.Carbon::parse($until)->translatedFormat('d F Y');
                }

                $sheet->setCellValue('A2', "Periode: {$periodeText}");
                $sheet->setCellValue('A3', 'Dicetak oleh: '.(auth()->user()?->name ?? '-'));

                // Headers Row 5-6
                $sheet->mergeCells('A5:A6');
                $sheet->setCellValue('A5', 'Nama Grup');
                $sheet->mergeCells('B5:B6');
                $sheet->setCellValue('B5', 'Level');

                $colIdx = 3;
                foreach ($this->meetings as $meeting) {
                    $startCol = Coordinate::stringFromColumnIndex($colIdx);
                    $endCol = Coordinate::stringFromColumnIndex($colIdx + 4);
                    $sheet->mergeCells("{$startCol}5:{$endCol}5");
                    $sheet->setCellValue("{$startCol}5", $meeting->meeting_date->format('d/m/y')."\n".$meeting->name);

                    $subHeaders = ['H', 'I/S', 'A', 'T', '%'];
                    for ($s = 0; $s < 5; $s++) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + $s).'6', $subHeaders[$s]);
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
            },
        ];
    }
}
