<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Member;
use App\Models\Attendance;
use App\Enums\MeetingTargetGender;
use App\Enums\MembershipType;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceMethod;
use App\Enums\Gender;
use Illuminate\Http\Request;

class LiveScannerController extends Controller
{
    public function index(Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting);

        $attendances = Attendance::where('meeting_id', $meeting->id)
            ->with('member')
            ->latest()
            ->get();

        $descendantIds = $meeting->group->getAllDescendantIds();
        $groups = \App\Models\Group::whereIn('id', $descendantIds)
            ->doesntHave('children')
            ->orderBy('name')
            ->get();

        return view('scanner.live', compact('meeting', 'attendances', 'groups'));
    }

    public function process(Request $request, Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting, 'manage');
        $code = $request->code;
        $member = Member::where('member_code', $code)->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => "Kode QR '$code' tidak terdaftar."
            ]);
        }

        $validation = $this->checkMemberEligibility($meeting, $member);
        if ($validation)
            return $validation;

        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'warning',
                'message' => "{$member->full_name} sudah diabsen jam " . ($existing->checkin_time?->format('H:i') ?? '-')
            ]);
        }

        $isLate = now()->greaterThan($meeting->meeting_date->copy()->setTimeFrom($meeting->start_time));

        $isSupervisor = $member->hasPositionIn($meeting->group);

        // Determine if they are a direct target
        $targetGroupIds = $meeting->group->getAllDescendantIds();
        $isTargetGroup = in_array($member->group_id, $targetGroupIds);
        $isTargetGender = ($meeting->target_gender === MeetingTargetGender::ALL || $member->gender === $meeting->target_gender);

        $allAgeGroupsCount = \App\Models\AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
        $isTargetAge = !$shouldFilterByAge || ($member->ageGroup && in_array($member->ageGroup->name, (array) $meeting->target_age_groups));

        $isDirectTarget = $isTargetGroup && $isTargetGender && $isTargetAge;

        // Supervisor who is NOT a direct target gets the badge and bypasses late status
        $isSupervisorNotDirectTarget = $isSupervisor && !$isDirectTarget;
        $notes = ($isLate && !$isSupervisorNotDirectTarget) ? 'TERLAMBAT' : null;

        try {
            Attendance::create([
                'meeting_id' => $meeting->id,
                'member_id' => $member->id,
                'checkin_time' => now(),
                'method' => AttendanceMethod::QR_CODE,
                'status' => AttendanceStatus::HADIR,
                'notes' => $notes,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "{$member->full_name} sudah diabsen sebelumnya.",
                    'name' => $member->full_name,
                ]);
            }
            throw $e;
        }

        $label = $isSupervisorNotDirectTarget ? " [PENGURUS]" : "";
        $message = "Hadir: {$member->full_name}{$label}";
        if ($isLate && !$isSupervisorNotDirectTarget) {
            $message .= " (TERLAMBAT)";
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'name' => $member->full_name,
            'time' => now()->format('H:i'),
            'is_late' => $isLate,
            'is_supervisor_badge' => $isSupervisorNotDirectTarget
        ]);
    }

    private function checkMemberEligibility(Meeting $meeting, Member $member)
    {
        // 1. Validation: Meeting date must be today
        if (!$meeting->meeting_date->isToday()) {
            $dateLabel = $meeting->meeting_date->translatedFormat('l, d F Y');
            return response()->json([
                'status' => 'error',
                'message' => "Pertemuan ini dijadwalkan pada {$dateLabel}."
            ]);
        }

        // 2. Validation: Check-in not yet open
        $openTime = $meeting->checkin_open_time ?? $meeting->start_time;
        if ($openTime) {
            $openDateTime = $meeting->meeting_date->copy()->setTimeFrom($openTime);
            if (now()->isBefore($openDateTime)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Presensi belum dibuka. Presensi dibuka mulai jam {$openTime->format('H:i')}."
                ]);
            }
        }

        // 3. Validation: Meeting session has ended
        if ($meeting->meeting_date->copy()->setTimeFrom($meeting->end_time)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => "Sesi presensi untuk pertemuan ini sudah berakhir."
            ]);
        }

        // 4. Validation: Member active status
        if ($member->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => "Anggota {$member->full_name} sudah tidak aktif (dihapus)."
            ]);
        }

        // 5. Check if Member is Supervisor (Authority in meeting lineage)
        $isSupervisor = $member->hasPositionIn($meeting->group);
        $canBypassFilters = $isSupervisor;

        // 6. Validation: Group Hierarchy (Bypassed by Supervisors)
        if (!$canBypassFilters) {
            $allowedGroupIds = $meeting->group->getAllDescendantIds();
            if (!in_array($member->group_id, $allowedGroupIds)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "{$member->full_name} bukan anggota {$meeting->group->name} atau turunannya."
                ]);
            }
        }

        // 7. Validation: Target Gender (Bypassed by Pengurus)
        if (!$canBypassFilters && $meeting->target_gender !== MeetingTargetGender::ALL && $member->gender !== $meeting->target_gender) {
            $targetLabel = $meeting->target_gender->getLabel();
            return response()->json([
                'status' => 'warning',
                'message' => "Pertemuan ini hanya untuk $targetLabel."
            ]);
        }

        // 8. Validation: Target Age Groups (Bypassed by Pengurus)
        if (!$canBypassFilters && !empty($meeting->target_age_groups)) {
            $allAgeGroupsCount = \App\Models\AgeGroup::count();
            $selectedAgeGroupsCount = count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

            if ($shouldFilterByAge) {
                $memberAgeGroupName = $member->ageGroup?->name;
                if (!in_array($memberAgeGroupName, (array) $meeting->target_age_groups)) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => "Kategori usia ({$memberAgeGroupName}) tidak sesuai target pertemuan."
                    ]);
                }
            }
        }

        return null; // OK
    }


    public function search(Request $request, Meeting $meeting)
    {
        try {
            $this->authorizeGroupAccess($meeting, 'manage');
            $query = $request->q;
            $groupIdFilter = $request->group_id;

            if (empty($query) && empty($groupIdFilter)) {
                return response()->json([]);
            }

            $targetGroupIds = $meeting->group->getAllDescendantIds();
            $supervisorGroupIds = array_merge([$meeting->group_id], $meeting->group->getAllAncestorIds());

            // Precalculate static meeting filters for massive speedup
            $allAgeGroupsCount = \App\Models\AgeGroup::count();
            $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
            $ageGroupIds = $shouldFilterByAge
                ? \App\Models\AgeGroup::whereIn('name', (array) $meeting->target_age_groups)->pluck('id')->toArray()
                : [];

            // Precalculate members with supervisory positions
            $supervisorMemberIds = \Illuminate\Support\Facades\DB::table('member_positions')
                ->whereIn('group_id', $supervisorGroupIds)
                ->pluck('member_id')
                ->toArray();

            $members = Member::with(['ageGroup', 'group'])
                ->when($query, function ($q) use ($query) {
                    $q->where(function ($sq) use ($query) {
                        $sq->where('full_name', 'like', "%$query%")
                            ->orWhere('member_code', 'like', "%$query%");
                    });
                })
                ->when($groupIdFilter, function ($q) use ($groupIdFilter) {
                    $q->where('group_id', $groupIdFilter);
                })
                ->where(function ($q) use ($targetGroupIds, $supervisorGroupIds, $meeting, $shouldFilterByAge, $ageGroupIds, $supervisorMemberIds) {
                    // Option A: Regular members who match meeting filters (Gender & Age)
                    $q->where(function ($sq) use ($targetGroupIds, $meeting, $shouldFilterByAge, $ageGroupIds) {
                        $sq->whereIn('group_id', $targetGroupIds)
                            ->when($meeting->target_gender !== MeetingTargetGender::ALL, function ($gq) use ($meeting) {
                                return $gq->where('gender', $meeting->target_gender->value);
                            })
                            ->when($shouldFilterByAge, function ($aq) use ($ageGroupIds) {
                                return $aq->whereIn('age_group_id', $ageGroupIds);
                            });
                    })
                        // Option B: Supervisors (Authority in meeting group or ancestors)
                        ->orWhere(function ($pq) use ($supervisorGroupIds, $supervisorMemberIds) {
                        $pq->where('membership_type', MembershipType::PENGURUS->value)
                            ->whereIn('group_id', $supervisorGroupIds)
                            ->orWhereIn('id', $supervisorMemberIds);
                    });
                })
                ->when(empty($groupIdFilter), function ($q) {
                    $q->limit(10);
                })
                ->get()
                ->map(function (Member $m) use ($meeting, $targetGroupIds, $supervisorGroupIds, $supervisorMemberIds, $allAgeGroupsCount, $selectedAgeGroupsCount) {
                    // Determine if they are a direct target
                    $isTargetGender = ($meeting->target_gender === MeetingTargetGender::ALL || $m->gender === $meeting->target_gender->value);

                    $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
                    $isTargetAge = true;
                    if ($shouldFilterByAge) {
                        $isTargetAge = $m->ageGroup && in_array($m->ageGroup->name, (array) $meeting->target_age_groups);
                    }
                    $isDirectTarget = in_array($m->group_id, $targetGroupIds) && $isTargetGender && $isTargetAge;

                    // In-memory evaluation without N+1 query or hierarchy recursion
                    $isSupervisor = ($m->membership_type === MembershipType::PENGURUS && in_array($m->group_id, $supervisorGroupIds))
                        || in_array($m->id, $supervisorMemberIds);
                    $label = (!$isDirectTarget && $isSupervisor) ? MembershipType::PENGURUS->getLabel() : '';

                    $groupName = $m->group?->name ?? '-';

                    return [
                        'id' => $m->id,
                        'text' => $m->full_name,
                        'code' => $m->member_code,
                        'group' => $groupName,
                        'badge' => $label,
                    ];
                });

            return response()->json($members);
        } catch (\Exception $e) {
            \Log::error('Search error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function manualStore(Request $request, Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting, 'manage');

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'status' => 'nullable|in:hadir,izin,sakit',
            'notes' => 'nullable|string|max:500',
            'evidence' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:2500',
        ]);

        $memberId = $request->member_id;
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['status' => 'error', 'message' => 'Anggota tidak ditemukan.']);
        }

        $validation = $this->checkMemberEligibility($meeting, $member);
        if ($validation)
            return $validation;

        $status = $request->status ?? AttendanceStatus::HADIR->value;

        if (in_array($status, [AttendanceStatus::IZIN->value, AttendanceStatus::SAKIT->value]) && !auth()->user()->can('SetExcusedAttendance')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk menginput status ' . strtoupper($status) . '.'
            ], 403);
        }

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('attendance-evidence', 'public');
        }

        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing) {
            // If same status, reject as duplicate
            if ($existing->status?->value === $status) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "{$member->full_name} sudah tercatat " . strtoupper($existing->status?->value ?? $status) . "."
                ]);
            }

            // Allow status update (e.g., from hadir to izin/sakit)
            $oldStatus = strtoupper($existing->status?->value ?? '');
            $existing->update([
                'status' => $status,
                'evidence_path' => $evidencePath ?? $existing->evidence_path,
                'notes' => $request->notes ?? $existing->notes,
            ]);

            $newStatus = strtoupper($status);
            return response()->json([
                'status' => 'success',
                'message' => "{$member->full_name} diubah dari {$oldStatus} → {$newStatus}."
            ]);
        }

        // Late Detection for manual present
        $isLate = false;
        if ($status === AttendanceStatus::HADIR->value) {
            $isLate = now()->greaterThan($meeting->meeting_date->copy()->setTimeFrom($meeting->start_time));
        }

        $isSupervisor = $member->hasPositionIn($meeting->group);

        // Determine if they are a direct target
        $targetGroupIds = $meeting->group->getAllDescendantIds();
        $isTargetGroup = in_array($member->group_id, $targetGroupIds);
        $isTargetGender = ($meeting->target_gender === MeetingTargetGender::ALL || $member->gender === $meeting->target_gender);

        $allAgeGroupsCount = \App\Models\AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
        $isTargetAge = !$shouldFilterByAge || ($member->ageGroup && in_array($member->ageGroup->name, (array) $meeting->target_age_groups));

        $isDirectTarget = $isTargetGroup && $isTargetGender && $isTargetAge;

        // Supervisor who is NOT a direct target gets the badge and bypasses late status
        $isSupervisorNotDirectTarget = $isSupervisor && !$isDirectTarget;
        $finalNotes = $request->notes ?? (($isLate && !$isSupervisorNotDirectTarget) ? 'TERLAMBAT' : null);

        try {
            Attendance::create([
                'meeting_id' => $meeting->id,
                'member_id' => $member->id,
                'checkin_time' => now(),
                'method' => AttendanceMethod::MANUAL,
                'status' => $status,
                'evidence_path' => $evidencePath,
                'notes' => $finalNotes,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "Presensi untuk {$member->full_name} sudah tercatat sebelumnya.",
                    'name' => $member->full_name,
                ]);
            }
            throw $e;
        }

        $label = $isSupervisorNotDirectTarget ? " [PENGURUS]" : "";

        $statusLabel = ucfirst(strtolower($status));
        $message = "{$statusLabel}: {$member->full_name}{$label}";
        if ($isLate && !$isSupervisorNotDirectTarget)
            $message .= " (TERLAMBAT)";

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'name' => $member->full_name,
            'code' => $member->member_code ?? '-',
            'time' => now()->format('H:i'),
            'is_late' => $isLate,
            'is_supervisor_badge' => $isSupervisorNotDirectTarget
        ]);
    }

    public function registerTemporary(Request $request, Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting, 'manage');

        if ($meeting->meeting_date->copy()->setTimeFrom($meeting->end_time)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi presensi untuk pertemuan ini sudah berakhir.'
            ], 422);
        }

        $request->validate([
            'full_name' => 'required|string|max:255',
            'nick_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'group_id' => 'required|exists:groups,id',
            'birth_date' => 'required|date|before:today',
            'photo' => 'nullable|image|max:2500',
        ]);

        $allowedGroupIds = $meeting->group->getAllDescendantIds();
        if (!in_array($request->group_id, $allowedGroupIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelompok yang dipilih tidak termasuk dalam cakupan pertemuan ini.'
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('member_photos', 'public');
        }

        $member = Member::create([
            'full_name' => $request->full_name,
            'nick_name' => $request->nick_name,
            'gender' => $request->gender,
            'group_id' => $request->group_id,
            'birth_date' => $request->birth_date,
            'is_permanent' => false,
            'status' => false,
            'member_code' => null,
            'profile_photo_path' => $photoPath,
        ]);

        $member->load(['group', 'ageGroup']);

        return response()->json([
            'status' => 'success',
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
            ]
        ]);
    }

    /**
     * Validate that the authenticated user has group hierarchy access to the given meeting.
     * 
     * @param string $mode 'view' for seeing, 'manage' for scanning/updating
     */
    private function authorizeGroupAccess(Meeting $meeting, string $mode = 'view'): void
    {
        $user = auth()->user();

        if (!$user || $user->isSuperAdmin()) {
            return;
        }

        if (!$user->can('openScanner', $meeting)) {
            abort(403, 'Role Anda tidak diizinkan untuk mengakses fitur scanner.');
        }

        if (!$user->group_id) {
            abort(403, 'Akun Anda belum ditempatkan di grup manapun.');
        }

        // 1. Cek Descendant (Grup Anda & Anak-anaknya) -> Selalu punya akses View & Manage
        $descendantIds = $user->group->getAllDescendantIds();
        if (in_array($meeting->group_id, $descendantIds)) {
            return;
        }

        // 2. Cek Ancestor (Induk / Atasan Anda) -> Hanya punya akses VIEW
        if ($mode === 'view') {
            $ancestorIds = $user->group->getAllAncestorIds();
            if (in_array($meeting->group_id, $ancestorIds)) {
                return;
            }
        }

        abort(403, $mode === 'manage'
            ? 'Hanya penyelenggara (atau penyelenggara) yang dapat melakukan presensi.'
            : 'Anda tidak memiliki akses ke pertemuan ini.');
    }
}
