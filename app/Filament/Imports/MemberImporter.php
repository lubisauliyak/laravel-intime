<?php

namespace App\Filament\Imports;

use App\Models\Member;
use App\Models\Group;
use App\Models\AgeGroup;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MemberImporter implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, SkipsEmptyRows, SkipsOnError, WithEvents
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
        $today = new \DateTime();
        $age = $today->diff($birth);
        return $age->y;
    }

    /**
     * Determine age group based on age
     * If age is null, return "Pra Nikah" category
     */
    protected function determineAgeGroup(?int $age): ?int
    {
        // If age is null, find "Pra Nikah" category
        if ($age === null) {
            $praNikah = AgeGroup::whereRaw('LOWER(name) LIKE ?', ['%pra nikah%'])->first();
            if ($praNikah) {
                return $praNikah->id;
            }
            
            // Fallback: find category with no age limits
            $fallback = AgeGroup::whereNull('min_age')->whereNull('max_age')->first();
            return $fallback?->id;
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
        if (empty($row['full_name']) || empty($row['group_name'])) {
            $this->failedRows[] = [
                'row' => ($this->importedCount + 2),
                'reason' => 'Nama lengkap atau nama grup kosong'
            ];
            return null;
        }

        $groupId = $this->findGroup($row['group_name']);

        if (!$groupId) {
            $this->failedRows[] = [
                'row' => ($this->importedCount + 2),
                'reason' => "Grup '{$row['group_name']}' tidak ditemukan"
            ];
            return null;
        }

        // Check if member_code exists (for update)
        $memberCode = !empty($row['member_code']) ? trim($row['member_code']) : null;
        $member = null;
        $isUpdate = false;

        if ($memberCode) {
            $member = Member::where('member_code', $memberCode)->first();
            if ($member) {
                $isUpdate = true;
            }
        }

        // Calculate age if birth_date provided
        $age = null;
        $ageGroupId = null;
        $birthDate = null;

        if (!empty($row['birth_date'])) {
            try {
                $birthDate = $this->parseDate($row['birth_date']);
                $age = $this->calculateAge($birthDate);
                $ageGroupId = $this->determineAgeGroup($age);
            } catch (\Exception $e) {
                $this->failedRows[] = [
                    'row' => ($this->importedCount + 2),
                    'reason' => 'Format tanggal lahir tidak valid'
                ];
                return null;
            }
        } else {
            // If birth_date is empty, assign to "Pra Nikah" category
            $ageGroupId = $this->determineAgeGroup(null);
        }

        // Normalize gender and membership_type
        $gender = !empty($row['gender']) ? strtolower(trim($row['gender'])) : 'male';
        $membershipType = !empty($row['membership_type']) ? strtolower(trim($row['membership_type'])) : 'anggota';

        // Map data
        $memberData = [
            'full_name' => strtoupper(trim($row['full_name'])),
            'nick_name' => !empty($row['nick_name']) ? strtoupper(trim($row['nick_name'])) : null,
            'group_id' => $groupId,
            'birth_date' => $birthDate,
            'age' => $age,
            'age_group_id' => $ageGroupId,
            'gender' => $gender,
            'status' => !empty($row['status']) ? strtolower(trim($row['status'])) : 'active',
            'membership_type' => $membershipType,
        ];

        if ($isUpdate) {
            // Update existing member
            $member->update($memberData);
            $this->importedCount++;
            return null;
        }

        // Generate new member code and create new member
        $memberData['member_code'] = $this->generateMemberCode();
        
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
            'full_name' => 'required|string|max:255',
            'group_name' => 'required|string|max:255',
            'gender' => 'nullable|in:male,female',
            'status' => 'nullable|in:active,inactive',
            'membership_type' => 'nullable|in:anggota,pengurus',
            'birth_date' => 'nullable|date',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi',
            'group_name.required' => 'Nama grup wajib diisi',
            'gender.in' => 'Gender harus salah satu dari: male, female',
            'status.in' => 'Status harus salah satu dari: active, inactive',
            'membership_type.in' => 'Tipe keanggotaan harus salah satu dari: anggota, pengurus',
            'birth_date.date' => 'Format tanggal lahir tidak valid (gunakan DD/MM/YYYY)',
        ];
    }

    public function skipOnError($exception): void
    {
        $this->failedRows[] = [
            'row' => ($this->importedCount + 2),
            'reason' => $exception->getMessage()
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
            'failed_details' => $this->failedRows
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
