<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Group;
use App\Models\AgeGroup;
use App\Models\Level;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateMembersFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'members:update-csv 
                            {--dry-run : Preview changes without saving}
                            {--file= : Path to CSV file (default: docs/.hide/Master-Data-Member-19022026.csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update member data from CSV file based on No KTA (member_code). Updates existing members or creates new ones.';

    protected int $totalProcessed = 0;
    protected int $updatedCount = 0;
    protected int $createdCount = 0;
    protected int $failedCount = 0;
    protected int $unchangedCount = 0;
    
    protected array $failedRows = [];
    protected array $groupCache = [];
    protected array $ageGroupCache = [];
    protected array $levelCache = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Member Update from CSV...');
        $this->newLine();

        $filePath = $this->option('file') ?? base_path('docs/.hide/Master-Data-Member-19022026.csv');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return Command::FAILURE;
        }

        // Load caches
        $this->loadCaches();

        // Process CSV
        $this->info('📄 Processing CSV file...');
        $this->processCsv($filePath, $dryRun);

        // Show report
        $this->newLine();
        $this->showReport();

        if ($this->failedCount > 0) {
            $this->newLine();
            $this->warn('⚠️  Failed rows details:');
            foreach (array_slice($this->failedRows, 0, 10) as $failed) {
                $this->error("  Row {$failed['row']}: {$failed['reason']}");
            }
            if (count($this->failedRows) > 10) {
                $this->warn('  ... and ' . (count($this->failedRows) - 10) . ' more. Check logs for details.');
            }
        }

        return Command::SUCCESS;
    }

    protected function loadCaches(): void
    {
        $this->info('📦 Loading caches...');
        
        // Load levels
        Level::all()->each(function ($level) {
            $this->levelCache[$level->level_number] = $level->id;
        });
        $this->info("   ✓ Loaded " . count($this->levelCache) . " levels");

        // Load groups
        Group::all()->each(function ($group) {
            $key = strtoupper($group->name);
            $this->groupCache[$key] = $group;
        });
        $this->info("   ✓ Loaded " . count($this->groupCache) . " groups");

        // Load age groups
        AgeGroup::all()->each(function ($ageGroup) {
            $this->ageGroupCache[] = $ageGroup;
        });
        $this->info("   ✓ Loaded " . count($this->ageGroupCache) . " age groups");
        
        $this->newLine();
    }

    protected function processCsv(string $filePath, bool $dryRun): void
    {
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            $this->error("❌ Cannot open file: {$filePath}");
            return;
        }

        // Skip header row
        $header = fgetcsv($handle, 1000, ';');
        
        // Count actual data rows first (skip empty rows)
        $totalRows = 0;
        $filePos = ftell($handle);
        while (($row = fgetcsv($handle, 1000, ';')) !== false) {
            if (!empty($row[1]) || !empty($row[2])) {
                $totalRows++;
            }
        }
        rewind($handle);
        fgetcsv($handle, 1000, ';'); // Skip header again
        
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();
        $this->newLine(2);

        while (($row = fgetcsv($handle, 1000, ';')) !== false) {
            // Skip empty rows
            if (empty($row[1]) && empty($row[2])) {
                continue;
            }

            $this->totalProcessed++;
            $bar->advance();

            // Parse row
            // Format: No;No KTA;Nama;Desa;Kelompok;Jenis Kelamin;Tanggal Lahir
            if (count($row) < 6) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => 'Invalid row format (less than 6 columns)'
                ];
                $this->failedCount++;
                continue;
            }

            $noKta = trim($row[1]);
            $nama = trim($row[2]);
            $desa = trim($row[3]);
            $kelompok = trim($row[4]);
            $jenisKelamin = trim($row[5]);
            $tanggalLahir = isset($row[6]) ? trim($row[6]) : null;

            // Validate required fields
            if (empty($noKta)) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => 'No KTA is empty'
                ];
                $this->failedCount++;
                continue;
            }

            if (empty($nama)) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => 'Nama is empty'
                ];
                $this->failedCount++;
                continue;
            }

            // Find group
            $groupId = $this->findGroup($desa, $kelompok);
            if (!$groupId) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => "Group not found: {$desa} - {$kelompok}"
                ];
                $this->failedCount++;
                continue;
            }

            // Parse gender
            $gender = $this->parseGender($jenisKelamin);
            if (!$gender) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => "Invalid gender: {$jenisKelamin}"
                ];
                $this->failedCount++;
                continue;
            }

            // Parse birth date and calculate age
            $birthDate = null;
            $age = null;
            $ageGroupId = null;

            if (!empty($tanggalLahir)) {
                try {
                    $birthDate = $this->parseDate($tanggalLahir);
                    $age = $this->calculateAge($birthDate);
                    $ageGroupId = $this->findAgeGroup($age);
                } catch (\Exception $e) {
                    $this->failedRows[] = [
                        'row' => $this->totalProcessed + 1,
                        'reason' => 'Invalid birth date format: ' . $tanggalLahir
                    ];
                    $this->failedCount++;
                    continue;
                }
            }

            // Find existing member or create new
            $member = Member::where('member_code', $noKta)->first();
            $isNew = !$member;

            if ($isNew) {
                $member = new Member();
                $member->member_code = $noKta;
            }

            // Prepare update data
            $updateData = [
                'full_name' => strtoupper($nama),
                'group_id' => $groupId,
                'birth_date' => $birthDate,
                'age' => $age,
                'age_group_id' => $ageGroupId,
                'gender' => $gender,
                'status' => true, // Active by default
            ];

            // Check if data has changed
            $hasChanges = false;
            $changes = [];

            if ($isNew) {
                $hasChanges = true;
            } else {
                foreach ($updateData as $key => $value) {
                    $originalValue = $member->getOriginal($key);
                    if ($this->valuesDiffer($originalValue, $value)) {
                        $hasChanges = true;
                        $changes[$key] = [
                            'old' => $originalValue,
                            'new' => $value
                        ];
                    }
                }
            }

            if (!$hasChanges) {
                $this->unchangedCount++;
                continue;
            }

            // Apply changes
            foreach ($updateData as $key => $value) {
                $member->$key = $value;
            }

            // Save or skip (dry run)
            if ($dryRun) {
                if ($isNew) {
                    $this->createdCount++;
                } else {
                    $this->updatedCount++;
                }
                
                // Log changes for dry run
                if ($this->output->isVerbose()) {
                    $this->info("  [DRY RUN] " . ($isNew ? 'CREATE' : 'UPDATE') . " member: {$nama} (No KTA: {$noKta})");
                    if (!$isNew && !empty($changes)) {
                        foreach ($changes as $field => $change) {
                            $this->info("    {$field}: {$change['old']} → {$change['new']}");
                        }
                    }
                }
            } else {
                try {
                    $member->save();
                    if ($isNew) {
                        $this->createdCount++;
                    } else {
                        $this->updatedCount++;
                    }
                } catch (\Exception $e) {
                    $this->failedRows[] = [
                        'row' => $this->totalProcessed + 1,
                        'reason' => $e->getMessage()
                    ];
                    $this->failedCount++;
                }
            }
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);
    }

    protected function findGroup(string $desa, string $kelompok): ?int
    {
        $desaName = strtoupper($desa);
        $kelompokName = strtoupper($kelompok);

        // First, try to find the banjar group directly
        $banjarKey = $kelompokName;
        if (isset($this->groupCache[$banjarKey])) {
            $group = $this->groupCache[$banjarKey];
            // Verify it has the correct parent (desa)
            if ($group->parent) {
                $parentName = strtoupper($group->parent->name);
                if ($parentName === $desaName || str_contains($parentName, $desaName)) {
                    return $group->id;
                }
            }
        }

        // Try to find by combination
        $banjar = Group::whereRaw('UPPER(name) = ?', [$kelompokName])
            ->whereHas('parent', function ($query) use ($desaName) {
                $query->whereRaw('UPPER(name) LIKE ?', ["%{$desaName}%"]);
            })
            ->first();

        if ($banjar) {
            return $banjar->id;
        }

        // Fallback: find desa first, then look for banjar under it
        $desaGroup = Group::whereRaw('UPPER(name) = ?', [$desaName])->first();
        
        if ($desaGroup) {
            $banjar = Group::whereRaw('UPPER(name) = ?', [$kelompokName])
                ->where('parent_id', $desaGroup->id)
                ->first();
            
            if ($banjar) {
                return $banjar->id;
            }

            // Try partial match for banjar
            $banjar = Group::whereRaw('UPPER(name) LIKE ?', ["%{$kelompokName}%"])
                ->where('parent_id', $desaGroup->id)
                ->first();
            
            if ($banjar) {
                return $banjar->id;
            }
        }

        return null;
    }

    protected function parseGender(string $jenisKelamin): ?string
    {
        $jenisKelamin = strtoupper(trim($jenisKelamin));
        
        if (in_array($jenisKelamin, ['LAKI-LAKI', 'L', 'MALE'])) {
            return 'male';
        }
        
        if (in_array($jenisKelamin, ['PEREMPUAN', 'P', 'FEMALE'])) {
            return 'female';
        }

        return null;
    }

    protected function parseDate(string $date): string
    {
        // Handle DD/MM/YYYY or MM/DD/YYYY format
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $matches)) {
            $first = (int)$matches[1];
            $second = (int)$matches[2];
            $year = $matches[3];
            
            // Determine format: if first > 12, it must be DD/MM/YYYY
            // if second > 12, it must be MM/DD/YYYY
            // if both <= 12, assume DD/MM/YYYY (Indonesian format)
            if ($first > 12) {
                // DD/MM/YYYY
                $day = $first;
                $month = $second;
            } elseif ($second > 12) {
                // MM/DD/YYYY
                $month = $first;
                $day = $second;
            } else {
                // Ambiguous, assume DD/MM/YYYY (Indonesian format)
                $day = $first;
                $month = $second;
            }
            
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Try other formats
        $formats = ['Y-m-d', 'd-m-Y', 'Y/m/d', 'm/d/Y'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return $parsed->format('Y-m-d');
            }
        }

        throw new \Exception("Invalid date format: {$date}");
    }

    protected function calculateAge(string $birthDate): int
    {
        $birth = new \DateTime($birthDate);
        $today = new \DateTime();
        $age = $today->diff($birth);
        return $age->y;
    }

    protected function findAgeGroup(int $age): ?int
    {
        foreach ($this->ageGroupCache as $ageGroup) {
            if ($age >= $ageGroup->min_age && ($age <= $ageGroup->max_age || is_null($ageGroup->max_age))) {
                return $ageGroup->id;
            }
        }
        return null;
    }

    protected function valuesDiffer($original, $new): bool
    {
        if ($original === null && $new === null) {
            return false;
        }

        if ($original instanceof \DateTime && $new instanceof \DateTime) {
            return $original->format('Y-m-d') !== $new->format('Y-m-d');
        }

        if ($original instanceof \DateTime) {
            return $original->format('Y-m-d') !== $new;
        }

        return (string)$original !== (string)$new;
    }

    protected function showReport(): void
    {
        $this->info('📊 Import Report:');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $this->totalProcessed],
                ['Updated', $this->updatedCount],
                ['Created', $this->createdCount],
                ['Unchanged', $this->unchangedCount],
                ['Failed', $this->failedCount],
            ]
        );

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('⚠️  This was a dry run. Run without --dry-run to save changes.');
        }
    }
}
