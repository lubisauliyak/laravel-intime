<?php

namespace App\Filament\Imports;

use App\Models\Group;
use App\Models\Level;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class GroupImporter implements SkipsEmptyRows, SkipsOnError, ToModel, WithBatchInserts, WithEvents, WithHeadingRow, WithMultipleSheets, WithValidation
{
    protected $importedCount = 0;

    protected $failedRows = [];

    protected array $levelCache = [];

    protected array $groupCache = [];

    public function __construct()
    {
        $this->loadCaches();
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    protected function loadCaches(): void
    {
        $this->levelCache = Cache::remember('import_level_cache', 3600, function () {
            return Level::all()->pluck('id', 'name')->toArray();
        });

        $this->groupCache = Cache::remember('import_group_cache', 3600, function () {
            return Group::all()->pluck('id', 'name')->toArray();
        });
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

    public function model(array $row): ?Group
    {
        if (empty($row['nama_grup_wajib']) || empty($row['tingkat_grup_wajib'])) {
            $this->failedRows[] = [
                'row' => ($this->importedCount + 2),
                'reason' => 'Nama grup atau tingkat grup kosong',
            ];

            return null;
        }

        $levelId = $this->findLevel($row['tingkat_grup_wajib']);

        if (! $levelId) {
            $this->failedRows[] = [
                'row' => ($this->importedCount + 2),
                'reason' => "Tingkat Grup '{$row['tingkat_grup_wajib']}' tidak ditemukan",
            ];

            return null;
        }

        $parentId = null;
        if (! empty($row['induk_grup'])) {
            $parentId = $this->findGroup($row['induk_grup']);
            if (! $parentId) {
                $this->failedRows[] = [
                    'row' => ($this->importedCount + 2),
                    'reason' => "Induk Grup '{$row['induk_grup']}' tidak ditemukan",
                ];

                return null;
            }
        }

        $groupName = strtoupper(trim($row['nama_grup_wajib']));

        // Ekstraksi kode grup jika ada
        $codeRaw = $row['kode_grup_opsional'] ?? $row['kode_grup'] ?? $row['kode'] ?? null;
        $groupCode = filled($codeRaw) ? strtoupper(trim((string) $codeRaw)) : null;

        // Check if group already exists to prevent duplicate
        $existingGroup = Group::where('name', $groupName)
            ->where('level_id', $levelId)
            ->where('parent_id', $parentId)
            ->first();

        if ($existingGroup) {
            // Jika grup sudah ada dan kode baru diisi, perbarui kode jika sebelumnya null
            if ($groupCode && empty($existingGroup->code)) {
                $existingGroup->update(['code' => $groupCode]);
            }
            $this->groupCache[$groupName] = $existingGroup->id;

            return null;
        }

        $groupData = [
            'name' => $groupName,
            'code' => $groupCode,
            'level_id' => $levelId,
            'parent_id' => $parentId,
            'status' => true,
        ];

        $group = new Group($groupData);
        $group->save();

        // Update cache so subsequent rows (children) can find this new group
        $this->groupCache[$groupName] = $group->id;

        $this->importedCount++;

        return null; // Return null as we saved it manually
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function rules(): array
    {
        return [
            'tingkat_grup_wajib' => 'required|string|max:255',
            'nama_grup_wajib' => 'required|string|max:255',
            'induk_grup' => 'nullable|string|max:255',
            'kode_grup_opsional' => 'nullable|max:10',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'tingkat_grup_wajib.required' => 'Tingkat grup wajib diisi',
            'nama_grup_wajib.required' => 'Nama grup wajib diisi',
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
        Cache::forget('import_group_cache');
        Cache::forget('import_level_cache');
        Cache::forget('import_group_cache_validator');
        Cache::forget('import_level_cache_validator');

        \Log::info('Group import completed', [
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
