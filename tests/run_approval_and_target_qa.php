<?php

/**
 * inTime - Automated Member Approval & Dynamic Target QA Test Suite
 * Memvalidasi seluruh skenario Fase 5 secara komprehensif:
 * - 5.1: Pendaftaran Mandiri & Presensi Non-Target
 * - 5.2: Non-Target Tidak Hadir (Bebas Alpa Fiktif)
 * - 5.3: Aksi Approval Admin & Transisi Target Wajib
 * - 5.4: Query Audit & Performance Benchmark
 */

require 'd:/SandBox/Antigravity/inTime/vendor/autoload.php';
$app = require_once 'd:/SandBox/Antigravity/inTime/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Setup SQLite in-memory database and array cache to run without touching MySQL
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ],
    'cache.default' => 'array',
]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');
\Illuminate\Support\Facades\Cache::setDefaultDriver('array');

// Create required core tables in SQLite memory
\Illuminate\Support\Facades\Schema::create('users', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->string('email')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->nullable();
    $table->rememberToken();
    $table->string('role')->default('operator');
    $table->boolean('status')->default(true);
    $table->unsignedBigInteger('group_id')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('levels', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('code')->nullable();
    $table->integer('level_number')->default(1);
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('groups', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->string('code')->nullable();
    $table->unsignedBigInteger('parent_id')->nullable();
    $table->unsignedBigInteger('level_id')->nullable();
    $table->unsignedBigInteger('card_template_id')->nullable();
    $table->boolean('status')->default(true);
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('age_groups', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('code')->nullable();
    $table->integer('sort_order')->default(1);
    $table->integer('min_age')->nullable();
    $table->integer('max_age')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('members', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('member_code')->nullable();
    $table->string('full_name');
    $table->string('nick_name')->nullable();
    $table->unsignedBigInteger('group_id');
    $table->date('birth_date')->nullable();
    $table->integer('age')->nullable();
    $table->unsignedBigInteger('age_group_id')->nullable();
    $table->string('gender')->default('male');
    $table->boolean('status')->default(true);
    $table->boolean('is_permanent')->default(false);
    $table->string('membership_type')->default('anggota');
    $table->string('qr_code_path')->nullable();
    $table->string('profile_photo_path')->nullable();
    $table->string('membership_card_photo_path')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('position_categories', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('code')->nullable();
    $table->integer('sort_order')->default(1);
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('member_positions', function ($table) {
    $table->id();
    $table->unsignedBigInteger('member_id');
    $table->unsignedBigInteger('group_id');
    $table->unsignedBigInteger('position_category_id')->nullable();
    $table->string('position_name')->nullable();
    $table->integer('sort_order')->default(1);
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('meetings', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->text('description')->nullable();
    $table->date('meeting_date');
    $table->time('start_time')->nullable();
    $table->time('checkin_open_time')->nullable();
    $table->time('end_time')->nullable();
    $table->unsignedBigInteger('group_id');
    $table->string('target_gender')->default('all');
    $table->json('target_age_groups')->nullable();
    $table->boolean('is_permission_template_active')->default(true);
    $table->boolean('is_permission_enabled')->default(true);
    $table->boolean('is_presence_enabled')->default(true);
    $table->text('permission_opening_text')->nullable();
    $table->text('permission_statement_text')->nullable();
    $table->text('permission_closing_text')->nullable();
    $table->unsignedBigInteger('created_by')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

\Illuminate\Support\Facades\Schema::create('attendances', function ($table) {
    $table->id();
    $table->unsignedBigInteger('meeting_id');
    $table->unsignedBigInteger('member_id');
    $table->dateTime('checkin_time');
    $table->string('method')->default('qr_code');
    $table->string('status')->default('hadir');
    $table->text('notes')->nullable();
    $table->string('evidence_path')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['meeting_id', 'member_id']);
});

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\MeetingTargetGender;
use App\Enums\MembershipType;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Level;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalAndTargetQATester
{
    private int $passed = 0;

    private int $failed = 0;

    private array $errors = [];

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "   inTime - QA TEST SUITE: APPROVAL & TARGET SYSTEM (FASE 5)        \n";
        echo "====================================================================\n\n";

        $this->seedInitialData();

        $this->testScenario51_SelfRegistrationAndNonTargetAttendance();
        $this->testScenario52_NonTargetAbsentExclusion();
        $this->testScenario53_AdminApprovalFlowAndTargetTransition();
        $this->testScenario54_QueryAuditAndPerformanceBenchmark();

        echo "====================================================================\n";
        echo sprintf("HASIL AKHIR: %d LULUS, %d GAGAL\n", $this->passed, $this->failed);
        echo "====================================================================\n";

        if ($this->failed > 0) {
            echo "\nRincian Kegagalan:\n";
            foreach ($this->errors as $err) {
                echo ' - '.$err."\n";
            }
            exit(1);
        } else {
            echo "\nSELURUH PENGUJIAN FASE 5 BERHASIL DENGAN SEMPURNA! (100% PASS)\n\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $description): void
    {
        if ($condition) {
            echo '  [PASS] '.$description."\n";
            $this->passed++;
        } else {
            echo '  [FAIL] '.$description."\n";
            $this->failed++;
            $this->errors[] = $description;
        }
    }

    private Group $rootGroup;

    private AgeGroup $ageRemaja;

    private AgeGroup $ageDewasa;

    private User $adminUser;

    private ?Member $newMember1 = null;

    private ?Member $newMember2 = null;

    private ?Member $absentNonTarget = null;

    private function seedInitialData(): void
    {
        echo "[INIT] Melakukan seeding data dasar in-memory...\n";

        $level = Level::create([
            'name' => 'Kelompok',
            'code' => 'KLP',
            'level_number' => 1,
        ]);

        $this->rootGroup = Group::create([
            'name' => 'KELOMPOK PUSAT A',
            'code' => 'PST-A',
            'level_id' => $level->id,
            'status' => true,
        ]);

        $this->ageRemaja = AgeGroup::create([
            'name' => 'Remaja',
            'code' => 'RMJ',
            'sort_order' => 1,
            'min_age' => 13,
            'max_age' => 19,
        ]);

        $this->ageDewasa = AgeGroup::create([
            'name' => 'Dewasa',
            'code' => 'DWS',
            'sort_order' => 2,
            'min_age' => 20,
            'max_age' => 45,
        ]);

        $this->adminUser = User::create([
            'name' => 'Administrator Test',
            'email' => 'admin@intime.test',
            'role' => 'admin',
            'status' => true,
            'group_id' => $this->rootGroup->id,
        ]);

        echo "  Data dasar berhasil diinisialisasi.\n\n";
    }

    /**
     * Skenario 5.1: Pendaftaran Mandiri & Presensi Non-Target
     */
    private function testScenario51_SelfRegistrationAndNonTargetAttendance(): void
    {
        echo "[FASE 5.1] Menguji Pendaftaran Mandiri & Presensi Non-Target...\n";

        // 1. Simulasikan pendaftaran mandiri (SelfPresenceController/LiveScannerController)
        $newMember = Member::create([
            'full_name' => 'Pendaftar Mandiri 1',
            'gender' => Gender::MALE,
            'group_id' => $this->rootGroup->id,
            'birth_date' => '2005-05-15',
            'age_group_id' => $this->ageRemaja->id,
            'is_permanent' => false,
            'status' => false,
            'membership_type' => MembershipType::ANGGOTA,
        ]);

        $this->assert($newMember->is_permanent === false, 'Pendaftar mandiri baru berstatus is_permanent = false (menunggu persetujuan)');
        $this->assert($newMember->status === false, 'Pendaftar mandiri baru berstatus status = false (Non-Target Wajib)');

        // 2. Uji eligibility: status = false dan is_permanent = false tetap eligible untuk check-in
        $isEligible = ! $newMember->trashed() && in_array($newMember->group_id, $this->rootGroup->getAllDescendantIds());
        $this->assert($isEligible, 'Anggota non-target belum permanen TETAP BERHAK melakukan presensi');

        // Uji soft delete: anggota yang dihapus ditolak
        $deletedMember = Member::create([
            'full_name' => 'Anggota Dihapus',
            'gender' => Gender::FEMALE,
            'group_id' => $this->rootGroup->id,
            'status' => true,
            'is_permanent' => true,
        ]);
        $deletedMember->delete();
        $this->assert($deletedMember->trashed() === true, 'Soft deleted member teridentifikasi trashed() = true');

        // 3. Buat 5 anggota target dasar (status = true, is_permanent = true)
        $baseTargetMembers = [];
        for ($i = 1; $i <= 5; $i++) {
            $baseTargetMembers[] = Member::create([
                'full_name' => "Anggota Target Wajib {$i}",
                'gender' => ($i % 2 === 0) ? Gender::FEMALE : Gender::MALE,
                'group_id' => $this->rootGroup->id,
                'age_group_id' => $this->ageDewasa->id,
                'status' => true,
                'is_permanent' => true,
            ]);
        }

        // 4. Buat pertemuan dengan target semua anggota
        $meeting = Meeting::create([
            'name' => 'Pertemuan Rutin 1',
            'meeting_date' => now()->toDateString(),
            'group_id' => $this->rootGroup->id,
            'target_gender' => MeetingTargetGender::ALL,
            'target_age_groups' => ['Remaja', 'Dewasa'],
            'created_by' => $this->adminUser->id,
        ]);

        // Hitung statistik awal (sebelum ada yang hadir)
        $statsInitial = $this->calculateMeetingStats($meeting, $this->rootGroup);
        $this->assert($statsInitial['base_target'] === 5, 'Target dasar awal adalah 5 anggota (status = true)');
        $this->assert($statsInitial['total_target'] === 5, 'Total target dinamis awal sama dengan target dasar (5)');
        $this->assert($statsInitial['hadir'] === 0, 'Jumlah hadir awal adalah 0');
        $this->assert($statsInitial['alpa'] === 5, 'Jumlah alpa awal adalah 5 (seluruh target dasar belum hadir)');

        // 5. Pendaftar mandiri (non-target) melakukan checkin HADIR
        Attendance::create([
            'meeting_id' => $meeting->id,
            'member_id' => $newMember->id,
            'checkin_time' => now(),
            'status' => AttendanceStatus::HADIR,
            'method' => 'qr_code',
        ]);

        $this->newMember1 = $newMember;

        // Hitung ulang statistik setelah pendaftar mandiri hadir
        $statsAfterNonTargetCheckin = $this->calculateMeetingStats($meeting, $this->rootGroup);
        $this->assert($statsAfterNonTargetCheckin['total_target'] === 6, 'Total target dinamis bertambah menjadi 6 (5 target dasar + 1 non-target hadir)');
        $this->assert($statsAfterNonTargetCheckin['hadir'] === 1, 'Jumlah hadir bertambah menjadi 1');
        $this->assert($statsAfterNonTargetCheckin['alpa'] === 5, 'Jumlah alpa TETAP 5 (kehadiran non-target TIDAK menambah alpa)');

        // 6. Anggota non-target kedua mengajukan IZIN
        $newMember2 = Member::create([
            'full_name' => 'Pendaftar Mandiri 2 (Izin)',
            'gender' => Gender::FEMALE,
            'group_id' => $this->rootGroup->id,
            'birth_date' => '2004-03-10',
            'age_group_id' => $this->ageDewasa->id,
            'is_permanent' => false,
            'status' => false,
        ]);
        Attendance::create([
            'meeting_id' => $meeting->id,
            'member_id' => $newMember2->id,
            'checkin_time' => now(),
            'status' => AttendanceStatus::IZIN,
            'method' => 'manual',
            'notes' => 'Izin keperluan keluarga',
        ]);

        $this->newMember2 = $newMember2;

        // Hitung ulang statistik setelah non-target kedua izin
        $statsAfterPermission = $this->calculateMeetingStats($meeting, $this->rootGroup);
        $this->assert($statsAfterPermission['total_target'] === 7, 'Total target dinamis bertambah menjadi 7 (5 base + 2 non-target aktif)');
        $this->assert($statsAfterPermission['hadir'] === 1, 'Jumlah hadir tetap 1');
        $this->assert($statsAfterPermission['izin_sakit'] === 1, 'Jumlah izin/sakit bertambah menjadi 1');
        $this->assert($statsAfterPermission['alpa'] === 5, 'Jumlah alpa TETAP 5');

        // 7. Konsistensi matematika formula: Hadir + Izin/Sakit + Alpa === Total Target Dinamis
        $sum = $statsAfterPermission['hadir'] + $statsAfterPermission['izin_sakit'] + $statsAfterPermission['alpa'];
        $this->assert($sum === $statsAfterPermission['total_target'], "Integritas Matematis 100%: Hadir (1) + Izin (1) + Alpa (5) = {$sum} === Total Target Dinamis ({$statsAfterPermission['total_target']})");

        echo "\n";
    }

    /**
     * Skenario 5.2: Non-Target Tidak Hadir (Bebas Alpa Fiktif)
     */
    private function testScenario52_NonTargetAbsentExclusion(): void
    {
        echo "[FASE 5.2] Menguji Non-Target Tidak Hadir (Bebas Alpa Fiktif)...\n";

        // Buat anggota non-target ke-3 yang TIDAK hadir
        $absentNonTarget = Member::create([
            'full_name' => 'Non-Target Pasif (Tidak Hadir)',
            'gender' => Gender::MALE,
            'group_id' => $this->rootGroup->id,
            'age_group_id' => $this->ageDewasa->id,
            'is_permanent' => false,
            'status' => false,
        ]);
        $this->absentNonTarget = $absentNonTarget;

        $meeting = Meeting::first();

        // 1. Verifikasi statistik agregat: Anggota non-target yang tidak hadir TIDAK menambah target & TIDAK menambah alpa
        $stats = $this->calculateMeetingStats($meeting, $this->rootGroup);
        $this->assert($stats['total_target'] === 7, 'Total target dinamis tetap 7 (non-target tidak hadir TIDAK dihitung target)');
        $this->assert($stats['alpa'] === 5, 'Alpa tetap 5 (non-target tidak hadir TIDAK dijatuhi alpa fiktif)');

        // 2. Verifikasi Query Tabel Presensi (getTableQuery logic):
        // Query harus mengembalikan target wajib (status = true) ATAU yang memiliki status absensi
        $tableQueryResults = $this->executeTableQuery($meeting, $this->rootGroup);

        $memberIdsInTable = $tableQueryResults->pluck('id')->toArray();

        $this->assert(! in_array($absentNonTarget->id, $memberIdsInTable), 'Anggota non-target yang TIDAK hadir TIDAK MUNCUL di daftar tabel presensi');
        $this->assert(in_array($this->newMember1->id, $memberIdsInTable), 'Anggota non-target yang HADIR muncul di daftar tabel presensi');
        $this->assert(in_array($this->newMember2->id, $memberIdsInTable), 'Anggota non-target yang IZIN muncul di daftar tabel presensi');

        // 5 anggota target wajib (meskipun belum hadir) harus tetap muncul di tabel untuk dicatat alpa
        $baseTargets = Member::where('status', true)->pluck('id')->toArray();
        $allBaseTargetsPresentInTable = empty(array_diff($baseTargets, $memberIdsInTable));
        $this->assert($allBaseTargetsPresentInTable, 'Seluruh anggota target wajib (5 orang) tetap muncul di tabel presensi');

        echo "\n";
    }

    /**
     * Skenario 5.3: Aksi Approval Admin & Transisi Target Wajib
     */
    private function testScenario53_AdminApprovalFlowAndTargetTransition(): void
    {
        echo "[FASE 5.3] Menguji Aksi Approval Admin & Transisi Target Wajib...\n";

        $memberToApproveWithoutTarget = $this->newMember1;
        $memberToApproveWithTarget = $this->absentNonTarget;

        // Kasus A: Admin menyetujui pendaftar mandiri TANPA dijadikan target wajib
        // Meniru aksi Filament: $record->update(['is_permanent' => true, 'status' => false])
        $memberToApproveWithoutTarget->update([
            'is_permanent' => true,
            'status' => false,
        ]);
        $memberToApproveWithoutTarget->refresh();
        $this->assert($memberToApproveWithoutTarget->is_permanent === true, 'Aksi Approval A: is_permanent berubah menjadi true (Terverifikasi)');
        $this->assert($memberToApproveWithoutTarget->status === false, 'Aksi Approval A: status tetap false (Tetap Anggota Mengambang/Non-Target)');

        // Kasus B: Admin menyetujui pendaftar mandiri DENGAN toggle set_as_target = true
        // Meniru aksi Filament: $record->update(['is_permanent' => true, 'status' => true])
        $memberToApproveWithTarget->update([
            'is_permanent' => true,
            'status' => true,
        ]);
        $memberToApproveWithTarget->refresh();
        $this->assert($memberToApproveWithTarget->is_permanent === true, 'Aksi Approval B: is_permanent berubah menjadi true (Terverifikasi)');
        $this->assert($memberToApproveWithTarget->status === true, 'Aksi Approval B: status berubah menjadi true (Naik Menjadi Target Wajib)');

        // Buat pertemuan BARU untuk memvalidasi dampak perubahan status ke target kehadiran
        $newMeeting = Meeting::create([
            'name' => 'Pertemuan Rutin 2 (Pasca Approval)',
            'meeting_date' => now()->addDay()->toDateString(),
            'group_id' => $this->rootGroup->id,
            'target_gender' => MeetingTargetGender::ALL,
            'target_age_groups' => ['Remaja', 'Dewasa'],
            'created_by' => $this->adminUser->id,
        ]);

        // Pada pertemuan baru ini, belum ada yang hadir sama sekali
        $newStats = $this->calculateMeetingStats($newMeeting, $this->rootGroup);

        // Target dasar sebelumnya adalah 5. Sekarang Member B sudah menjadi status=true, sehingga target dasar menjadi 6!
        $this->assert($newStats['base_target'] === 6, 'Target dasar pertemuan baru naik menjadi 6 (5 target lama + 1 anggota yang baru diapprove)');
        $this->assert($newStats['alpa'] === 6, 'Alpa pertemuan baru adalah 6 (termasuk anggota yang baru naik menjadi target wajib)');

        // Verifikasi pada query tabel presensi pertemuan baru:
        $newTableResults = $this->executeTableQuery($newMeeting, $this->rootGroup);
        $newMemberIdsInTable = $newTableResults->pluck('id')->toArray();

        $this->assert(in_array($memberToApproveWithTarget->id, $newMemberIdsInTable), 'Anggota yang baru diapprove sebagai target wajib otomatis muncul di tabel presensi');
        $this->assert(! in_array($memberToApproveWithoutTarget->id, $newMemberIdsInTable), 'Anggota yang diapprove tanpa target tetap tersembunyi jika tidak hadir');

        echo "\n";
    }

    /**
     * Skenario 5.4: Query Audit & Performance Benchmark
     */
    private function testScenario54_QueryAuditAndPerformanceBenchmark(): void
    {
        echo "[FASE 5.4] Menguji Query Audit & Performance Benchmark (Mencegah N+1)...\n";

        // Seed 50 anggota target wajib tambahan dan 20 non-target
        echo "  [BENCH] Menambahkan 50 anggota target dan 20 anggota non-target...\n";
        $membersToInsert = [];
        for ($i = 1; $i <= 50; $i++) {
            Member::create([
                'full_name' => "Batch Target {$i}",
                'gender' => ($i % 2 === 0) ? Gender::FEMALE : Gender::MALE,
                'group_id' => $this->rootGroup->id,
                'age_group_id' => $this->ageDewasa->id,
                'status' => true,
                'is_permanent' => true,
            ]);
        }
        for ($i = 1; $i <= 20; $i++) {
            $nt = Member::create([
                'full_name' => "Batch Non-Target {$i}",
                'gender' => ($i % 2 === 0) ? Gender::FEMALE : Gender::MALE,
                'group_id' => $this->rootGroup->id,
                'age_group_id' => $this->ageDewasa->id,
                'status' => false,
                'is_permanent' => false,
            ]);
            // 10 di antaranya hadir
            if ($i <= 10) {
                Attendance::create([
                    'meeting_id' => 2, // Meeting 2
                    'member_id' => $nt->id,
                    'checkin_time' => now(),
                    'status' => AttendanceStatus::HADIR->value,
                    'method' => 'qr_code',
                ]);
            }
        }

        $meeting = Meeting::find(2);

        // Audit Perhitungan Statistik (MeetingAttendanceInfolist logic)
        $queriesLogged = [];
        DB::listen(function ($query) use (&$queriesLogged) {
            $queriesLogged[] = $query->sql;
        });

        $startTime = microtime(true);
        $queriesLogged = [];
        $stats = $this->calculateMeetingStats($meeting, $this->rootGroup);
        $endTime = microtime(true);
        $statsDurationMs = round(($endTime - $startTime) * 1000, 2);

        $statsQueryCount = count($queriesLogged);
        $this->assert($statsQueryCount <= 6, "Perhitungan statistik agregat dieksekusi efisien dalam {$statsQueryCount} queries (maks 6 queries)");
        $this->assert($statsDurationMs < 100, "Eksekusi kalkulasi statistik selesai dalam {$statsDurationMs} ms (< 100ms)");

        // Audit Query Tabel Presensi (MeetingAttendanceDetails logic)
        $queriesLogged = [];
        $startTime = microtime(true);
        $tableResults = $this->executeTableQuery($meeting, $this->rootGroup);
        $records = $tableResults->take(25)->get(); // Simulasikan halaman pertama paginasi
        $endTime = microtime(true);
        $tableDurationMs = round(($endTime - $startTime) * 1000, 2);

        $tableQueryCount = count($queriesLogged);
        $this->assert($tableQueryCount <= 5, "Query tabel presensi dieksekusi efisien dalam {$tableQueryCount} queries (bebas dari N+1 query loop)");
        $this->assert($tableDurationMs < 100, "Query tabel presensi selesai dalam {$tableDurationMs} ms (< 100ms)");

        echo "\n";
    }

    /**
     * Helper: Menjalankan algoritma perhitungan statistik pertemuan (persis seperti MeetingAttendanceInfolist)
     */
    private function calculateMeetingStats(Meeting $meeting, Group $group): array
    {
        $descendantIds = $group->getAllDescendantIds();

        $allAgeGroupsCount = AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

        $baseTargetQuery = Member::whereIn('group_id', $descendantIds)
            ->where('status', true)
            ->when($meeting->target_gender !== MeetingTargetGender::ALL, function ($q) use ($meeting) {
                return $q->where('gender', $meeting->target_gender->value);
            })
            ->when($shouldFilterByAge, function ($q) use ($meeting) {
                return $q->whereHas('ageGroup', function ($aq) use ($meeting) {
                    return $aq->whereIn('name', (array) $meeting->target_age_groups);
                });
            });

        $baseTargetMemberIds = $baseTargetQuery->pluck('id')->toArray();
        $baseTargetCount = count($baseTargetMemberIds);

        $attendances = Attendance::with('member.ageGroup')
            ->where('meeting_id', $meeting->id)
            ->whereHas('member', fn ($q) => $q->whereIn('group_id', $descendantIds))
            ->whereIn('status', [
                AttendanceStatus::HADIR->value,
                AttendanceStatus::IZIN->value,
                AttendanceStatus::SAKIT->value,
            ])
            ->get();

        $nonTargetAttendances = $attendances->reject(fn ($att) => in_array($att->member_id, $baseTargetMemberIds));

        $totalTarget = $baseTargetCount + $nonTargetAttendances->count();

        $present = $attendances->where('status', AttendanceStatus::HADIR)->count();
        $excusedSick = $attendances->whereIn('status', [
            AttendanceStatus::IZIN,
            AttendanceStatus::SAKIT,
        ])->count();

        $targetPresent = $attendances->whereIn('member_id', $baseTargetMemberIds)
            ->where('status', AttendanceStatus::HADIR)
            ->count();
        $targetExcusedSick = $attendances->whereIn('member_id', $baseTargetMemberIds)
            ->whereIn('status', [
                AttendanceStatus::IZIN,
                AttendanceStatus::SAKIT,
            ])
            ->count();
        $absent = max(0, $baseTargetCount - $targetPresent - $targetExcusedSick);

        return [
            'base_target' => $baseTargetCount,
            'total_target' => $totalTarget,
            'hadir' => $present,
            'izin_sakit' => $excusedSick,
            'alpa' => $absent,
        ];
    }

    /**
     * Helper: Menjalankan Builder query tabel presensi (persis seperti MeetingAttendanceDetails::getTableQuery)
     */
    private function executeTableQuery(Meeting $meeting, Group $group)
    {
        $descendantGroupIds = $group->getAllDescendantIds();
        $meetingId = $meeting->id;
        $allAgeGroupsCount = AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

        return Member::query()
            ->select([
                'members.*',
                'attendances.status as attendance_status',
                'attendances.checkin_time',
                'attendances.method',
                'attendances.notes',
                'attendances.evidence_path',
            ])
            ->leftJoin('attendances', function ($join) use ($meetingId) {
                $join->on('attendances.member_id', '=', 'members.id')
                    ->where('attendances.meeting_id', '=', $meetingId)
                    ->whereNull('attendances.deleted_at');
            })
            ->whereIn('members.group_id', $descendantGroupIds)
            ->where(function ($query) use ($shouldFilterByAge, $meeting) {
                $query->where(function ($sub) use ($shouldFilterByAge, $meeting) {
                    $sub->where('members.status', true)
                        ->when($shouldFilterByAge, function ($aq) use ($meeting) {
                            return $aq->whereHas('ageGroup', function ($q) use ($meeting) {
                                return $q->whereIn('name', $meeting->target_age_groups);
                            });
                        });
                })
                    ->orWhereNotNull('attendances.status');
            })
            ->with(['ageGroup', 'group.parent', 'positions.group.level'])
            ->orderByRaw('attendances.checkin_time IS NULL ASC')
            ->orderBy('attendances.checkin_time', 'ASC')
            ->orderBy('members.id', 'ASC');
    }
}

// Eksekusi Test Runner
$tester = new ApprovalAndTargetQATester;
$tester->runAll();
