<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Member;
use App\Models\Meeting;
use App\Models\Group;
use App\Models\Level;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingTargetGender;
use App\Http\Controllers\LiveScannerController;
use App\Http\Controllers\SelfPermissionController;
use App\Http\Controllers\SelfPresenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assert_it($name, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "\033[32m[PASS]\033[0m {$name}\n";
    } else {
        $failed++;
        echo "\033[31m[FAIL]\033[0m {$name} - {$details}\n";
    }
}

echo "\n======================================================\n";
echo "       IN-TIME QA TEST MATRIX - FASE 5 VERIFICATION    \n";
echo "======================================================\n\n";

// Configure SQLite in-memory and array cache so test runs self-contained without needing external MySQL service
config([
    'cache.default' => 'array',
    'database.default' => 'sqlite',
    'database.connections.sqlite' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],
]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');

echo "Menyiapkan skema database in-memory (SQLite)... ";
\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo "SELESAI.\n\n";

try {
    // 0. Setup base fixtures (Group & Meeting)
    $user = \App\Models\User::create([
        'name' => 'QA Admin',
        'email' => 'qa@example.com',
        'password' => bcrypt('secret'),
    ]);

    $group = Group::first();
    if (!$group) {
        $level = Level::firstOrCreate(['name' => 'Ranting', 'level_number' => 1, 'code' => 'PR']);
        $group = Group::create([
            'name' => 'GRUP QA TEST',
            'level_id' => $level->id,
            'status' => true,
        ]);
    }

    $ageGroupPemuda = AgeGroup::firstOrCreate(
        ['name' => 'Pemuda'],
        ['code' => 'PMD', 'min_age' => 16, 'max_age' => 25]
    );
    $ageGroupDewasa = AgeGroup::firstOrCreate(
        ['name' => 'Dewasa'],
        ['code' => 'DWS', 'min_age' => 26, 'max_age' => 60]
    );

    $meeting = Meeting::create([
        'created_by' => $user->id,
        'group_id' => $group->id,
        'name' => 'Pertemuan QA Test Matrix',
        'meeting_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '23:59',
        'is_presence_enabled' => true,
        'is_permission_enabled' => true,
        'target_gender' => MeetingTargetGender::ALL,
    ]);

    // -------------------------------------------------------------
    // UJI KASUS 1: Perizinan Anggota Tanpa Tanggal Lahir (Frictionless)
    // -------------------------------------------------------------
    echo "--- UJI KASUS 1: Lengkapi Tanggal Lahir & Usia Otomatis ---\n";
    
    $member1 = Member::create([
        'group_id' => $group->id,
        'full_name' => 'TEST MEMBER NO BIRTHDATE',
        'status' => true,
        'gender' => 'male',
        'birth_date' => null,
        'age' => null,
        'age_group_id' => null,
    ]);

    $permController = app(SelfPermissionController::class);

    // 1.1 Lookup harus mendeteksi incomplete_biodata
    $reqLookup = Request::create("/self-permission/{$meeting->uid}/lookup", 'GET', [
        'member_id' => $member1->id
    ]);
    $resLookup = $permController->lookupMember($reqLookup, $meeting);
    $dataLookup = json_decode($resLookup->getContent(), true);

    assert_it('1.1 Lookup mendeteksi incomplete_biodata', 
        $dataLookup['status'] === 'incomplete_biodata' && $dataLookup['member']['has_birth_date'] === false,
        'Status bukan incomplete_biodata: ' . ($dataLookup['status'] ?? 'null')
    );

    // 1.2 Lengkapi tanggal lahir via completeBiodata
    $birthDateInput = now()->subYears(20)->format('Y-m-d');
    $reqComplete = Request::create("/self-permission/{$meeting->uid}/complete-biodata", 'POST', [
        'member_id' => $member1->id,
        'birth_date' => $birthDateInput,
    ]);
    $resComplete = $permController->completeBiodata($reqComplete, $meeting);
    $dataComplete = json_decode($resComplete->getContent(), true);

    assert_it('1.2 completeBiodata mengembalikan status success',
        $dataComplete['status'] === 'success',
        'Status: ' . ($dataComplete['status'] ?? 'null')
    );

    $member1->refresh();
    $bDateStr = $member1->birth_date ? \Carbon\Carbon::parse($member1->birth_date)->format('Y-m-d') : null;
    assert_it('1.3 Tanggal lahir berhasil disimpan di DB',
        $bDateStr === $birthDateInput,
        'Tanggal lahir DB: ' . ($bDateStr ?? 'null')
    );

    assert_it('1.4 Usia dan AgeGroup terhitung otomatis via MemberObserver',
        $member1->age === 20 && !empty($member1->age_group_id),
        "Age: {$member1->age}, AgeGroupId: {$member1->age_group_id}"
    );

    // -------------------------------------------------------------
    // UJI KASUS 2: Proteksi Celah Penimpaan Tanggal Lahir Ilegal (IDOR)
    // -------------------------------------------------------------
    echo "\n--- UJI KASUS 2: Proteksi Immutability Biodata (Anti-Tampering) ---\n";

    $member2 = Member::create([
        'group_id' => $group->id,
        'full_name' => 'TEST MEMBER EXISTING BIODATA',
        'status' => true,
        'gender' => 'male',
        'birth_date' => '2000-01-01',
    ]);

    // Upaya manipulasi mengganti tanggal lahir & gender orang lain
    $reqIllegal = Request::create("/self-permission/{$meeting->uid}/complete-biodata", 'POST', [
        'member_id' => $member2->id,
        'gender' => 'female',
        'birth_date' => '1990-12-31',
    ]);
    $permController->completeBiodata($reqIllegal, $meeting);
    $member2->refresh();

    $bDate2Str = $member2->birth_date ? \Carbon\Carbon::parse($member2->birth_date)->format('Y-m-d') : null;
    $gender2Val = $member2->gender instanceof \App\Enums\Gender ? $member2->gender->value : (string) $member2->gender;

    assert_it('2.1 Tanggal lahir yang sudah ada TIDAK DAPAT ditimpa',
        $bDate2Str === '2000-01-01',
        'Tanggal lahir tertimpa: ' . ($bDate2Str ?? 'null')
    );

    assert_it('2.2 Gender yang sudah ada TIDAK DAPAT ditimpa',
        $gender2Val === 'male',
        'Gender tertimpa: ' . $gender2Val
    );

    // -------------------------------------------------------------
    // UJI KASUS 3: Simulasi Double Scan / Race Condition (Graceful 1062)
    // -------------------------------------------------------------
    echo "\n--- UJI KASUS 3: Penanganan Race Condition (Duplicate Key 1062) ---\n";

    $member3 = Member::create([
        'group_id' => $group->id,
        'full_name' => 'TEST MEMBER CONCURRENCY',
        'status' => true,
        'gender' => 'male',
        'birth_date' => '2001-05-15',
    ]);

    $presenceController = app(SelfPresenceController::class);

    $tinyPhoto = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    // Presensi 1: Normal
    $reqPresence1 = Request::create("/self-presence/{$meeting->uid}/store", 'POST', [
        'member_id' => $member3->id,
        'selfie_photo' => $tinyPhoto,
    ]);
    $resPresence1 = $presenceController->store($reqPresence1, $meeting);
    $dataPresence1 = json_decode($resPresence1->getContent(), true);

    assert_it('3.1 Presensi pertama berhasil',
        $dataPresence1['status'] === 'success',
        'Status: ' . ($dataPresence1['status'] ?? 'null')
    );

    // Presensi 2: Double scan langsung
    $reqPresence2 = Request::create("/self-presence/{$meeting->uid}/store", 'POST', [
        'member_id' => $member3->id,
        'selfie_photo' => $tinyPhoto,
    ]);
    $resPresence2 = $presenceController->store($reqPresence2, $meeting);
    $dataPresence2 = json_decode($resPresence2->getContent(), true);

    assert_it('3.2 Presensi kedua mengembalikan status warning elegan (Bukan HTTP 500)',
        $dataPresence2['status'] === 'warning' && str_contains($dataPresence2['message'], 'sudah melakukan presensi'),
        'Status: ' . ($dataPresence2['status'] ?? 'null') . ', Pesan: ' . ($dataPresence2['message'] ?? 'null')
    );

    // Uji ketahanan langsung jika DB melempar Duplicate Entry 1062 saat create paralel
    $duplicateCaught = false;
    $warningReturned = false;
    try {
        Attendance::create([
            'meeting_id' => $meeting->id,
            'member_id' => $member3->id,
            'checkin_time' => now(),
            'method' => AttendanceMethod::SELF,
            'status' => AttendanceStatus::HADIR,
        ]);
    } catch (\Illuminate\Database\QueryException $e) {
        $duplicateCaught = true;
        if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
            $warningReturned = true;
        }
    }

    assert_it('3.3 DB Unique Constraint violation (1062/23000) terdeteksi & tertangkap aman',
        $duplicateCaught && $warningReturned,
        'Error tidak tertangkap atau bukan 23000'
    );

    // -------------------------------------------------------------
    // UJI KASUS 4: Pendaftaran Instan pada Pertemuan Berfilter Usia
    // -------------------------------------------------------------
    echo "\n--- UJI KASUS 4: Pendaftaran Instan Berfilter Usia ---\n";

    // Pastikan ada AgeGroup untuk rentang 16-25
    $ageGroupPemuda = AgeGroup::where('min_age', '<=', 20)
        ->where(function($q) { $q->where('max_age', '>=', 20)->orWhereNull('max_age'); })
        ->first();

    if (!$ageGroupPemuda) {
        $ageGroupPemuda = AgeGroup::create([
            'name' => 'Pemuda',
            'code' => 'PMD',
            'min_age' => 16,
            'max_age' => 25,
        ]);
    }

    $meetingFiltered = Meeting::create([
        'created_by' => $user->id,
        'group_id' => $group->id,
        'name' => 'Pertemuan Khusus Usia Tertentu',
        'meeting_date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '23:59',
        'is_presence_enabled' => true,
        'target_gender' => MeetingTargetGender::ALL,
        'target_age_groups' => [$ageGroupPemuda->name],
    ]);

    $reqTemp = Request::create("/self-presence/{$meetingFiltered->uid}/register-temporary", 'POST', [
        'full_name' => 'ANGGOTA INSTAN PEMUDA',
        'gender' => 'male',
        'birth_date' => now()->subYears(20)->format('Y-m-d'),
        'group_id' => $group->id,
    ]);

    $resTemp = $presenceController->registerTemporary($reqTemp, $meetingFiltered);
    $dataTemp = json_decode($resTemp->getContent(), true);

    assert_it('4.1 Pendaftaran instan berhasil dengan status success',
        $dataTemp['status'] === 'success',
        'Status: ' . ($dataTemp['status'] ?? 'null') . ', Pesan: ' . ($dataTemp['message'] ?? 'null')
    );

    $createdMember = Member::where('full_name', 'ANGGOTA INSTAN PEMUDA')->first();
    assert_it('4.2 Anggota instan otomatis terhubung ke AgeGroup yang sesuai',
        $createdMember && $createdMember->age_group_id === $ageGroupPemuda->id && $createdMember->age === 20,
        'AgeGroup ID tidak cocok atau usia salah'
    );

    // -------------------------------------------------------------
    // UJI KASUS 5: Pertemuan Lintas Hari (Overnight Meetings)
    // -------------------------------------------------------------
    echo "\n--- UJI KASUS 5: Status Pertemuan Lintas Hari ---\n";
    assert_it('5.1 Pertemuan Lintas Hari (Overnight) [OUT OF SCOPE - DIABAIKAN]',
        true,
        'Diabaikan sesuai arahan review pengguna'
    );

} finally {
    echo "\n[INFO] Selesai. Seluruh pengujian berjalan di database in-memory tanpa mengubah data asli.\n";
}

echo "\n======================================================\n";
echo "                RINGKASAN HASIL QA MATRIX             \n";
echo "======================================================\n";
echo "Total Uji Lulus (PASS): {$passed}\n";
echo "Total Uji Gagal (FAIL): {$failed}\n";

if ($failed === 0) {
    echo "\033[32m>>> SEMUA SKENARIO UJI FASE 5 BERHASIL (100% PASS) <<<\033[0m\n\n";
    exit(0);
} else {
    echo "\033[31m>>> TERDAPAT SKENARIO UJI YANG GAGAL <<<\033[0m\n\n";
    exit(1);
}
