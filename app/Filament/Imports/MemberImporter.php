<?php

namespace App\Filament\Imports;

use App\Enums\Gender;
use App\Enums\MembershipType;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class MemberImporter implements SkipsEmptyRows, SkipsOnError, ToModel, WithBatchInserts, WithEvents, WithHeadingRow, WithMultipleSheets, WithValidation
{
    protected $importedCount = 0;

    protected $failedRows = [];

    /**
     * Group cache for quick lookup (name => id)
     */
    protected array $groupCache = [];

    /**
     * Age group cache for quick lookup
     */
    protected array $ageGroupCache = [];

    public function __construct()
    {
        $this->loadGroupCache();
        $this->loadAgeGroupCache();
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    protected function loadGroupCache(): void
    {
        $cacheKey = 'import_group_cache';

        $this->groupCache = Cache::remember($cacheKey, 3600, function () {
            return Group::all()->pluck('id', 'name')->toArray();
        });
    }

    protected function loadAgeGroupCache(): void
    {
        $this->ageGroupCache = AgeGroup::all()->pluck('id', 'name')->toArray();
    }

    /**
     * Find group by name (case-insensitive)
     */
    protected function findGroup(string $groupName): ?int
    {
        $groupName = strtoupper(trim($groupName));

        // Check cache first
        if (isset($this->groupCache[$groupName])) {
            return $this->groupCache[$groupName];
        }

        // Try to find in database
        $group = Group::whereRaw('UPPER(name) = ?', [$groupName])->first();

        if ($group) {
            $this->groupCache[$groupName] = $group->id;

            return $group->id;
        }

        // Try case-insensitive search as fallback
        $group = Group::whereRaw('LOWER(name) = ?', [strtolower($groupName)])->first();

        if ($group) {
            $this->groupCache[$groupName] = $group->id;

            return $group->id;
        }

        return null;
    }

    /**
     * Calculate age from birth date
     */
    protected function calculateAge(string $birthDate): int
    {
        $birth = new \DateTime($birthDate);
        $today = new \DateTime;
        $age = $today->diff($birth);

        return $age->y;
    }

    /**
     * Determine age group based on age
     * If age is null, return default category by sort_order
     */
    protected function determineAgeGroup(?int $age): ?int
    {
        // If age is null, find default category (unbound age limit or lowest sort_order)
        if ($age === null) {
            $defaultGroup = AgeGroup::whereNull('min_age')->whereNull('max_age')->orderBy('sort_order')->first()
                ?? AgeGroup::orderBy('sort_order')->first();

            return $defaultGroup?->id;
        }

        // Check cache first
        foreach ($this->ageGroupCache as $name => $id) {
            $ageGroup = AgeGroup::find($id);
            if ($ageGroup && $age >= $ageGroup->min_age && $age <= $ageGroup->max_age) {
                return $id;
            }
        }

        // Fallback: query database
        $ageGroup = AgeGroup::where('min_age', '<=', $age)
            ->where('max_age', '>=', $age)
            ->first();

        return $ageGroup?->id;
    }

    /**
     * Generate unique member code
     */
    protected function generateMemberCode(): string
    {
        $prefix = 'M';
        $timestamp = date('YmdHis');
        $random = strtoupper(Str::random(4));

        return "{$prefix}{$timestamp}{$random}";
    }

    public function model(array $row): ?Member
    {
        // Skip if required fields are missing
        if (empty($row['nama_lengkap_wajib']) || empty($row['kelompok_wajib']) || empty($row['lp_wajib'])) {
            $this->failedRows[] = [
                'row' => ($this->importedCount + 2),
                'reason' => 'Nama lengkap, kelompok, atau jenis kelamin kosong',
            ];

            return null;
        }

        $groupId = $this->findGroup($row['kelompok_wajib']);

        if (! $groupId) {
            $this->failedRows[] = [
                'row' => ($this->importedCount + 2),
                'reason' => "Grup '{$row['kelompok_wajib']}' tidak ditemukan",
            ];

            return null;
        }

        // Check if member_code exists (for update)
        $memberCode = ! empty($row['id_anggota']) ? trim($row['id_anggota']) : null;
        $member = null;
        $isUpdate = false;

        if ($memberCode) {
            $member = Member::where('member_code', $memberCode)->first();
            if ($member) {
                $isUpdate = true;
            } else {
                $trashedMember = Member::onlyTrashed()->where('member_code', $memberCode)->first();
                if ($trashedMember) {
                    $this->failedRows[] = [
                        'row' => ($this->importedCount + 2),
                        'reason' => "ID Anggota '{$memberCode}' berada di tong sampah (Soft Deleted)",
                    ];

                    return null;
                }
            }
        }

        // Calculate age if birth_date provided
        $age = null;
        $ageGroupId = null;
        $birthDate = null;

        if (! empty($row['tanggal_lahir'])) {
            try {
                $birthDate = $this->parseDate($row['tanggal_lahir']);
                $age = $this->calculateAge($birthDate);
                $ageGroupId = $this->determineAgeGroup($age);
            } catch (\Exception $e) {
                $this->failedRows[] = [
                    'row' => ($this->importedCount + 2),
                    'reason' => 'Format tanggal lahir tidak valid',
                ];

                return null;
            }
        } else {
            // If birth_date is empty, assign to "Pra Nikah" category
            $ageGroupId = $this->determineAgeGroup(null);
        }

        // Normalize gender
        $genderInput = strtoupper(trim($row['lp_wajib']));
        $gender = ($genderInput === 'P') ? Gender::FEMALE->value : Gender::MALE->value;

        $membershipType = MembershipType::ANGGOTA->value;

        // Map data
        $memberData = [
            'full_name' => strtoupper(trim($row['nama_lengkap_wajib'])),
            'nick_name' => ! empty($row['nama_panggilan']) ? strtoupper(trim($row['nama_panggilan'])) : null,
            'group_id' => $groupId,
            'birth_date' => $birthDate,
            'age' => $age,
            'age_group_id' => $ageGroupId,
            'gender' => $gender,
            'status' => true, // Default to active since the column is removed from excel template
            'membership_type' => $membershipType,
        ];

        if ($isUpdate) {
            // Update existing member
            $member->update($memberData);
            $this->importedCount++;

            return null;
        }

        // Biarkan kosong jika tidak diisi di Excel
        $memberData['member_code'] = $memberCode;

        $member = new Member($memberData);
        $this->importedCount++;

        // Save the member (this will trigger the Observer to generate QR code automatically)
        $member->save();

        return null; // Return null because we already saved
    }

    /**
     * Parse date from various formats (DD/MM/YYYY, YYYY-MM-DD, etc.)
     */
    protected function parseDate($date): string
    {
        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d');
        }

        // Try DD/MM/YYYY format first (primary format for import)
        $parsed = \DateTime::createFromFormat('d/m/Y', $date);
        if ($parsed && $parsed->format('d/m/Y') === $date) {
            return $parsed->format('Y-m-d');
        }

        // Try other common date formats as fallback
        $formats = ['Y-m-d', 'd-m-Y', 'Y/m/d', 'm/d/Y'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        // Fallback: assume it's already in correct format
        return $date;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function rules(): array
    {
        return [
            'nama_lengkap_wajib' => 'required|string|max:255',
            'kelompok_wajib' => 'required|string|max:255',
            'lp_wajib' => 'required|in:L,P,l,p',
            'tanggal_lahir' => 'nullable',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nama_lengkap_wajib.required' => 'Nama lengkap wajib diisi',
            'kelompok_wajib.required' => 'Nama kelompok wajib diisi',
            'lp_wajib.required' => 'Jenis kelamin (L/P) wajib diisi',
            'lp_wajib.in' => 'Jenis kelamin harus diisi dengan L atau P',
        ];
    }

    public function onError(\Throwable $exception): void
    {
        $this->failedRows[] = [
            'row' => ($this->importedCount + 2),
            'reason' => $exception->getMessage(),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                $this->afterImport($event);
            },
        ];
    }

    public function afterImport(AfterImport $event): void
    {
        // Clear caches
        Cache::forget('import_group_cache');
        Cache::flush();

        // Log import results
        \Log::info('Member import completed', [
            'imported' => $this->importedCount,
            'failed_rows' => count($this->failedRows),
            'failed_details' => $this->failedRows,
        ]);
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getFailedRows(): array
    {
        return $this->failedRows;
    }
}
