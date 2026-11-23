<?php

/**
 * inTime - Automated Policy & Authorization QA Test Suite
 * Memvalidasi seluruh skenario Fase 7 secara komprehensif.
 */

require 'd:/SandBox/Antigravity/inTime/vendor/autoload.php';
$app = require_once 'd:/SandBox/Antigravity/inTime/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Setup SQLite in-memory database and array cache to test without running MySQL
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

// Create required core tables in memory
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

\Illuminate\Support\Facades\Schema::create('groups', function ($table) {
    $table->id();
    $table->string('uid')->nullable();
    $table->string('name');
    $table->string('code')->nullable();
    $table->unsignedBigInteger('parent_id')->nullable();
    $table->unsignedBigInteger('level_id')->nullable();
    $table->boolean('status')->default(true);
    $table->timestamps();
    $table->softDeletes();
});

// Run Spatie permission migration
$permissionMigration = require 'd:/SandBox/Antigravity/inTime/database/migrations/2026_01_29_155638_create_permission_tables.php';
$permissionMigration->up();

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Group;
use App\Models\Member;
use App\Models\Meeting;
use App\Models\Attendance;
use App\Models\MemberPosition;
use Spatie\Permission\Models\Role;
use App\Policies\UserPolicy;
use App\Policies\MemberPolicy;
use App\Policies\GroupPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\RolePolicy;
use App\Policies\AttendancePolicy;
use App\Policies\MemberPositionPolicy;
use Illuminate\Support\Facades\Gate;

class PolicyQATester
{
    private int $passed = 0;
    private int $failed = 0;

