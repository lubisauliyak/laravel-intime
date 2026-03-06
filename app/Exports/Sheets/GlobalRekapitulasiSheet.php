<?php

namespace App\Exports\Sheets;

use App\Models\Member;
use App\Models\Meeting;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
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
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class GlobalRekapitulasiSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents, WithCustomStartCell
{
    use Exportable;

    protected $filters;
    protected $user;
    protected $rowCount = 0;
    protected $meetings;
    protected $lastCollection;
    protected $ageGroupsCount;

    public function __construct(array $filters, $user)
    {
        $this->filters = $filters;
        $this->user = $user;
        $this->meetings = $this->getRelevantMeetings();
        $this->ageGroupsCount = \App\Models\AgeGroup::count();
    }

    public function getMeetings()
    {
        return $this->meetings;
    }

    protected function getRelevantMeetings()
    {
        $meetingDateFilter = $this->filters['meeting_date'] ?? [];
        $from = $meetingDateFilter['from'] ?? null;
        $until = $meetingDateFilter['until'] ?? null;

        $query = Meeting::query()
            ->when($from, fn ($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('meeting_date', '<=', $until));

        if (!$this->user->isSuperAdmin()) {
            if ($this->user->group_id) {
                $relatedIds = array_merge(
                    [$this->user->group_id],
                    $this->user->group->getAllAncestorIds(),
                    $this->user->group->getAllDescendantIds()
                );
                $query->whereIn('group_id', $relatedIds);
            }
        }

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
        $query = Member::query()
            ->select('members.*')
            ->join('groups', 'members.group_id', '=', 'groups.id')
            ->join('levels', 'groups.level_id', '=', 'levels.id')
            ->leftJoin('groups as parents', 'groups.parent_id', '=', 'parents.id')
            ->where('members.status', true);
        
        if (!$this->user->isSuperAdmin()) {
            if ($this->user->group_id) {
                $descendantIds = $this->user->group->getAllDescendantIds();
                $query->whereIn('members.group_id', $descendantIds);
            } else {
                return collect();
            }
        }

        // Apply Desa filter if present (parent_id is used for better schema alignment)
        $desaId = $this->filters['parent_id'] ?? $this->filters['desa_id'] ?? null;
        if (is_array($desaId)) {
            $desaId = $desaId['value'] ?? null;
        }
        
        if ($desaId) {
            $desa = \App\Models\Group::find($desaId);
            if ($desa) {
                $query->whereIn('members.group_id', $desa->getAllDescendantIds());
            }
        }

        // Apply group filter if present (Handle Filament SelectFilter nested structure)
        $groupId = $this->filters['group_id'] ?? null;
        if (is_array($groupId)) {
            $groupId = $groupId['value'] ?? null;
        }

        if ($groupId) {
            if (is_array($groupId)) {
                $query->whereIn('members.group_id', $groupId);
            } else {
                $query->where('members.group_id', $groupId);
            }
        }

        return $this->lastCollection = $query->with(['group', 'attendances', 'group.level', 'group.parent', 'ageGroup'])
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
            'Izin/Sakit',
            'Tidak Hadir',
            '% Kehadiran',
        ];

        foreach ($this->meetings as $meeting) {
            $headers[] = $meeting->meeting_date->format('d/m/y') . "\n" . $meeting->name;
        }

        return $headers;
    }

    public function map($member): array
    {
        $this->rowCount++;
        $meetingDateFilter = $this->filters['meeting_date'] ?? [];
        $from = $meetingDateFilter['from'] ?? null;
        $until = $meetingDateFilter['until'] ?? null;

        // 1. Calculate Total Sessions (Targeted)
        $groupIds = array_merge([$member->group_id], $member->group->getAllAncestorIds());
        $totalSessions = Meeting::whereIn('group_id', $groupIds)
            ->where(function ($query) use ($member) {
                $query->where('target_gender', 'all')
                    ->orWhere('target_gender', $member->gender);
            })
            ->where(function ($query) use ($member) {
                $query->where(function ($q) {
                    $q->whereNull('target_age_groups')
                        ->orWhereJsonLength('target_age_groups', 0);
                });

                if ($member->ageGroup) {
                    $allAgeGroupsCount = \App\Models\AgeGroup::count();
                    $query->orWhere(function($subQ) use ($member, $allAgeGroupsCount) {
                        $subQ->whereJsonContains('target_age_groups', $member->ageGroup->name)
                            ->where(function($innerQ) use ($allAgeGroupsCount) {
                                // Only apply age filter if NOT all age groups are selected
                                $innerQ->whereRaw("JSON_LENGTH(target_age_groups) < ?", [$allAgeGroupsCount]);
                            });
                    });
                }
            })
            ->when($from, fn ($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('meeting_date', '<=', $until))
            ->whereDate('meeting_date', '>=', $member->created_at->toDateString())
            ->count();

        // 2. Calculate Hadir
        $attended = $member->attendances()
            ->where('status', 'hadir')
            ->when($from, fn ($q) => $q->whereDate('checkin_time', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('checkin_time', '<=', $until))
            ->count();

        // 3. Calculate Izin / Sakit
        $excused = $member->attendances()
            ->whereIn('status', ['izin', 'sakit'])
            ->when($from, fn ($q) => $q->whereDate('checkin_time', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('checkin_time', '<=', $until))
            ->count();

        // 4. Calculate Tanpa Keterangan (Alpa)
        $tanpaKeterangan = max(0, $totalSessions - $attended - $excused);

        // 5. Calculate Rate
        $rate = $totalSessions > 0 ? number_format(($attended / $totalSessions) * 100, 1) . '%' : '0%';

        $totalSessionsInt = (int) ($totalSessions ?? 0);
        $attendedInt = (int) ($attended ?? 0);
        $excusedInt = (int) ($excused ?? 0);
        $tidakHadirInt = (int) ($tanpaKeterangan ?? 0);

        $row = [
            $this->rowCount,
            $member->member_code,
            $member->full_name,
            strtoupper($member->gender === 'male' ? 'L' : 'P'),
            $member->group->name,
            $member->ageGroup?->name ?? '-',
            $totalSessionsInt,
            $attendedInt,
            $excusedInt,
            $tidakHadirInt,
            $rate,
        ];

        // 6. Meeting Status Columns
        foreach ($this->meetings as $meeting) {
            $attendance = $member->attendances->where('meeting_id', $meeting->id)->first();
            $isTarget = $this->isMemberTargetForMeeting($member, $meeting);

            if ($attendance) {
                $row[] = match($attendance->status) {
                    'hadir' => 'H',
                    'izin' => 'I',
                    'sakit' => 'S',
                    default => '',
                };
            } elseif (!$isTarget) {
                $row[] = '-'; // Non-target and no attendance
            } else {
                $row[] = ''; // Target but no attendance record (Alpa)
            }
        }

        return $row;
    }

    protected function isMemberTargetForMeeting($member, $meeting)
    {
        // 1. Group check: meeting group must be ancestor of member group or member group itself
        $memberGroupAncestors = array_merge([$member->group_id], $member->group->getAllAncestorIds());
        if (!in_array($meeting->group_id, $memberGroupAncestors)) {
            return false;
        }

        // 2. Date check: member must be created before or on meeting date
        if ($member->created_at->startOfDay()->gt($meeting->meeting_date->startOfDay())) {
            return false;
        }

        // 3. Gender check
        if ($meeting->target_gender !== 'all' && $meeting->target_gender !== $member->gender) {
            return false;
        }

        // 4. Age Group check
        if (!empty($meeting->target_age_groups)) {
            if ($member->ageGroup) {
                if (count((array) $meeting->target_age_groups) < $this->ageGroupsCount) {
                    if (!in_array($member->ageGroup->name, (array) $meeting->target_age_groups)) {
                        return false;
                    }
                }
            } else {
                // If member has no age group but meeting targets specific ones
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
            'G' => 12,  // Total Sesi
            'H' => 12,  // Hadir
            'I' => 12,  // Izin/Sakit
            'J' => 12,  // Tidak Hadir
            'K' => 15,  // %
        ];

        for ($i = 0; $i < count($this->meetings); $i++) {
            $letter = Coordinate::stringFromColumnIndex(12 + $i);
            $widths[$letter] = 12;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->rowCount + 5;
        $lastColNum = 11 + count($this->meetings);
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
            $lastMeetingColNum = 11 + count($this->meetings);
            $lastMeetingColLetter = Coordinate::stringFromColumnIndex($lastMeetingColNum);
            $sheet->getStyle("L5:{$lastMeetingColLetter}5")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }

        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        // Force display 0 for summary columns
        $sheet->getStyle("G6:J{$lastRow}")->getNumberFormat()->setFormatCode('0');
        
        $sheet->getStyle("A5:{$lastColLetter}5")->getFont()->setBold(true);
        $sheet->getStyle("A5:{$lastColLetter}5")->getAlignment()->setWrapText(true);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColNum = 11 + count($this->meetings);
                $lastColLetter = Coordinate::stringFromColumnIndex($lastColNum);

                // $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->setCellValue('A1', 'REKAPITULASI KEHADIRAN ANGGOTA');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                
                $meetingDateFilter = $this->filters['meeting_date'] ?? [];
                $from = $meetingDateFilter['from'] ?? null;
                $until = $meetingDateFilter['until'] ?? null;
                
                $periodeText = 'Semua Waktu';
                if ($from && $until) {
                    $periodeText = Carbon::parse($from)->translatedFormat('d F Y') . ' s.d ' . Carbon::parse($until)->translatedFormat('d F Y');
                } elseif ($from) {
                    $periodeText = 'Mulai ' . Carbon::parse($from)->translatedFormat('d F Y');
                } elseif ($until) {
                    $periodeText = 'Sampai ' . Carbon::parse($until)->translatedFormat('d F Y');
                }

                // $sheet->mergeCells("A2:{$lastColLetter}2");
                $sheet->setCellValue('A2', "Periode: {$periodeText}");
                
                // $sheet->mergeCells("A3:{$lastColLetter}3");
                $sheet->setCellValue('A3', "Dicetak oleh: " . $this->user->name);
                
                // $sheet->mergeCells("A4:{$lastColLetter}4"); // Spacer
                $sheet->setCellValue('A4', "");
            },
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set row heights
                for ($i = 1; $i <= $this->rowCount + 5; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }

                // Apply gray background for non-target cells & Color for statuses
                if ($this->lastCollection && count($this->meetings) > 0) {
                    $rowIdx = 6;
                    foreach ($this->lastCollection as $member) {
                        $colIdx = 12;
                        foreach ($this->meetings as $meeting) {
                            $cell = Coordinate::stringFromColumnIndex($colIdx) . $rowIdx;
                            
                            $val = $sheet->getCell($cell)->getValue();
                            $isTarget = $this->isMemberTargetForMeeting($member, $meeting);
                            
                            // Apply color for statuses (H/I/S/A)
                            if ($val === 'H') {
                                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF15803D'); // Success Green
                            } elseif (in_array($val, ['I', 'S'])) {
                                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFB45309'); // Warning Orange
                            } elseif ($val === 'A') {
                                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFDC2626'); // Danger Red
                            }

                            // If not target, apply gray background (even if they attended)
                            if (!$isTarget) {
                                $sheet->getStyle($cell)->getFill()
                                    ->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFBFBFBF'); // Light gray
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
