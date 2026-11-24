<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Level;
use App\Models\Member;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportMemberReport extends Command
{
    protected $signature = 'members:export-csv
                            {--output= : Output file path (default: docs/.datas/Laporan-Member.csv)}
                            {--kta-file= : Path to Master KTA CSV (default: docs/.datas/Master-Data-Member-19022026.csv)}';

    protected $description = 'Export member report CSV with attendance stats, pengurus status, and KTA print status.';

    public function handle()
    {
        $this->info('🚀 Generating Member Report CSV...');
        $this->newLine();

        $outputPath = $this->option('output') ?? base_path('docs/.datas/Laporan-Member.csv');
        $ktaFilePath = $this->option('kta-file') ?? base_path('docs/.datas/Master-Data-Member-19022026.csv');

        // 1. Load KTA print list from Master Data CSV
        $ktaPrintedCodes = $this->loadKtaPrintedCodes($ktaFilePath);
        $this->info('   ✓ Loaded '.count($ktaPrintedCodes).' printed KTA codes from Master Data CSV');

        // 2. Find the "Desa" level number
        $desaLevel = Level::whereRaw('UPPER(name) LIKE ?', ['%DESA%'])->first();
        $desaLevelNumber = $desaLevel?->level_number;

        if (! $desaLevelNumber) {
            $this->warn('⚠️  Level "Desa" not found, will try to use parent group name instead.');
        } else {
            $this->info("   ✓ Desa level found: level_number = {$desaLevelNumber}");
        }

        // 3. Load all members with relationships
        $this->info('📦 Loading members...');
        $members = Member::with(['group.parent', 'group.level', 'ageGroup', 'positions'])
            ->where('status', true)
            ->orderBy('group_id')
            ->orderBy('full_name')
            ->get();

        $this->info("   ✓ Found {$members->count()} active members");

        // 4. Pre-load attendance stats per member (batch query for performance)
        $this->info('📊 Calculating attendance stats...');

        $attendanceStats = DB::table('attendances')
            ->select(
                'member_id',
                DB::raw('COUNT(*) as total_meetings'),
                DB::raw("SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir_count"),
                DB::raw("SUM(CASE WHEN status IN ('izin', 'sakit') THEN 1 ELSE 0 END) as izin_sakit_count")
            )
            ->whereNull('deleted_at')
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        $this->info("   ✓ Loaded attendance stats for {$attendanceStats->count()} members");

        // 5. Generate CSV
        $this->info('📝 Writing CSV...');
        $this->newLine();

        $handle = fopen($outputPath, 'w');

        if ($handle === false) {
            $this->error("❌ Cannot open output file: {$outputPath}");

            return self::FAILURE;
        }

        // BOM for UTF-8 Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Header
        fputcsv($handle, [
            'No',
            'Desa',
            'Kelompok',
            'No KTA',
            'Nama Lengkap',
            'L/P',
            'Kategori Usia',
            'Tanggal Lahir',
            'Pertemuan',
            'Hadir',
            'Izin/Sakit',
            'Pengurus',
            'Punya KTA',
        ], ';');

        $bar = $this->output->createProgressBar($members->count());
        $bar->start();

        $no = 0;
        foreach ($members as $member) {
            $no++;
            $bar->advance();

            // Desa: find parent group at "Desa" level
            $desa = '';
            if ($desaLevelNumber) {
                $desaGroup = $member->group?->getParentAtLevel($desaLevelNumber);
                $desa = $desaGroup?->name ?? '';
            } else {
                // Fallback: use parent group name
                $desa = $member->group?->parent?->name ?? '';
            }

            // Kelompok
            $kelompok = $member->group?->name ?? '';

            // L/P
            $lp = $member->gender?->getCode() ?? '';

            // Kategori Usia
            $kategoriUsia = $member->ageGroup?->name ?? '';

            // Tanggal Lahir (format DD/MM/YYYY)
            $tanggalLahir = '';
            if ($member->birth_date) {
                try {
                    $tanggalLahir = \Carbon\Carbon::parse($member->birth_date)->format('d/m/Y');
                } catch (\Exception $e) {
                    $tanggalLahir = '';
                }
            }

            // Attendance stats
            $stats = $attendanceStats->get($member->id);
            $pertemuan = $stats?->total_meetings ?? 0;
            $hadir = $stats?->hadir_count ?? 0;
            $izinSakit = $stats?->izin_sakit_count ?? 0;

            // Pengurus
            $pengurus = $member->isPengurus() ? '✓' : '';

            // Punya KTA (cross-reference with Master Data CSV)
            $punyaKta = in_array((string) $member->member_code, $ktaPrintedCodes) ? '✓' : '';

            fputcsv($handle, [
                $no,
                $desa,
                $kelompok,
                $member->member_code,
                $member->full_name,
                $lp,
                $kategoriUsia,
                $tanggalLahir,
                $pertemuan,
                $hadir,
                $izinSakit,
                $pengurus,
                $punyaKta,
            ], ';');
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->info("✅ CSV exported successfully: {$outputPath}");
        $this->info("   Total members: {$no}");

        return self::SUCCESS;
    }

    /**
     * Load the list of member_codes that have printed KTA from the Master Data CSV.
     */
    protected function loadKtaPrintedCodes(string $filePath): array
    {
        $codes = [];

        if (! file_exists($filePath)) {
            $this->warn("⚠️  KTA Master Data file not found: {$filePath}");

            return $codes;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->warn("⚠️  Cannot open KTA Master Data file: {$filePath}");

            return $codes;
        }

        // Skip header: No;No KTA;Nama;Desa;Kelompok;Jenis Kelamin;Tanggal Lahir
        fgetcsv($handle, 1000, ';');

        while (($row = fgetcsv($handle, 1000, ';')) !== false) {
            if (! empty($row[1])) {
                $codes[] = trim($row[1]);
            }
        }

        fclose($handle);

        return $codes;
    }
}
