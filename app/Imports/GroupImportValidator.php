<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;
use App\Models\Level;
use App\Models\Group;
use Illuminate\Support\Facades\Cache;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GroupImportValidator implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    protected array $rowErrors = [];
    protected int $validCount = 0;
    
    protected array $levelCache = [];
    protected array $groupCache = [];
    
    // Untuk tracking grup yang akan dibuat di file ini (agar bisa jadi induk grup baris bawahnya)
    protected array $pendingGroups = [];

    // Tracking kode grup eksisting dari database per parent_id
    protected array $existingCodesByParent = [];

    // Tracking kode grup yang sedang divalidasi pada file ini per parent
    protected array $pendingCodesByParent = [];

    public function __construct()
    {
        $this->levelCache = Cache::remember('import_level_cache_validator', 3600, function () {
            return Level::all()->pluck('id', 'name')->toArray();
        });
        
        $this->groupCache = Cache::remember('import_group_cache_validator', 3600, function () {
            return Group::all()->pluck('id', 'name')->toArray();
        });

        // In-memory map: [parentId => [code => groupName]] (0 untuk root group tanpa induk)
        $existing = Group::select('id', 'name', 'parent_id', 'code')->whereNotNull('code')->get();
        foreach ($existing as $g) {
            $pId = $g->parent_id ?? 0;
            $this->existingCodesByParent[$pId][strtoupper(trim((string) $g->code))] = strtoupper(trim($g->name));
        }
    }

    public function sheets(): array
    {
        return [
            0 => $this, // Sheet pertama saja yang divalidasi
        ];
    }

    protected function findLevel(string $levelName): ?int
    {
        $levelName = strtoupper(trim($levelName));
        if (isset($this->levelCache[$levelName])) {
            return $this->levelCache[$levelName];
        }

        $level = Level::whereRaw('UPPER(name) = ?', [$levelName])->first();
        if ($level) {
            $this->levelCache[$levelName] = $level->id;
            return $level->id;
        }

        return null;
    }

    protected function findGroup(string $groupName): bool
    {
        $groupName = strtoupper(trim($groupName));
        
        // Cek di memori pending file excel ini dulu (kalau di-create di baris sebelumnya)
        if (in_array($groupName, $this->pendingGroups)) {
            return true;
        }

        if (isset($this->groupCache[$groupName])) {
            return true;
        }

        $group = Group::whereRaw('UPPER(name) = ?', [$groupName])->first();
        if ($group) {
            $this->groupCache[$groupName] = $group->id;
            return true;
        }

        $group = Group::whereRaw('LOWER(name) = ?', [strtolower($groupName)])->first();
        if ($group) {
            $this->groupCache[$groupName] = $group->id;
            return true;
        }

        return false;
    }

    protected function getGroupId(string $groupName): ?int
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

            $rowArray = $row->toArray();

            // Cek apakah key header sesuai ekspektasi
            if (!array_key_exists('tingkat_grup_wajib', $rowArray) || !array_key_exists('nama_grup_wajib', $rowArray)) {
                $keys = implode(', ', array_keys($rowArray));
                $this->rowErrors[$rowNumber][] = "Format Header/Kolom tidak sesuai. Kolom terdeteksi: [" . $keys . "]. Pastikan menggunakan Template terbaru.";
                continue;
            }

            // Validate basic structure
            $validator = Validator::make($rowArray, [
                'tingkat_grup_wajib' => 'required|string|max:255',
                'nama_grup_wajib' => 'required|string|max:255',
                'induk_grup' => 'nullable|string|max:255',
            ], [
                'tingkat_grup_wajib.required' => 'Tingkat grup wajib diisi',
                'nama_grup_wajib.required' => 'Nama grup wajib diisi',
            ]);

            if ($validator->fails()) {
                $this->rowErrors[$rowNumber] = array_merge($this->rowErrors[$rowNumber] ?? [], $validator->errors()->all());
                continue;
            }

            // Validasi Tingkat Grup
            if (!$this->findLevel($row['tingkat_grup_wajib'])) {
                $this->rowErrors[$rowNumber][] = "Tingkat Grup '{$row['tingkat_grup_wajib']}' tidak ditemukan di sistem";
                continue;
            }

            $parentId = null;
            // Validasi Induk Grup jika ada
            if (!empty($row['induk_grup'])) {
                if (!$this->findGroup($row['induk_grup'])) {
                    $this->rowErrors[$rowNumber][] = "Induk Grup '{$row['induk_grup']}' tidak ditemukan di sistem, dan belum/tidak dibuat pada baris di atasnya";
                    continue;
                }
                $parentId = $this->getGroupId($row['induk_grup']);
            }

            $groupName = strtoupper(trim($row['nama_grup_wajib']));

            // Ekstraksi & Validasi Kode Grup (Opsional)
            $codeRaw = $rowArray['kode_grup_opsional'] ?? $rowArray['kode_grup'] ?? $rowArray['kode'] ?? null;
            $groupCode = filled($codeRaw) ? strtoupper(trim((string) $codeRaw)) : null;

            if ($groupCode !== null) {
                if (strlen($groupCode) > 10) {
                    $this->rowErrors[$rowNumber][] = "Kode Grup '{$groupCode}' terlalu panjang (maksimal 10 karakter)";
                    continue;
                }

                $parentLookupKey = !empty($row['induk_grup']) ? strtoupper(trim($row['induk_grup'])) : 'ROOT';
                $parentLabel = !empty($row['induk_grup']) ? "induk '{$row['induk_grup']}'" : "tingkat teratas (tanpa induk)";

                // 1. Cek duplikasi kode di baris sebelumnya pada file Excel yang sama
                if (isset($this->pendingCodesByParent[$parentLookupKey][$groupCode])) {
                    $prevGroup = $this->pendingCodesByParent[$parentLookupKey][$groupCode];
                    if ($prevGroup !== $groupName) {
                        $this->rowErrors[$rowNumber][] = "Kode Grup '{$groupCode}' sudah digunakan oleh grup '{$prevGroup}' di bawah {$parentLabel} pada file ini";
                        continue;
                    }
                }

                // 2. Cek duplikasi kode terhadap database di bawah induk yang sama
                $pDbId = $parentId ?? 0;
                if (isset($this->existingCodesByParent[$pDbId][$groupCode])) {
                    $existingName = $this->existingCodesByParent[$pDbId][$groupCode];
                    if ($existingName !== $groupName) {
                        $this->rowErrors[$rowNumber][] = "Kode Grup '{$groupCode}' sudah digunakan oleh grup '{$existingName}' di bawah {$parentLabel} di sistem";
                        continue;
                    }
                }

                // Catat ke memori pending
                $this->pendingCodesByParent[$parentLookupKey][$groupCode] = $groupName;
                if ($parentId) {
                    $this->pendingCodesByParent[$parentId][$groupCode] = $groupName;
                }
            }

            // Simpan nama grup ini ke pending list (kalau sukses divalidasi)
            // agar bisa jadi induk untuk baris di bawahnya
            $this->pendingGroups[] = $groupName;

            $this->validCount++;
        }
    }

    public function getErrors(): array
    {
        $formatted = [];
        ksort($this->rowErrors); // Sort by row number
        foreach ($this->rowErrors as $rowNum => $errors) {
            $formatted[] = "Baris {$rowNum}: " . implode(', ', $errors);
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
