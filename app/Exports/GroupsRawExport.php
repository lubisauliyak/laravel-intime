<?php

namespace App\Exports;

use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use App\Services\Groups\GroupHierarchyService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class GroupsRawExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithColumnFormatting, WithEvents
{
    use Exportable;

    protected ?array $groupIds = null;
    protected ?Collection $customCollection = null;
    protected int $rowCount = 0;
    protected array $memberCountsMap = [];
    protected ?GroupHierarchyService $hierarchyService = null;

    /**
     * @param array|Collection|null $groups Array of IDs, Collection of Group, or null for all manageable
     */
    public function __construct(array|Collection|null $groups = null)
    {
        if ($groups instanceof Collection) {
            $this->customCollection = $groups;
        } elseif (is_array($groups)) {
            $this->groupIds = $groups;
        }

        $this->hierarchyService = app(GroupHierarchyService::class);

        // Preload member counts per direct group into memory for O(1) aggregation
        $this->memberCountsMap = Member::groupBy('group_id')
            ->selectRaw('group_id, count(*) as total')
            ->pluck('total', 'group_id')
            ->toArray();
    }

    public function collection()
    {
        if ($this->customCollection !== null) {
            return $this->customCollection;
        }

        $query = Group::query()
            ->with([
                'level',
                'parent.level',
                'cardTemplate',
            ]);

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $manageableIds = $user->getManageableGroupIds();
            $query->whereIn('groups.id', $manageableIds);
        }

        if ($this->groupIds !== null) {
            $query->whereIn('groups.id', $this->groupIds);
        }

        return $query
            ->addSelect([
                'level_sort' => Level::select('level_number')
                    ->whereColumn('levels.id', 'groups.level_id')
                    ->limit(1)
            ])
            ->orderBy('level_sort', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tingkat Grup (Wajib)',
            'Nama Grup (Wajib)',
            'Induk Grup',
            'Kode Grup (Opsional)',
            'Prefix ID Otomatis',
            'Template KTA Efektif',
            'Jumlah Anggota',
            'Status Aktif',
        ];
    }

    public function map($group): array
    {
        $this->rowCount++;

        // 1. Tingkat Grup
        $levelName = $group->level?->name ?? '';

        // 2. Nama Grup
        $groupName = $group->name;

        // 3. Induk Grup
        $parentName = $group->parent?->name ?? '';

        // 4. Kode Grup (as string to preserve leading zero)
        $code = ($group->code !== null && $group->code !== '') ? (string) $group->code : '';

        // 5. Prefix ID Otomatis
        $prefix = $this->hierarchyService->getCalculatedPrefix($group) ?? '-';

        // 6. Template KTA Efektif
        $resTmpl = $this->hierarchyService->getEffectiveCardTemplate($group);
        $templateName = '-';
        if ($resTmpl['template']) {
            $templateName = $resTmpl['is_locked']
                ? "🔒 {$resTmpl['template']->name} (Diwariskan)"
                : $resTmpl['template']->name;
        }

        // 7. Jumlah Anggota (Seluruh anggota di grup ini & sub-grupnya)
        $descendantIds = $group->getAllDescendantIds();
        $totalMembers = 0;
        foreach ($descendantIds as $dId) {
            $totalMembers += ($this->memberCountsMap[$dId] ?? 0);
        }

        // 8. Status Aktif
        $statusStr = $group->status ? 'Aktif' : 'Nonaktif';

        return [
            $levelName,
            $groupName,
            $parentName,
            $code,
            $prefix,
            $templateName,
            $totalMembers,
            $statusStr,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT, // Kode Grup
            'E' => NumberFormat::FORMAT_TEXT, // Prefix ID Otomatis
            'G' => '#,##0',                    // Jumlah Anggota
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22, // Tingkat Grup (Wajib)
            'B' => 32, // Nama Grup (Wajib)
            'C' => 28, // Induk Grup
            'D' => 20, // Kode Grup (Opsional)
            'E' => 20, // Prefix ID Otomatis
            'F' => 32, // Template KTA Efektif
            'G' => 18, // Jumlah Anggota
            'H' => 16, // Status Aktif
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max($this->rowCount + 1, 2);

        // Header height
        $sheet->getRowDimension(1)->setRowHeight(28);

        // 1. Header Kolom A–D: Indigo/Blue (Editable / Import-Ready)
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // 2. Header Kolom E–H: Slate/Gray (System Information / Read-Only)
        $sheet->getStyle('E1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4B5563'], // Gray-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // 3. Data Rows Styling
        if ($this->rowCount > 0) {
            // General border for all data cells
            $sheet->getStyle("A1:H{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'], // Slate-300
                    ],
                ],
            ]);

            // Alignment per column
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Row heights and zebra striping
            for ($r = 2; $r <= $lastRow; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(20);
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:H{$r}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'], // Slate-50
                        ],
                    ]);
                }
            }
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze pane at A2 (pin header row)
                $event->sheet->getDelegate()->freezePane('A2');

                // Enable Auto-Filter on header row
                $event->sheet->getDelegate()->setAutoFilter('A1:H1');
            },
        ];
    }
}

