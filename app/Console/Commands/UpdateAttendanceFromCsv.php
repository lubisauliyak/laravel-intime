<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Meeting;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateAttendanceFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:update-csv 
                            {--dry-run : Preview changes without saving}
                            {--file= : Path to CSV file}
                            {--meeting= : Meeting ID to update attendance for}
                            {--date= : Meeting date (YYYY-MM-DD) if not using meeting ID}
                            {--status= : Attendance status (hadir, izin, sakit). Default: ' . AttendanceStatus::HADIR->value . '}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update attendance data from CSV file based on member No KTA. Creates or updates attendance records.';

    protected int $totalProcessed = 0;
    protected int $createdCount = 0;
    protected int $updatedCount = 0;
    protected int $failedCount = 0;
    protected int $unchangedCount = 0;
    
    protected array $failedRows = [];
    protected ?Meeting $meeting = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Attendance Update from CSV...');
        $this->newLine();

        $filePath = $this->option('file');
        $meetingId = $this->option('meeting');
        $date = $this->option('date');
        $status = $this->option('status') ?? AttendanceStatus::HADIR->value;
        $dryRun = $this->option('dry-run');

        // Validate status
        if (!in_array($status, [AttendanceStatus::HADIR->value, AttendanceStatus::IZIN->value, AttendanceStatus::SAKIT->value])) {
            $this->error("❌ Invalid status: {$status}. Must be: " . AttendanceStatus::HADIR->value . ", " . AttendanceStatus::IZIN->value . ", or " . AttendanceStatus::SAKIT->value);
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // Determine file path
        if (!$filePath) {
            // Try to find latest Presensi or absensi-ijin CSV in docs/.hide
            $files = array_merge(
                glob(base_path('docs/.hide/Presensi-*.csv')) ?: [],
                glob(base_path('docs/.hide/absensi-ijin-*.csv')) ?: []
            );
            if (empty($files)) {
                $this->error("❌ No CSV file found. Please specify with --file option");
                return self::FAILURE;
            }
            // Get the most recent file (by name/date in filename)
            rsort($files);
            $filePath = $files[0];
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("📄 Using file: {$filePath}");
        $this->info("📝 Status: {$status}");

        // Find meeting
        $this->meeting = $this->findMeeting($meetingId, $date, $filePath);
        if (!$this->meeting) {
            $this->error("❌ Meeting not found. Please specify with --meeting or --date option");
            return self::FAILURE;
        }

        $this->info("📅 Meeting: {$this->meeting->name} ({$this->meeting->meeting_date->format('d/m/Y')})");
        $this->newLine();

        // Process CSV
        $this->info('📊 Processing CSV file...');
        $this->processCsv($filePath, $dryRun, $status);

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

        return self::SUCCESS;
    }

    protected function findMeeting(?string $meetingId, ?string $date, string $filePath): ?Meeting
    {
        // If meeting ID is provided, use it
        if ($meetingId) {
            return Meeting::find($meetingId);
        }

        // Try to extract date from filename (Presensi-DDMMYYYY.csv)
        if (preg_match('/Presensi-(\d{2})(\d{2})(\d{4})\.csv$/', basename($filePath), $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $date = "{$year}-{$month}-{$day}";
        }

        // If date is provided or extracted, find meeting by date
        if ($date) {
            $meetings = Meeting::whereDate('meeting_date', $date)->get();
            
            if ($meetings->count() === 0) {
                $this->error("No meeting found for date: {$date}");
                return null;
            }
            
            if ($meetings->count() === 1) {
                return $meetings->first();
            }
            
            // Multiple meetings on same date
            $this->warn("Multiple meetings found on {$date}:");
            foreach ($meetings as $m) {
                $this->info("  ID: {$m->id} - {$m->name} (Group: {$m->group->name})");
            }
            $this->info('Please specify meeting with --meeting=ID option');
            return null;
        }

        return null;
    }

    protected function processCsv(string $filePath, bool $dryRun, string $status): void
    {
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            $this->error("❌ Cannot open file: {$filePath}");
            return;
        }

        // Skip header row
        $header = fgetcsv($handle, 1000, ';');
        
        // Count actual data rows first
        $totalRows = 0;
        while (($row = fgetcsv($handle, 1000, ';')) !== false) {
            if (!empty($row[0]) && !empty($row[1])) {
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
            if (empty($row[0]) || empty($row[1])) {
                continue;
            }

            $this->totalProcessed++;
            $bar->advance();

            $memberCode = trim($row[0]);
            $checkInTime = trim($row[1]);

            // Validate required fields
            if (empty($memberCode)) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => 'Member ID (No KTA) is empty'
                ];
                $this->failedCount++;
                continue;
            }

            if (empty($checkInTime)) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => 'Check-in time is empty'
                ];
                $this->failedCount++;
                continue;
            }

            // Find member by member_code (No KTA)
            $member = Member::where('member_code', $memberCode)->first();

            if (!$member) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => "Member not found: {$memberCode}"
                ];
                $this->failedCount++;
                continue;
            }

            // Parse check-in time
            try {
                $checkInDateTime = $this->parseCheckInTime($checkInTime, $this->meeting->meeting_date);
            } catch (\Exception $e) {
                $this->failedRows[] = [
                    'row' => $this->totalProcessed + 1,
                    'reason' => 'Invalid time format: ' . $checkInTime
                ];
                $this->failedCount++;
                continue;
            }

            // Find existing attendance or create new
            $attendance = Attendance::where('meeting_id', $this->meeting->id)
                ->where('member_id', $member->id)
                ->first();
            
            $isNew = !$attendance;
            $hasChanges = false;
            $changes = [];

            if ($isNew) {
                $attendance = new Attendance();
                $attendance->meeting_id = $this->meeting->id;
                $attendance->member_id = $member->id;
                $attendance->checkin_time = $checkInDateTime;
                $attendance->method = AttendanceMethod::MANUAL;
                $attendance->status = AttendanceStatus::from($status);
                $hasChanges = true;
            } else {
                // Check if data has changed
                $originalTime = $attendance->checkin_time?->format('H:i:s');
                $originalStatus = $attendance->status instanceof \BackingEnum ? $attendance->status->value : $attendance->status;

                if ($originalTime !== $checkInTime || $originalStatus !== $status) {
                    $hasChanges = true;
                    $changes = [
                        'checkin_time' => ['old' => $originalTime, 'new' => $checkInTime],
                        'status' => ['old' => $originalStatus, 'new' => $status]
                    ];
                }
                
                $attendance->checkin_time = $checkInDateTime;
                $attendance->method = AttendanceMethod::MANUAL;
                $attendance->status = AttendanceStatus::from($status);
            }

            if (!$hasChanges) {
                $this->unchangedCount++;
                continue;
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
                    $action = $isNew ? 'CREATE' : 'UPDATE';
                    $this->info("  [DRY RUN] {$action} attendance: {$member->full_name} (No KTA: {$memberCode}) @ {$checkInTime} [{$status}]");
                    if (!$isNew && !empty($changes) && $this->output->isVeryVerbose()) {
                        foreach ($changes as $field => $change) {
                            $this->info("    {$field}: {$change['old']} → {$change['new']}");
                        }
                    }
                }
            } else {
                try {
                    $attendance->save();
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

    protected function parseCheckInTime(string $time, $meetingDate): \Illuminate\Support\Carbon
    {
        // Parse time format HH:MM:SS or HH:MM
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $matches)) {
            $hour = (int)$matches[1];
            $minute = (int)$matches[2];
            $second = isset($matches[3]) ? (int)$matches[3] : 0;
            
            // Validate time
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
                throw new \Exception("Invalid time: {$time}");
            }
            
            // Create datetime with meeting date and check-in time
            $dateTime = \Illuminate\Support\Carbon::instance($meetingDate);
            $dateTime->setTime($hour, $minute, $second);
            
            return $dateTime;
        }

        throw new \Exception("Invalid time format: {$time}. Expected HH:MM:SS");
    }

    protected function showReport(): void
    {
        $this->info('📊 Import Report:');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $this->totalProcessed],
                ['Created', $this->createdCount],
                ['Updated', $this->updatedCount],
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
