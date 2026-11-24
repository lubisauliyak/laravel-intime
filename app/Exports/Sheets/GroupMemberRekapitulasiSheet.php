<?php

namespace App\Exports\Sheets;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\MeetingTargetGender;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
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

class GroupMemberRekapitulasiSheet implements FromCollection, WithColumnWidths, WithCustomStartCell, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    protected Group $group;

    protected $filters;

    protected ?array $memberIds;

    protected $rowCount = 0;

    protected $meetings;

    protected $lastCollection;

    protected $ageGroupsCount;

    // Fixed columns count: No, ID, Nama, L/P, Kelompok, Kategori Usia, Pertemuan, Hadir, Telat, Izin, Sakit, Tidak Hadir, % Kehadiran = 13
    protected int $fixedColCount = 13;

    public function __construct(Group $group, array $filters = [], ?array $memberIds = null)
    {
        $this->group = $group;
        $this->filters = $filters;
        $this->memberIds = $memberIds;
        $this->meetings = $this->getRelevantMeetings();
        $this->ageGroupsCount = \App\Models\AgeGroup::count();
    }

    public function getMeetings()
    {
        return $this->meetings;
    }

    protected function getRelevantMeetings()
    {
        $from = data_get($this->filters, 'date_range.date_from');
        $until = data_get($this->filters, 'date_range.date_to');

        $levelValue = $this->filters['level'] ?? null;
        $levelId = null;
        if (is_array($levelValue)) {
            $levelId = $levelValue['value'] ?? $levelValue['is'] ?? $levelValue[0] ?? null;
        } elseif ($levelValue !== null && $levelValue !== '') {
            $levelId = $levelValue;
        }

        // Get all ancestor group IDs + group itself for meeting scope
        $groupIds = array_merge([$this->group->id], $this->group->getAllAncestorIds(), $this->group->getAllDescendantIds());
        $groupIds = array_unique($groupIds);

        $query = Meeting::query()
            ->with('group')
            ->whereIn('group_id', $groupIds)
            ->when($levelId, function ($q) use ($levelId) {
                $q->whereHas('group', fn ($g) => $g->where('level_id', $levelId));
            })
            ->when($from, fn ($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('meeting_date', '<=', $until));

        return $query->orderBy('meeting_date', 'asc')->get();
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function title(): string
    {
        return 'Rekapitulasi Kehadiran';
    }

    public function collection()
    {
        $descendantIds = $this->group->getAllDescendantIds();

        $ageGroupIds = data_get($this->filters, 'age_group.values');

        return $this->lastCollection = Member::query()
            ->select('members.*')
            ->join('groups', 'members.group_id', '=', 'groups.id')
            ->leftJoin('groups as parents', 'groups.parent_id', '=', 'parents.id')
            ->whereIn('members.group_id', $descendantIds)
            ->where('members.status', true)
            ->when($this->memberIds !== null, fn ($q) => $q->whereIn('members.id', $this->memberIds))
            ->when($ageGroupIds, fn ($q) => $q->whereIn('members.age_group_id', $ageGroupIds))
            ->with(['group.level', 'group.parent', 'ageGroup', 'attendances.meeting.group'])
            ->orderBy('parents.name', 'asc')
            ->orderBy('groups.name', 'asc')
            ->orderBy('members.gender', 'asc')
            ->orderBy('members.member_code', 'asc')
            ->get();
    }

    public function headings(): array
    {
        $headers = [
            'No',
            'ID',
            'Nama Anggota',
            'L/P',
            'Kelompok',
            'Kategori Usia',
            'Pertemuan',
            'Hadir',
            'Telat',
            'Izin',
            'Sakit',
            'Tidak Hadir',
            '% Kehadiran',
        ];

        foreach ($this->meetings as $meeting) {
            $headers[] = $meeting->meeting_date->format('d/m/y')."\n".$meeting->name;
        }

        return $headers;
    }

    public function map($member): array
    {
        $this->rowCount++;
        $from = data_get($this->filters, 'date_range.date_from');
        $until = data_get($this->filters, 'date_range.date_to');

        $levelValue = $this->filters['level'] ?? null;
        $levelId = null;
        if (is_array($levelValue)) {
            $levelId = $levelValue['value'] ?? $levelValue['is'] ?? $levelValue[0] ?? null;
        } elseif ($levelValue !== null && $levelValue !== '') {
            $levelId = $levelValue;
        }

        // 1. Calculate Total Sessions (Targeted) from pre-fetched meetings in-memory
        $totalSessions = $this->meetings->filter(fn ($m) => $this->isMemberTargetForMeeting($member, $m))->count();

        // 2. Get all attendances from pre-loaded relation in-memory
        $attendances = $member->attendances->filter(function ($att) use ($levelId, $from, $until) {
            if ($levelId && $att->meeting?->group?->level_id != $levelId) {
                return false;
            }
            if ($from && Carbon::parse($att->checkin_time)->format('Y-m-d') < $from) {
                return false;
            }
            if ($until && Carbon::parse($att->checkin_time)->format('Y-m-d') > $until) {
                return false;
            }

            return true;
        });

        $attendedMeetingIds = $attendances->pluck('meeting_id')->unique()->count();
        $total = max($totalSessions, $attendedMeetingIds);

        $hadir = 0;
        $telat = 0;
        $izin = 0;
        $sakit = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->status === AttendanceStatus::HADIR) {
                $hadir++;
                if ($attendance->meeting && $attendance->checkin_time > Carbon::parse($attendance->meeting->meeting_date)->format('Y-m-d').' '.Carbon::parse($attendance->meeting->start_time)->format('H:i:s')) {
                    $telat++;
                }
            } elseif ($attendance->status === AttendanceStatus::IZIN) {
                $izin++;
            } elseif ($attendance->status === AttendanceStatus::SAKIT) {
                $sakit++;
            }
        }

        $tidakHadir = max(0, $total - $hadir - $izin - $sakit);
        $rate = $total > 0 ? number_format(($hadir / $total) * 100, 1).'%' : '0%';

        $row = [
            $this->rowCount,
            $member->member_code,
            $member->full_name,
            strtoupper($member->gender === Gender::MALE ? 'L' : 'P'),
            $member->group->name,
            $member->ageGroup?->name ?? '-',
            (int) $total,
            (int) $hadir,
            (int) $telat,
            (int) $izin,
            (int) $sakit,
            (int) $tidakHadir,
            $rate,
        ];

        // 6. Meeting Status Columns
        foreach ($this->meetings as $meeting) {
            $attendance = $member->attendances->where('meeting_id', $meeting->id)->first();
            $isTarget = $this->isMemberTargetForMeeting($member, $meeting);

            if ($attendance) {
                $row[] = match ($attendance->status) {
                    AttendanceStatus::HADIR => 'H',
                    AttendanceStatus::IZIN => 'I',
                    AttendanceStatus::SAKIT => 'S',
                    default => '',
                };
            } elseif (! $isTarget) {
                $row[] = '-'; // Non-target and no attendance
            } else {
                $row[] = ''; // Target but no attendance record (Alpa)
            }
        }

        return $row;
    }

    protected function isMemberTargetForMeeting($member, $meeting)
    {
        // 1. Group check
        $memberGroupAncestors = array_merge([$member->group_id], $member->group->getAllAncestorIds());
        if (! in_array($meeting->group_id, $memberGroupAncestors)) {
            return false;
        }

        // 2. Date check
        if ($member->created_at->startOfDay()->gt($meeting->meeting_date->startOfDay())) {
            return false;
        }

        // 3. Gender check
        $targetGender = $meeting->target_gender instanceof MeetingTargetGender ? $meeting->target_gender->value : $meeting->target_gender;
        $memberGender = $member->gender instanceof Gender ? $member->gender->value : $member->gender;
        if ($targetGender !== 'all' && $targetGender !== $memberGender) {
            return false;
        }

        // 4. Age Group check
        if (! empty($meeting->target_age_groups)) {
            if ($member->ageGroup) {
                if (count((array) $meeting->target_age_groups) < $this->ageGroupsCount) {
                    if (! in_array($member->ageGroup->name, (array) $meeting->target_age_groups)) {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }

        return true;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,   // No
            'B' => 12,  // ID
            'C' => 30,  // Nama
            'D' => 5,   // L/P
            'E' => 20,  // Kelompok
            'F' => 18,  // Kategori Usia
            'G' => 12,  // Pertemuan
            'H' => 8,  // Hadir
            'I' => 8,   // Telat
            'J' => 8,   // Izin
            'K' => 8,   // Sakit
            'L' => 12,  // Tidak Hadir
            'M' => 15,  // % Kehadiran
        ];

        for ($i = 0; $i < count($this->meetings); $i++) {
            $letter = Coordinate::stringFromColumnIndex($this->fixedColCount + 1 + $i);
            $widths[$letter] = 12;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->rowCount + 5;
        $lastColNum = $this->fixedColCount + count($this->meetings);
        $lastColLetter = Coordinate::stringFromColumnIndex($lastColNum);

        $sheet->getStyle("A5:{$lastColLetter}{$lastRow}")->applyFromArray([
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

        // Meeting headers use top align
        if (count($this->meetings) > 0) {
            $firstMeetingCol = Coordinate::stringFromColumnIndex($this->fixedColCount + 1);
            $lastMeetingColLetter = Coordinate::stringFromColumnIndex($this->fixedColCount + count($this->meetings));
            $sheet->getStyle("{$firstMeetingCol}5:{$lastMeetingColLetter}5")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }

        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Force display 0 for summary columns
        $sheet->getStyle("G6:L{$lastRow}")->getNumberFormat()->setFormatCode('0');

        $sheet->getStyle("A5:{$lastColLetter}5")->getFont()->setBold(true);
        $sheet->getStyle("A5:{$lastColLetter}5")->getAlignment()->setWrapText(true);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColNum = $this->fixedColCount + count($this->meetings);
                $lastColLetter = Coordinate::stringFromColumnIndex($lastColNum);

                $sheet->setCellValue('A1', 'REKAPITULASI KEHADIRAN ANGGOTA - '.$this->group->name);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

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
                $sheet->setCellValue('A3', 'Dicetak oleh: '.auth()->user()->name);
                $sheet->setCellValue('A4', '');
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set row heights
                for ($i = 1; $i <= $this->rowCount + 5; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }

                // Apply gray background for non-target cells & Color for statuses
                if ($this->lastCollection && count($this->meetings) > 0) {
                    $rowIdx = 6;
                    foreach ($this->lastCollection as $member) {
                        $colIdx = $this->fixedColCount + 1;
                        foreach ($this->meetings as $meeting) {
                            $cell = Coordinate::stringFromColumnIndex($colIdx).$rowIdx;

                            $val = $sheet->getCell($cell)->getValue();
                            $isTarget = $this->isMemberTargetForMeeting($member, $meeting);

                            // Apply color for statuses
                            if ($val === 'H') {
                                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF15803D');
                            } elseif (in_array($val, ['I', 'S'])) {
                                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFB45309');
                            } elseif ($val === 'A') {
                                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFDC2626');
                            }

                            // If not target, apply gray background
                            if (! $isTarget) {
                                $sheet->getStyle($cell)->getFill()
                                    ->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFBFBFBF');
                            }
                            $colIdx++;
                        }
                        $rowIdx++;
                    }
                }
            },
        ];
    }
}
