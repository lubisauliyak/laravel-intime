<?php

namespace App\Imports;

use App\Models\Group;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MemberImportValidator implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected array $rowErrors = [];

    protected int $validCount = 0;

    protected array $groupCache = [];

    public function __construct()
    {
        $this->groupCache = Cache::remember('import_group_cache_validator', 3600, function () {
            return Group::all()->pluck('id', 'name')->toArray();
        });
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    protected function findGroup(string $groupName): ?int
    {
        $groupName = strtoupper(trim($groupName));
        if (isset($this->groupCache[$groupName])) {
            return $this->groupCache[$groupName];
        }

        $group = Group::whereRaw('UPPER(name) = ?', [$groupName])->first();
        if ($group) {
            $this->groupCache[$groupName] = $group->id;

            return $group->id;
        }

        $group = Group::whereRaw('LOWER(name) = ?', [strtolower($groupName)])->first();
        if ($group) {
            $this->groupCache[$groupName] = $group->id;

            return $group->id;
        }

        return null;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for 0-index, +1 for heading row

            // Jika row kosong (null) lewati saja
            if (empty(array_filter($row->toArray()))) {
                continue;
            }

            // Validate basic structure
            $validator = Validator::make($row->toArray(), [
                'nama_lengkap_wajib' => 'required|string|max:255',
                'kelompok_wajib' => 'required|string|max:255',
                'lp_wajib' => 'required|in:L,P,l,p',
            ], [
                'nama_lengkap_wajib.required' => 'Nama lengkap wajib diisi',
                'nama_lengkap_wajib.max' => 'Nama lengkap maksimal 255 karakter',
                'kelompok_wajib.required' => 'Nama kelompok wajib diisi',
                'kelompok_wajib.max' => 'Nama kelompok maksimal 255 karakter',
                'lp_wajib.required' => 'Jenis kelamin (L/P) wajib diisi',
                'lp_wajib.in' => 'Jenis kelamin harus diisi dengan L atau P',
            ]);

            if ($validator->fails()) {
                $this->rowErrors[$rowNumber] = array_merge($this->rowErrors[$rowNumber] ?? [], $validator->errors()->all());

                continue;
            }

            // Validasi id_anggota tidak berada di tong sampah (soft deleted)
            if (! empty($row['id_anggota'])) {
                $memberCode = trim($row['id_anggota']);
                $trashedMember = Member::onlyTrashed()->where('member_code', $memberCode)->first();
                if ($trashedMember) {
                    $this->rowErrors[$rowNumber][] = "ID Anggota '{$memberCode}' tidak dapat digunakan karena data tersebut berada di tong sampah (Soft Deleted).";

                    continue;
                }
            }

            // Validasi grup
            if (! $this->findGroup($row['kelompok_wajib'])) {
                $this->rowErrors[$rowNumber][] = "Grup '{$row['kelompok_wajib']}' tidak ditemukan di sistem";

                continue;
            }

            // Validasi format tanggal (jika ada)
            if (! empty($row['tanggal_lahir'])) {
                if (! $this->isValidDate($row['tanggal_lahir'])) {
                    $this->rowErrors[$rowNumber][] = "Format tanggal lahir '{$row['tanggal_lahir']}' tidak valid (gunakan DD/MM/YYYY atau YYYY-MM-DD)";

                    continue;
                }
            }

            $this->validCount++;
        }
    }

    protected function isValidDate($date): bool
    {
        if ($date instanceof \DateTime) {
            return true;
        }
        $dateStr = (string) $date;

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d', 'm/d/Y'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $dateStr);
            if ($parsed && $parsed->format($format) === $dateStr) {
                return true;
            }
        }

        // Cek jika ini angka Excel (integer serial date)
        if (is_numeric($dateStr)) {
            return true; // Biasanya date dari excel dibaca integer
        }

        return false;
    }

    public function getErrors(): array
    {
        $formatted = [];
        ksort($this->rowErrors); // Sort by row number
        foreach ($this->rowErrors as $rowNum => $errors) {
            $formatted[] = "Baris {$rowNum}: ".implode(', ', $errors);
        }

        return $formatted;
    }

    public function hasErrors(): bool
    {
        return count($this->rowErrors) > 0;
    }

    public function getValidCount(): int
    {
        return $this->validCount;
    }
}
