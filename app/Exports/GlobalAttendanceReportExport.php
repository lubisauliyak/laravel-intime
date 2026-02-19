<?php

namespace App\Exports;

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

class GlobalAttendanceReportExport implements FromCollection, WithTitle, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents, WithCustomStartCell
{
    use Exportable;

    protected $filters;
    protected $user;
    protected $rowCount = 0;

    public function __construct(array $filters, $user)
    {
        $this->filters = $filters;
        $this->user = $user;
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
        $query = Member::query()->where('status', true);
        
        if (!$this->user->isSuperAdmin()) {
            if ($this->user->group_id) {
                $descendantIds = $this->user->group->getAllDescendantIds();
                $query->whereIn('group_id', $descendantIds);
            } else {
                return collect();
            }
        }

        // Apply group filter if present (Handle Filament SelectFilter nested structure)
        $groupId = null;
        if (isset($this->filters['group_id'])) {
            $groupId = is_array($this->filters['group_id']) 
                ? ($this->filters['group_id']['value'] ?? null) 
                : $this->filters['group_id'];
        }

        if ($groupId) {
            if (is_array($groupId)) {
                $query->whereIn('group_id', $groupId);
            } else {
                $query->where('group_id', $groupId);
            }
        }

        return $query->with('group')->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'ID',
            'Nama Anggota',
            'Grup',
            'Total Sesi',
            'Hadir',
            'Izin / Sakit',
            'Tanpa Keterangan',
            '% Kehadiran',
        ];
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
                    $query->orWhereJsonContains('target_age_groups', $member->ageGroup->name);
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

        return [
            $this->rowCount,
            $member->member_code,
            $member->full_name,
            $member->group->name,
            $totalSessions,
            $attended,
            $excused,
            $tanpaKeterangan,
            $rate,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 12,  // ID
            'C' => 30,  // Nama
            'D' => 20,  // Grup
            'E' => 12,  // Total Sesi
            'F' => 10,  // Hadir
            'G' => 15,  // Izin/Sakit
            'H' => 18,  // Alpa
            'I' => 15,  // %
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->rowCount + 5;
        
        $sheet->getStyle("A5:I{$lastRow}")->applyFromArray([
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

        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('A5:I5')->getFont()->setBold(true);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:I1');
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

                $sheet->mergeCells('A2:I2');
                $sheet->setCellValue('A2', "Periode: {$periodeText}");
                
                $sheet->mergeCells('A3:I3');
                $sheet->setCellValue('A3', "Dicetak oleh: " . $this->user->name);
                
                $sheet->mergeCells('A4:I4'); // Spacer
            },
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                for ($i = 1; $i <= $this->rowCount + 5; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }
            },
        ];
    }
}
