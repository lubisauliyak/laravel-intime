<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\Level;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;

class GroupImportParser
{
    protected array $levelCache = [];
    protected array $groupCache = [];
    protected array $pendingGroups = [];
    protected array $existingCodesByParent = [];
    protected array $pendingCodesByParent = [];
    protected array $existingNamesByLevel = [];
    protected array $pendingNamesByLevel = [];
    protected array $manageableGroupIds = [];
    protected ?User $user = null;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
        $this->manageableGroupIds = $user ? $user->getManageableGroupIds() : [];

        $userCacheKey = $user ? "u{$user->id}" : 'public';
        $isSuperAdmin = $user ? $user->isSuperAdmin() : true;

        $this->levelCache = Cache::remember("import_level_cache_{$userCacheKey}", 3600, function () {
            return Level::all()->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
                return [strtoupper(trim($name)) => $id];
            })->toArray();
        });

        $manageableIds = $this->manageableGroupIds;
        $this->groupCache = Cache::remember("import_group_cache_{$userCacheKey}", 300, function () use ($manageableIds, $isSuperAdmin) {
            $query = Group::query();
            if (!$isSuperAdmin) {
                $query->whereIn('id', $manageableIds);
            }
            return $query->pluck('id', 'name')->mapWithKeys(function ($id, $name) {
                return [strtoupper(trim($name)) => $id];
            })->toArray();
        });

        $existing = Group::select('id', 'name', 'level_id', 'parent_id', 'code')
            ->when(!$isSuperAdmin, fn($q) => $q->whereIn('id', $manageableIds))
            ->get();

        foreach ($existing as $g) {
            $nameUpper = strtoupper(trim($g->name));
            if (!is_null($g->code)) {
                $pId = $g->parent_id ?? 0;
                $this->existingCodesByParent[$pId][strtoupper(trim((string) $g->code))] = $nameUpper;
            }

            if ($g->level_id) {
                $this->existingNamesByLevel[$g->level_id][$nameUpper] = [
                    'id' => $g->id,
                    'parent_id' => $g->parent_id,
                ];
            }
        }
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

        if (in_array($groupName, $this->pendingGroups)) {
            return true;
        }

        if (isset($this->groupCache[$groupName])) {
            return true;
        }

        $isSuperAdmin = $this->user ? $this->user->isSuperAdmin() : true;

        $query = Group::whereRaw('UPPER(name) = ?', [$groupName]);
        if (!$isSuperAdmin) {
            $query->whereIn('id', $this->manageableGroupIds);
        }
        $group = $query->first();

        if ($group) {
            $this->groupCache[$groupName] = $group->id;
            return true;
        }

        $queryLower = Group::whereRaw('LOWER(name) = ?', [strtolower($groupName)]);
        if (!$isSuperAdmin) {
            $queryLower->whereIn('id', $this->manageableGroupIds);
        }
        $group = $queryLower->first();

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

        $isSuperAdmin = $this->user ? $this->user->isSuperAdmin() : true;

        $query = Group::whereRaw('UPPER(name) = ?', [$groupName]);
        if (!$isSuperAdmin) {
            $query->whereIn('id', $this->manageableGroupIds);
        }
        $group = $query->first();

        if ($group) {
            $this->groupCache[$groupName] = $group->id;
            return $group->id;
        }

        return null;
    }

    /**
     * Ekstrak nilai field dari baris dengan mencoba beberapa variasi nama kolom
     */
    protected function extractField(Collection $row, array $candidateKeys): mixed
    {
        foreach ($candidateKeys as $key) {
            if ($row->has($key) && !is_null($row->get($key))) {
                return $row->get($key);
            }
        }

        return null;
    }

    /**
     * Parse file spreadsheet dan ekstrak baris valid/invalid untuk pratinjau
     */
    public function parse(string $filePath): array
    {
        $this->pendingGroups = [];
        $this->pendingCodesByParent = [];
        $this->pendingNamesByLevel = [];

        $sheetReader = new class implements ToCollection, WithHeadingRow, WithMultipleSheets {
            protected ?Collection $rows = null;

            public function sheets(): array
            {
                return [0 => $this];
            }

            public function collection(Collection $rows): void
            {
                $this->rows = $rows;
            }

            public function getRows(): Collection
            {
                return $this->rows ?? collect();
            }
        };

        Excel::import($sheetReader, $filePath);
        $rawRows = $sheetReader->getRows();

        $validRows = [];
        $invalidRows = [];
        $allRows = [];

        foreach ($rawRows as $index => $row) {
            $rowArray = $row->toArray();

            // Abaikan kolom catatan/error jika user mengunggah file CSV error perbaikan
            unset($rowArray['keterangan_error'], $rowArray['keterangan_masalah'], $rowArray['catatan_error']);

            // Jika seluruh nilai baris kosong, lewati
            $nonEmptyValues = array_filter($rowArray, function ($value) {
                return !is_null($value) && trim((string) $value) !== '';
            });

            if (empty($nonEmptyValues)) {
                continue;
            }

            $excelRowNumber = $index + 2; // Baris 1 adalah heading
            $rowErrors = [];

            // Ekstraksi nilai kolom fleksibel
            $rawLevel = $this->extractField($row, ['tingkat_grup_wajib', 'tingkat_grup', 'tingkat']);
            $rawName = $this->extractField($row, ['nama_grup_wajib', 'nama_grup', 'nama']);
            $rawParent = $this->extractField($row, ['induk_grup', 'induk']);
            $rawCode = $this->extractField($row, ['kode_grup_opsional', 'kode_grup', 'kode']);

            // Cek jika kolom wajib sama sekali tidak ditemukan pada header
            if ($rawLevel === null && $rawName === null && !array_key_exists('tingkat_grup_wajib', $rowArray) && !array_key_exists('nama_grup_wajib', $rowArray)) {
                $keys = implode(', ', array_keys($rowArray));
                $rowErrors[] = "Format Header/Kolom tidak sesuai. Kolom terdeteksi: [{$keys}]. Pastikan menggunakan Template resmi.";
            }

            $tingkatGrup = filled($rawLevel) ? trim((string) $rawLevel) : '';
            $namaGrup = filled($rawName) ? trim((string) $rawName) : '';
            $indukGrup = filled($rawParent) ? trim((string) $rawParent) : '';
            $kodeGrup = filled($rawCode) ? strtoupper(trim((string) $rawCode)) : null;

            // Validasi string dasar
            $validator = Validator::make([
                'tingkat_grup_wajib' => $tingkatGrup,
                'nama_grup_wajib' => $namaGrup,
                'induk_grup' => $indukGrup ?: null,
            ], [
                'tingkat_grup_wajib' => 'required|string|max:255',
                'nama_grup_wajib' => 'required|string|max:255',
                'induk_grup' => 'nullable|string|max:255',
            ], [
                'tingkat_grup_wajib.required' => 'Tingkat grup wajib diisi',
                'nama_grup_wajib.required' => 'Nama grup wajib diisi',
            ]);

            if ($validator->fails()) {
                $rowErrors = array_merge($rowErrors, $validator->errors()->all());
            }

            $levelId = null;
            if (!empty($tingkatGrup)) {
                $levelId = $this->findLevel($tingkatGrup);
                if (!$levelId) {
                    $rowErrors[] = "Tingkat Grup '{$tingkatGrup}' tidak ditemukan di sistem";
                }
            }

            $isNonSuperAdmin = $this->user && !$this->user->isSuperAdmin();
            if ($isNonSuperAdmin && empty($this->manageableGroupIds)) {
                $rowErrors[] = "Pengguna tidak memiliki wewenang pada grup manapun untuk melakukan impor data.";
            }

            $parentId = null;
            if (!empty($indukGrup)) {
                if (!$this->findGroup($indukGrup)) {
                    $rowErrors[] = "Induk Grup '{$indukGrup}' tidak ditemukan di sistem, dan belum/tidak dibuat pada baris di atasnya";
                } else {
                    $parentId = $this->getGroupId($indukGrup);
                }
            } else {
                if ($isNonSuperAdmin) {
                    if (!$this->findGroup($namaGrup)) {
                        $rowErrors[] = "Induk grup wajib diisi. Pengguna non-Super Admin tidak dapat membuat grup tingkat teratas baru.";
                    }
                }
            }

            $groupNameUpper = strtoupper($namaGrup);

            // Validasi Keunikan Nama Grup pada Tingkat (Level) yang Sama
            if (!empty($namaGrup) && $levelId) {
                // 1. Cek duplikasi di baris sebelumnya dalam file yang sama pada level yang sama
                if (isset($this->pendingNamesByLevel[$levelId][$groupNameUpper])) {
                    $prevRow = $this->pendingNamesByLevel[$levelId][$groupNameUpper];
                    $rowErrors[] = "Nama grup '{$namaGrup}' sudah digunakan pada tingkat '{$tingkatGrup}' di baris {$prevRow} pada file ini";
                }
                // 2. Cek duplikasi di database dalam scope yang sama pada level yang sama
                elseif (isset($this->existingNamesByLevel[$levelId][$groupNameUpper])) {
                    $existingInfo = $this->existingNamesByLevel[$levelId][$groupNameUpper];
                    // Jika parent_id berbeda, tolak karena nama sama pada level yang sama dalam scope
                    if ($parentId !== null && $existingInfo['parent_id'] !== $parentId) {
                        $rowErrors[] = "Nama grup '{$namaGrup}' sudah terdaftar pada tingkat '{$tingkatGrup}' di bawah induk lain dalam wilayah wewenang Anda";
                    } elseif ($parentId === null && $existingInfo['parent_id'] !== null) {
                        $rowErrors[] = "Nama grup '{$namaGrup}' sudah terdaftar pada tingkat '{$tingkatGrup}' di bawah induk lain dalam wilayah wewenang Anda";
                    }
                }
            }

            // Validasi Kode Grup jika diisi
            if ($kodeGrup !== null) {
                if (strlen($kodeGrup) > 10) {
                    $rowErrors[] = "Kode Grup '{$kodeGrup}' terlalu panjang (maksimal 10 karakter)";
                } else {
                    $parentLookupKey = !empty($indukGrup) ? strtoupper($indukGrup) : 'ROOT';
                    $parentLabel = !empty($indukGrup) ? "induk '{$indukGrup}'" : "tingkat teratas (tanpa induk)";

                    // 1. Cek duplikasi di baris sebelumnya dalam file yang sama
                    if (isset($this->pendingCodesByParent[$parentLookupKey][$kodeGrup])) {
                        $prevGroup = $this->pendingCodesByParent[$parentLookupKey][$kodeGrup];
                        if ($prevGroup !== $groupNameUpper) {
                            $rowErrors[] = "Kode Grup '{$kodeGrup}' sudah digunakan oleh grup '{$prevGroup}' di bawah {$parentLabel} pada file ini";
                        }
                    }

                    // 2. Cek duplikasi di database di bawah induk yang sama
                    $pDbId = $parentId ?? 0;
                    if (isset($this->existingCodesByParent[$pDbId][$kodeGrup])) {
                        $existingName = $this->existingCodesByParent[$pDbId][$kodeGrup];
                        if ($existingName !== $groupNameUpper) {
                            $rowErrors[] = "Kode Grup '{$kodeGrup}' sudah digunakan oleh grup '{$existingName}' di bawah {$parentLabel} di sistem";
                        }
                    }

                    // Catat ke memori pending
                    if (empty($rowErrors)) {
                        $this->pendingCodesByParent[$parentLookupKey][$kodeGrup] = $groupNameUpper;
                        if ($parentId) {
                            $this->pendingCodesByParent[$parentId][$kodeGrup] = $groupNameUpper;
                        }
                    }
                }
            }

            $isValid = empty($rowErrors);

            if ($isValid) {
                $this->pendingGroups[] = $groupNameUpper;
                if ($levelId) {
                    $this->pendingNamesByLevel[$levelId][$groupNameUpper] = $excelRowNumber;
                }
            }

            $rowResult = [
                'excel_row' => $excelRowNumber,
                'is_valid' => $isValid,
                'tingkat_grup' => $tingkatGrup,
                'nama_grup' => $namaGrup,
                'induk_grup' => $indukGrup,
                'kode_grup' => $kodeGrup,
                'level_id' => $levelId,
                'parent_id' => $parentId,
                'error_message' => $isValid ? null : implode(', ', $rowErrors),
                'raw_data' => $rowArray,
            ];

            $allRows[] = $rowResult;
            if ($isValid) {
                $validRows[] = $rowResult;
            } else {
                $invalidRows[] = $rowResult;
            }
        }

        return [
            'total_rows' => count($allRows),
            'valid_count' => count($validRows),
            'invalid_count' => count($invalidRows),
            'all_rows' => $allRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
        ];
    }
}
