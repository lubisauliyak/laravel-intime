<?php

namespace App\Exports;

use App\Enums\Gender;
use App\Enums\MembershipType;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MembersRawExport implements FromCollection, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?array $memberIds = null;

    protected ?Collection $customCollection = null;

    protected int $rowCount = 0;

    /**
     * @param  array|Collection|null  $members  Array of IDs, Collection of Member, or null for all manageable
     */
    public function __construct(array|Collection|null $members = null)
    {
        if ($members instanceof Collection) {
            $this->customCollection = $members;
        } elseif (is_array($members)) {
            $this->memberIds = $members;
        }
    }

    public function collection()
    {
        if ($this->customCollection !== null) {
            return $this->customCollection;
        }

        $query = Member::query()
            ->with([
                'group.level',
                'group.parent.level',
                'group.parent.parent.level',
                'ageGroup',
                'positions.group',
                'positions.category',
            ]);

        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin()) {
            $manageableIds = $user->getManageableGroupIds();
            $query->whereIn('members.group_id', $manageableIds);
        }

        if ($this->memberIds !== null) {
            $query->whereIn('members.id', $this->memberIds);
        }

        return $query
            ->join('groups as kelompok', 'members.group_id', '=', 'kelompok.id')
            ->leftJoin('groups as desa', 'kelompok.parent_id', '=', 'desa.id')
            ->leftJoin('groups as daerah', 'desa.parent_id', '=', 'daerah.id')
            ->select('members.*')
            ->orderBy('daerah.name', 'asc')
            ->orderBy('desa.name', 'asc')
            ->orderBy('kelompok.name', 'asc')
            ->orderByRaw("FIELD(members.gender, '".Gender::MALE->value."', '".Gender::FEMALE->value."')")
            ->orderBy('members.full_name', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID Anggota',
            'Kelompok',
            'Nama Lengkap',
            'Nama Panggilan',
            'Tanggal Lahir',
            'L/P',
            'Desa',
            'Daerah',
            'Usia',
            'Kategori Usia',
            'Kepengurusan',
            'Detail Jabatan',
            'Status Verifikasi',
            'Status Aktif',
            'Status KTA',
        ];
    }

    public function map($member): array
    {
        $this->rowCount++;

        $genderCode = $member->gender instanceof Gender
            ? $member->gender->getCode()
            : (Gender::tryFrom($member->gender)?->getCode() ?? strtoupper((string) $member->gender));

        $birthDateStr = '';
        if ($member->birth_date) {
            try {
                $birthDateStr = Carbon::parse($member->birth_date)->format('d/m/Y');
            } catch (\Throwable $e) {
                $birthDateStr = (string) $member->birth_date;
            }
        }

        $desa = $member->group?->getParentAtLevel(2)?->name
            ?? $member->group?->parent?->name
            ?? '-';

        $daerah = $member->group?->getParentAtLevel(3)?->name
            ?? $member->group?->parent?->parent?->name
            ?? '-';

        $age = $member->age;
        if ($age === null && $member->birth_date) {
            try {
                $age = Carbon::parse($member->birth_date)->age;
            } catch (\Throwable $e) {
                $age = null;
            }
        }

        $isPengurus = ($member->membership_type instanceof MembershipType ? $member->membership_type : MembershipType::tryFrom($member->membership_type)) === MembershipType::PENGURUS
            || ($member->relationLoaded('positions') ? $member->positions->isNotEmpty() : $member->positions()->exists());

        $positionsText = '-';
        if ($isPengurus && $member->positions->isNotEmpty()) {
            $positionsText = $member->positions->map(function ($pos) {
                $parts = array_filter([
                    $pos->position_name,
                    $pos->category?->name,
                    $pos->group?->name,
                ]);

                return Str::title(strtolower(implode(' ', $parts)));
            })->filter()->unique()->implode(', ');
        }

        $hasKta = ! empty($member->membership_card_photo_path)
            && Storage::disk('public')->exists($member->membership_card_photo_path);

        return [
            $member->member_code ?? '',
            $member->group?->name ?? '',
            $member->full_name ?? '',
            $member->nick_name ?? '',
            $birthDateStr,
            $genderCode,
            $desa,
            $daerah,
            $age !== null ? (int) $age : '',
            $member->ageGroup?->name ?? 'Tanpa Kategori',
            $isPengurus ? 'Pengurus' : 'Anggota',
            $positionsText ?: '-',
            $member->is_permanent ? 'Disetujui' : 'Menunggu',
            $member->status ? 'Aktif' : 'Kurang Aktif',
            $hasKta ? 'Tersedia' : 'Belum Ada',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'I' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // ID Anggota
            'B' => 24, // Kelompok
            'C' => 32, // Nama Lengkap
            'D' => 16, // Nama Panggilan
            'E' => 16, // Tanggal Lahir
            'F' => 8,  // L/P
            'G' => 22, // Desa
            'H' => 22, // Daerah
            'I' => 10, // Usia
            'J' => 18, // Kategori Usia
            'K' => 16, // Kepengurusan
            'L' => 32, // Detail Jabatan
            'M' => 18, // Status Verifikasi
            'N' => 16, // Status Aktif
            'O' => 16, // Status KTA
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, $this->rowCount + 1);

        // Header Kolom A-F: Indigo (Kolom Utama / Kompatibel Impor)
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Header Kolom G-O: Slate Gray (Kolom Info Tambahan Sistem)
        $sheet->getStyle('G1:O1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4B5563'], // Slate Gray
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Border tipis untuk seluruh sel data
        $sheet->getStyle("A1:O{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        if ($this->rowCount > 0) {
            // General vertical center
            $sheet->getStyle("A2:O{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // Left align for Text names & descriptions
            $sheet->getStyle("B2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("L2:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Center align for codes, dates, numbers, badges
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M2:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Freeze pane pada baris 1
                $sheet->freezePane('A2');

                // Auto-filter pada header row
                $sheet->setAutoFilter('A1:O1');

                // Row height
                $sheet->getRowDimension(1)->setRowHeight(26);

                $lastRow = max(1, $this->rowCount + 1);
                for ($r = 2; $r <= $lastRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }
            },
        ];
    }
}