    public function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function runAll(): void
    {
        echo "========================================================\n";
        echo "  inTime - QA Otorisasi & Policy (Fase 7)\n";
        echo "========================================================\n\n";

        $this->testScenario7_1_SuperAdmin();
        $this->testScenario7_2_OperatorScopingAndAntiIdor();
        $this->testScenario7_3_SafetyNetUserAndRole();
        $this->testScenario7_4_BulkImportExport();

        echo "\n========================================================\n";
        echo "  HASIL AKHIR PENGUJIAN: {$this->passed} PASSED, {$this->failed} FAILED\n";
        echo "========================================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function createTestUser(int $id, string $name, UserRole $role, ?int $groupId = null): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

        if ($role === UserRole::OPERATOR) {
            $roleModel->givePermissionTo([
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'View:Member', 'guard_name' => 'web']),
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'Update:Member', 'guard_name' => 'web']),
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'Delete:Member', 'guard_name' => 'web']),
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'DownloadKta:Member', 'guard_name' => 'web']),
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'MakePermanent:Member', 'guard_name' => 'web']),
            ]);
        }

        $user = User::updateOrCreate(
            ['id' => $id],
            [
                'name' => $name,
                'role' => $role,
                'group_id' => $groupId,
                'email' => strtolower(str_replace(' ', '', $name)) . "@test.com",
            ]
        );

        $user->assignRole($roleModel);

        return $user;
    }

    /**
     * Skenario 7.1: Pengujian Otoritas Penuh Super Admin
     */
    private function testScenario7_1_SuperAdmin(): void
    {
        echo "▶ Menjalankan Skenario 7.1: Otoritas Penuh Super Admin\n";

        // Setup Super Admin User
        $superAdmin = $this->createTestUser(1, 'Super Admin Intime', UserRole::SUPER_ADMIN);

        // Policies
        $userPolicy = new UserPolicy();
        $memberPolicy = new MemberPolicy();
        $groupPolicy = new GroupPolicy();
        $meetingPolicy = new MeetingPolicy();
        $rolePolicy = new RolePolicy();
        $attendancePolicy = new AttendancePolicy();
        $positionPolicy = new MemberPositionPolicy();

        // 1. UserPolicy: Super admin can viewAny, view, create, update
        $this->assert($userPolicy->viewAny($superAdmin) === true, "UserPolicy::viewAny diizinkan untuk Super Admin");
        $targetUser = new User();
        $targetUser->id = 99;
        $targetUser->role = UserRole::OPERATOR;
        $targetUser->setRelation('roles', collect());
        $this->assert($userPolicy->view($superAdmin, $targetUser) === true, "UserPolicy::view diizinkan untuk Super Admin");
        $this->assert($userPolicy->update($superAdmin, $targetUser) === true, "UserPolicy::update diizinkan untuk Super Admin");

        // 2. MemberPolicy: Super admin can manage member in any group
        $randomGroup = new Group();
        $randomGroup->id = 888;
        $member = new Member();
        $member->id = 501;
        $member->group_id = 888;
        $member->setRelation('group', $randomGroup);

        $this->assert($memberPolicy->view($superAdmin, $member) === true, "MemberPolicy::view diizinkan untuk grup manapun");
        $this->assert($memberPolicy->update($superAdmin, $member) === true, "MemberPolicy::update diizinkan untuk grup manapun");
        $this->assert($memberPolicy->delete($superAdmin, $member) === true, "MemberPolicy::delete diizinkan untuk grup manapun");
        $this->assert($memberPolicy->generateCard($superAdmin, $member) === true, "MemberPolicy::generateCard diizinkan untuk grup manapun");
        $this->assert($memberPolicy->makePermanent($superAdmin, $member) === true, "MemberPolicy::makePermanent diizinkan untuk grup manapun");

        // 3. GroupPolicy & MeetingPolicy
        $this->assert($groupPolicy->viewAny($superAdmin) === true, "GroupPolicy::viewAny diizinkan untuk Super Admin");
        $meeting = new Meeting();
        $meeting->id = 77;
        $meeting->group_id = 888;
        $meeting->setRelation('group', $randomGroup);
        $this->assert($meetingPolicy->view($superAdmin, $meeting) === true, "MeetingPolicy::view diizinkan untuk Super Admin");

        // 4. AttendancePolicy & MemberPositionPolicy
        $attendance = new Attendance();
        $attendance->id = 11;
        $attendance->setRelation('meeting', $meeting);
        $this->assert($attendancePolicy->update($superAdmin, $attendance) === true, "AttendancePolicy::update diizinkan untuk Super Admin");
        $this->assert($attendancePolicy->setExcusedAttendance($superAdmin, $attendance) === true, "AttendancePolicy::setExcusedAttendance diizinkan untuk Super Admin");

        $position = new MemberPosition();
        $position->id = 12;
        $position->setRelation('member', $member);
        $this->assert($positionPolicy->update($superAdmin, $position) === true, "MemberPositionPolicy::update diizinkan untuk Super Admin");

        echo "\n";
    }

    /**
     * Skenario 7.2: Pengujian Scoping Operator & Proteksi Anti-IDOR (Direct URL Tampering)
     */
    private function testScenario7_2_OperatorScopingAndAntiIdor(): void
    {
        echo "▶ Menjalankan Skenario 7.2: Operator Scoping & Proteksi Anti-IDOR\n";

        // Setup Kelompok A (Induk ID 1) dan Kelompok A1 (Anak ID 2)
        $groupA = new Group();
        $groupA->id = 1;
        $groupA->name = 'CABANG A';

        $groupA1 = new Group();
        $groupA1->id = 2;
        $groupA1->name = 'KELOMPOK A1';
        $groupA1->parent_id = 1;
        $groupA1->setRelation('parent', $groupA);

        // Mock children relationship
        $groupA->setRelation('children', collect([$groupA1]));
        $groupA1->setRelation('children', collect([]));

        // Setup Kelompok B (Cabang Terpisah ID 3)
        $groupB = new Group();
        $groupB->id = 3;
        $groupB->name = 'CABANG B (LAIN WILAYAH)';
        $groupB->setRelation('children', collect([]));

        // Setup Operator A (hanya berwenang atas Kelompok A & A1)
        $operatorA = $this->createTestUser(20, 'Operator Cabang A', UserRole::OPERATOR, 1);
        $operatorA->setRelation('group', $groupA);

        // Anggota A1 (Sah dikelola oleh Operator A)
        $memberA1 = new Member();
        $memberA1->id = 101;
        $memberA1->group_id = 2;
        $memberA1->setRelation('group', $groupA1);

        // Anggota B (Di luar wilayah Operator A)
        $memberB = new Member();
        $memberB->id = 999;
        $memberB->group_id = 3;
        $memberB->setRelation('group', $groupB);

        $memberPolicy = new MemberPolicy();

        // 1. Uji akses sah pada cabang sendiri
        $this->assert($memberPolicy->view($operatorA, $memberA1) === true, "Operator A diizinkan melihat Anggota A1 (anak cabang)");
        $this->assert($memberPolicy->update($operatorA, $memberA1) === true, "Operator A diizinkan mengubah Anggota A1");
        $this->assert($memberPolicy->makePermanent($operatorA, $memberA1) === true, "Operator A diizinkan memvalidasi status permanen Anggota A1");

        // 2. Uji Proteksi Anti-IDOR: Akses paksa ke Anggota B (Direct URL Tampering)
        $this->assert($memberPolicy->view($operatorA, $memberB) === false, "ANTI-IDOR: Operator A DITOLAK melihat Anggota B");
        $this->assert($memberPolicy->update($operatorA, $memberB) === false, "ANTI-IDOR: Operator A DITOLAK mengedit Anggota B");
        $this->assert($memberPolicy->delete($operatorA, $memberB) === false, "ANTI-IDOR: Operator A DITOLAK menghapus Anggota B");
        $this->assert($memberPolicy->downloadKta($operatorA, $memberB) === false, "ANTI-IDOR: Operator A DITOLAK mengunduh KTA Anggota B");
        $this->assert($memberPolicy->makePermanent($operatorA, $memberB) === false, "ANTI-IDOR: Operator A DITOLAK mengubah status Anggota B");

        // 3. Uji Filter Aksi Massal (Bulk Action Granular Filtering)
        $selectedRecords = collect([$memberA1, $memberB]);
        $authorizedRecords = $selectedRecords->filter(fn($rec) => $operatorA->can('makePermanent', $rec));

        $this->assert($authorizedRecords->count() === 1 && $authorizedRecords->first()->id === 101, "Bulk Action menyaring hanya data yang sah dan membuang data luar wilayah");

        // 4. Uji User::getManageableGroupIds()
        $manageableIds = $operatorA->getManageableGroupIds();
        $this->assert(in_array(1, $manageableIds) && in_array(2, $manageableIds) && !in_array(3, $manageableIds), "getManageableGroupIds() mengembalikan grup sendiri + turunan saja");

        echo "\n";
    }

    /**
     * Skenario 7.3: Pengujian Safety Net Akun Pengguna & Proteksi Role
     */
    private function testScenario7_3_SafetyNetUserAndRole(): void
    {
        echo "▶ Menjalankan Skenario 7.3: Safety Net Akun Pengguna & Proteksi Role\n";

        $userPolicy = new UserPolicy();
        $rolePolicy = new RolePolicy();

        // Admin User
        $admin = $this->createTestUser(55, 'Admin Biasa', UserRole::ADMIN);

        // 1. Anti Self-Deletion
        $this->assert($userPolicy->delete($admin, $admin) === false, "SAFETY NET: Pengguna tidak dapat menghapus akunnya sendiri");

        // 2. Proteksi Super Admin dari penghapusan oleh admin biasa
        $superAdmin = $this->createTestUser(1, 'Super Admin', UserRole::SUPER_ADMIN);

        $this->assert($userPolicy->delete($admin, $superAdmin) === false, "SAFETY NET: Admin tidak dapat menghapus akun Super Admin");

        // 3. Proteksi Role super_admin
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $roleOperator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

        $this->assert($rolePolicy->delete($superAdmin, $roleSuperAdmin) === false, "SAFETY NET: Role 'super_admin' kebal dari penghapusan (bahkan oleh Super Admin)");
        $this->assert($rolePolicy->update($admin, $roleSuperAdmin) === false, "SAFETY NET: Role 'super_admin' tidak dapat diubah oleh non-Super Admin");
        $this->assert($rolePolicy->delete($superAdmin, $roleOperator) === true, "Role biasa (operator) tetap dapat dihapus oleh Super Admin");

        echo "\n";
    }

    /**
     * Skenario 7.4: Pengujian Alur Import & Export Gate (Safe Nullable Defaults)
     */
    private function testScenario7_4_BulkImportExport(): void
    {
        echo "▶ Menjalankan Skenario 7.4: Alur Bulk Import & Export Gate\n";

        $superAdmin = $this->createTestUser(1, 'Super Admin', UserRole::SUPER_ADMIN);

        $memberPolicy = new MemberPolicy();
        $groupPolicy = new GroupPolicy();
        $meetingPolicy = new MeetingPolicy();

        // Test signature: policy methods must accept ?Model $record = null without TypeError
        $memberImportSafe = false;
        try {
            $memberImportSafe = is_bool($memberPolicy->import($superAdmin, null));
        } catch (\TypeError $e) {
            $memberImportSafe = false;
        }
        $this->assert($memberImportSafe, "MemberPolicy::import menerima parameter null tanpa TypeError");

        $memberExportSafe = false;
        try {
            $memberExportSafe = is_bool($memberPolicy->export($superAdmin, null));
        } catch (\TypeError $e) {
            $memberExportSafe = false;
        }
        $this->assert($memberExportSafe, "MemberPolicy::export menerima parameter null tanpa TypeError");

        $groupImportSafe = false;
        try {
            $groupImportSafe = is_bool($groupPolicy->import($superAdmin, null));
        } catch (\TypeError $e) {
            $groupImportSafe = false;
        }
        $this->assert($groupImportSafe, "GroupPolicy::import menerima parameter null tanpa TypeError");

        $groupExportSafe = false;
        try {
            $groupExportSafe = is_bool($groupPolicy->export($superAdmin, null));
        } catch (\TypeError $e) {
            $groupExportSafe = false;
        }
        $this->assert($groupExportSafe, "GroupPolicy::export menerima parameter null tanpa TypeError");

        $meetingExportSafe = false;
        try {
            $meetingExportSafe = is_bool($meetingPolicy->export($superAdmin, null));
        } catch (\TypeError $e) {
            $meetingExportSafe = false;
        }
        $this->assert($meetingExportSafe, "MeetingPolicy::export menerima parameter null tanpa TypeError");

        // Test User::canExport()
        $this->assert(method_exists($superAdmin, 'canExport'), "User model memiliki method terpusat canExport()");

        echo "\n";
    }
}

// Eksekusi
$tester = new PolicyQATester();
$tester->runAll();
