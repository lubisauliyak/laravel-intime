<?php

namespace App\Services\Members;

use App\Enums\Gender;
use App\Enums\MembershipType;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use App\Services\Groups\GroupHierarchyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MemberImportParser
{
    /**
     * Cache kelompok untuk lookup cepat (uppercase & lowercase)
     */
    protected array $groupMap = [];

    /**
     * Cache ranking level kelompok untuk penentuan prioritas level terendah
     */
    protected array $groupLevelMap = [];

    /**
     * Cache kategori usia
     */
    protected Collection $ageGroups;

    /**
     * ID kategori usia Pra Nikah (default jika tanpa tanggal lahir)
     */
    protected ?int $defaultAgeGroupId = null;

    protected array $manageableGroupIds = [];

    protected ?User $user = null;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
        $this->manageableGroupIds = $user ? $user->getManageableGroupIds() : [];

        $this->loadGroupMap();
        $this->loadAgeGroups();
    }

    /**
     * Inisialisasi peta master grup/kelompok
     */
    protected function loadGroupMap(): void
    {
        $userCacheKey = $this->user ? "u{$this->user->id}" : 'public';
        $isSuperAdmin = $this->user ? $this->user->isSuperAdmin() : true;
        $manageableIds = $this->manageableGroupIds;

        $groups = Cache::remember("member_import_group_map_{$userCacheKey}", 300, function () use ($manageableIds, $isSuperAdmin) {
            $query = Group::with('level')->select(['id', 'name', 'level_id']);
            if (! $isSuperAdmin) {
                $query->whereIn('id', $manageableIds);
            }

            return $query->get()->toArray();
        });

        foreach ($groups as $group) {
            $nameClean = trim($group['name']);
            $upper = strtoupper($nameClean);
            $lower = strtolower($nameClean);

            // Prioritaskan level terendah (level_number paling kecil, misal Kelompok = 1)
            $currLevelNumber = $group['level']['level_number'] ?? 99;
            if (! isset($this->groupLevelMap[$upper]) || $currLevelNumber < $this->groupLevelMap[$upper]) {
                $this->groupMap[$upper] = $group['id'];
                $this->groupMap[$lower] = $group['id'];
                $this->groupLevelMap[$upper] = $currLevelNumber;
            }
        }
    }

    /**
     * Inisialisasi master kelompok usia
     */
    protected function loadAgeGroups(): void
    {
        $this->ageGroups = Cache::remember('member_import_age_groups', 3600, function () {
            return AgeGroup::all();
        });

        // Cari kategori default berdasarkan unbound limits atau sort_order pertama
        $defaultGroup = $this->ageGroups->first(function ($item) {
            return is_null($item->min_age) && is_null($item->max_age);
        }) ?? $this->ageGroups->sortBy('sort_order')->first();

        $this->defaultAgeGroupId = $defaultGroup?->id;
    }

    /**
     * Parse file spreadsheet secara dry-run dan ekstrak baris valid/invalid
     *
     * @param  string  $filePath  Path lengkap file spreadsheet
     */
    public function parse(string $filePath): array
    {
        $sheetReader = new class implements ToCollection, WithHeadingRow, WithMultipleSheets
        {
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

        // 1. Kumpulkan semua id_anggota untuk bulk lookup query
        $memberCodes = [];
        foreach ($rawRows as $row) {
            $code = $this->extractField($row, ['id_anggota', 'kode_anggota']);
            if (! empty($code)) {
                $memberCodes[] = trim((string) $code);
            }
        }

        $existingMembers = collect();
        $trashedCodes = [];

        if (! empty($memberCodes)) {
            $membersInDb = Member::withTrashed()
                ->whereIn('member_code', array_unique($memberCodes))
                ->get();

            foreach ($membersInDb as $m) {
                if ($m->trashed()) {
                    $trashedCodes[$m->member_code] = true;
                } else {
                    $existingMembers[$m->member_code] = $m;
                }
            }
        }

        // 2. Evaluasi baris per baris
        $validRows = [];
        $invalidRows = [];
        $allRows = [];

        $dataRowIndex = 0;

        foreach ($rawRows as $index => $row) {
            $rowArray = $row->toArray();

            // Abaikan kolom 'keterangan_error' jika user mengunggah ulang file CSV error sebelumnya
            unset($rowArray['keterangan_error'], $rowArray['keterangan_masalah'], $rowArray['catatan_error']);

            // Jika seluruh nilai baris kosong, lewati
            $nonEmptyValues = array_filter($rowArray, function ($value) {
                return ! is_null($value) && trim((string) $value) !== '';
            });

            if (empty($nonEmptyValues)) {
                continue;
            }

            $dataRowIndex++;
            $excelRowNumber = $index + 2; // Baris 1 adalah heading

            $rowErrors = [];

            $isNonSuperAdmin = $this->user && ! $this->user->isSuperAdmin();
            if ($isNonSuperAdmin && empty($this->manageableGroupIds)) {
                $rowErrors[] = 'Pengguna tidak memiliki wewenang pada grup manapun untuk melakukan impor data.';
            }

            // Ekstraksi nilai kolom fleksibel
            $rawMemberCode = $this->extractField($row, ['id_anggota', 'kode_anggota']);
            $rawName = $this->extractField($row, ['nama_lengkap_wajib', 'nama_lengkap', 'nama']);
            $rawNickName = $this->extractField($row, ['nama_panggilan', 'panggilan']);
            $rawGroup = $this->extractField($row, ['kelompok_wajib', 'kelompok', 'grup']);
            $rawGender = $this->extractField($row, ['lp_wajib', 'l_p_wajib', 'lp', 'l_p', 'jenis_kelamin']);
            $rawBirthDate = $this->extractField($row, ['tanggal_lahir', 'tgl_lahir']);

            // --- Validasi 1: Nama Lengkap (Wajib) ---
            $name = trim((string) $rawName);
            if (empty($name)) {
                $rowErrors[] = '[Nama Lengkap]: Wajib diisi';
            } elseif (mb_strlen($name) > 255) {
                $rowErrors[] = '[Nama Lengkap]: Maksimal 255 karakter';
            }

            // --- Validasi 2: Kelompok (Wajib) & Rantai Hierarki ---
            $groupName = trim((string) $rawGroup);
            $groupId = null;
            $chainValidation = null;

            if (empty($groupName)) {
                $rowErrors[] = '[Kelompok]: Nama kelompok wajib diisi';
            } else {
                $groupId = $this->findGroupId($groupName);
                if (! $groupId) {
                    $rowErrors[] = "[Kelompok]: Kelompok '{$groupName}' tidak ditemukan di master data";
                } else {
                    $groupRecord = Group::with('level')->find($groupId);
                    if ($groupRecord) {
                        $chainValidation = app(GroupHierarchyService::class)->validateChainIntegrity($groupRecord);
                        if (! $chainValidation['is_valid']) {
                            $rowErrors[] = "[Kelompok]: {$chainValidation['message']}";
                        }
                    }
                }
            }

            // --- Validasi 3: Jenis Kelamin (Wajib: L/P) ---
            $genderStr = strtoupper(trim((string) $rawGender));
            $gender = null;
            if (empty($genderStr)) {
                $rowErrors[] = '[Jenis Kelamin]: Jenis kelamin (L/P) wajib diisi';
            } elseif (! in_array($genderStr, ['L', 'P'])) {
                $rowErrors[] = "[Jenis Kelamin]: Format '{$genderStr}' tidak valid. Wajib diisi 'L' atau 'P'";
            } else {
                $gender = ($genderStr === 'P') ? Gender::FEMALE->value : Gender::MALE->value;
            }

            // --- Validasi 4: ID Anggota & Klasifikasi Status ID ---
            $memberCode = ! empty($rawMemberCode) ? trim((string) $rawMemberCode) : null;
            $isUpdate = false;
            $existingMember = null;
            $idStatus = 'no_code';
            $idStatusLabel = 'Tanpa ID';
            $calculatedPrefix = null;

            if ($chainValidation && ! $chainValidation['is_valid']) {
                $idStatus = 'broken_chain';
                $idStatusLabel = 'Rantai Putus';
            } elseif ($memberCode) {
                if (isset($trashedCodes[$memberCode])) {
                    $rowErrors[] = "[ID Anggota]: ID '{$memberCode}' tidak dapat digunakan karena berada di tong sampah";
                    $idStatus = 'invalid';
                    $idStatusLabel = 'Di Tong Sampah';
                } elseif (isset($existingMembers[$memberCode])) {
                    $isUpdate = true;
                    $existingMember = $existingMembers[$memberCode];
                    $idStatus = 'update';
                    $idStatusLabel = 'Update / Menimpa';
                } else {
                    $idStatus = 'manual_id';
                    $idStatusLabel = 'ID Manual (Baru)';
                }
            } else {
                // Kolom ID kosong di Excel
                if ($chainValidation && $chainValidation['status'] === 'valid') {
                    $idStatus = 'auto_id';
                    $idStatusLabel = 'ID Otomatis';
                    $calculatedPrefix = $chainValidation['prefix'];
                } else {
                    $idStatus = 'no_code';
                    $idStatusLabel = 'Tanpa ID';
                }
            }

            // --- Validasi 5: Tanggal Lahir (Opsional) ---
            $birthDate = null;
            $age = null;
            $ageGroupId = null;

            if (! empty($rawBirthDate)) {
                $parsedDate = $this->parseDate($rawBirthDate);
                if (! $parsedDate) {
                    $rowErrors[] = "[Tanggal Lahir]: Format '{$rawBirthDate}' tidak valid. Gunakan format DD/MM/YYYY";
                } else {
                    $birthDate = $parsedDate;
                    $age = $this->calculateAge($birthDate);
                    $ageGroupId = $this->determineAgeGroup($age);
                }
            } else {
                $ageGroupId = $this->defaultAgeGroupId;
            }

            // Bangun representasi data
            $parsedRecord = [
                'excel_row' => $excelRowNumber,
                'member_code' => $memberCode,
                'full_name' => mb_strtoupper($name),
                'nick_name' => ! empty($rawNickName) ? mb_strtoupper(trim((string) $rawNickName)) : null,
                'group_name' => $groupName,
                'group_id' => $groupId,
                'gender_label' => $genderStr ?: '-',
                'gender' => $gender,
                'birth_date' => $birthDate,
                'age' => $age,
                'age_group_id' => $ageGroupId,
                'is_update' => $isUpdate,
                'id_status' => $idStatus,
                'id_status_label' => $idStatusLabel,
                'calculated_prefix' => $calculatedPrefix,
                'status' => true,
                'membership_type' => MembershipType::ANGGOTA->value,
                'raw_data' => [
                    'id_anggota' => $memberCode ?? '',
                    'kelompok_wajib' => $groupName,
                    'nama_lengkap_wajib' => $name,
                    'nama_panggilan' => $rawNickName ?? '',
                    'tanggal_lahir' => $rawBirthDate ?? '',
                    'lp_wajib' => $genderStr,
                ],
            ];

            if (count($rowErrors) > 0) {
                $parsedRecord['is_valid'] = false;
                $parsedRecord['errors'] = $rowErrors;
                $parsedRecord['error_message'] = implode('; ', $rowErrors);

                $invalidRows[] = $parsedRecord;
            } else {
                $parsedRecord['is_valid'] = true;
                $parsedRecord['errors'] = [];
                $parsedRecord['error_message'] = null;

                $validRows[] = $parsedRecord;
            }

            $allRows[] = $parsedRecord;
        }

        return [
            'total_rows' => count($allRows),
            'valid_count' => count($validRows),
            'invalid_count' => count($invalidRows),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'all_rows' => $allRows,
        ];
    }

    /**
     * Mencari ID kelompok secara case-insensitive
     */
    protected function findGroupId(string $groupName): ?int
    {
        $trimmed = trim($groupName);
        $upper = strtoupper($trimmed);

        if (isset($this->groupMap[$upper])) {
            return $this->groupMap[$upper];
        }

        $lower = strtolower($trimmed);
        if (isset($this->groupMap[$lower])) {
            return $this->groupMap[$lower];
        }

        // Fallback query jika master data baru bertambah tanpa clear cache
        $isSuperAdmin = $this->user ? $this->user->isSuperAdmin() : true;
        $query = Group::with('level')->whereRaw('UPPER(name) = ?', [$upper]);
        if (! $isSuperAdmin) {
            $query->whereIn('id', $this->manageableGroupIds);
        }
        $group = $query->get()->sortBy(fn ($g) => $g->level?->level_number ?? 99)->first();
        if ($group) {
            $this->groupMap[$upper] = $group->id;
            $this->groupMap[$lower] = $group->id;
            $this->groupLevelMap[$upper] = $group->level?->level_number ?? 99;

            return $group->id;
        }

        return null;
    }

    /**
     * Parse tanggal dari berbagai format (DD/MM/YYYY, serial excel, dsb)
     */
    protected function parseDate($date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $dateStr = trim((string) $date);
        if ($dateStr === '') {
            return null;
        }

        // Format angka serial Excel (misal: 44197)
        if (is_numeric($dateStr) && (int) $dateStr > 1000 && (int) $dateStr < 100000) {
            try {
                return ExcelDate::excelToDateTimeObject((int) $dateStr)->format('Y-m-d');
            } catch (\Throwable $e) {
                // Lanjut ke string parsing
            }
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d', 'm/d/Y'];
        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $dateStr);
                if ($parsed && $parsed->format($format) === $dateStr) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Hitung usia berdasarkan tanggal lahir
     */
    protected function calculateAge(string $birthDate): int
    {
        return Carbon::parse($birthDate)->age;
    }

    /**
     * Menentukan kategori usia (AgeGroup)
     */
    protected function determineAgeGroup(?int $age): ?int
    {
        if ($age === null) {
            return $this->defaultAgeGroupId;
        }

        foreach ($this->ageGroups as $ageGroup) {
            $min = $ageGroup->min_age;
            $max = $ageGroup->max_age;

            if ($min !== null && $max !== null && $age >= $min && $age <= $max) {
                return $ageGroup->id;
            } elseif ($min !== null && $max === null && $age >= $min) {
                return $ageGroup->id;
            } elseif ($min === null && $max !== null && $age <= $max) {
                return $ageGroup->id;
            }
        }

        return $this->defaultAgeGroupId;
    }

    /**
     * Ekstrak nilai field dari baris dengan mencoba beberapa variasi nama kolom
     */
    protected function extractField(Collection $row, array $candidateKeys): mixed
    {
        foreach ($candidateKeys as $key) {
            if ($row->has($key) && ! is_null($row->get($key))) {
                return $row->get($key);
            }
        }

        return null;
    }
}
