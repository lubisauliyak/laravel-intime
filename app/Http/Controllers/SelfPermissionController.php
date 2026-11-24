<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\MeetingTargetGender;
use App\Enums\MembershipType;
use App\Models\AgeGroup;
use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\Member;
use Illuminate\Http\Request;

class SelfPermissionController extends Controller
{
    public function index(Meeting $meeting)
    {
        $meeting->load('group.level');
        $isExpired = $meeting->isExpired() || ! $meeting->is_permission_enabled;

        $descendantIds = $meeting->group->getAllDescendantIds();
        $lineageIds = array_unique(array_merge([$meeting->group_id], $meeting->group->getAllAncestorIds()));
        $allowedGroupIds = array_unique(array_merge($descendantIds, $lineageIds));

        $groups = \App\Models\Group::with('level')
            ->whereIn('id', $allowedGroupIds)
            ->where(function ($query) use ($meeting) {
                $query->doesntHave('children')
                    ->orWhere('id', $meeting->group_id)
                    ->orWhereHas('members');
            })
            ->orderBy('name')
            ->get();

        return view('permission.self', compact('meeting', 'isExpired', 'groups'));
    }

    public function getMembersByGroup(Request $request, Meeting $meeting)
    {
        $groupId = $request->group_id;

        $targetGroupIds = $meeting->group->getAllDescendantIds();
        $meetingLineageIds = array_unique(array_merge(
            [$meeting->group_id],
            $meeting->group->getAllAncestorIds()
        ));

        $allAgeGroupsCount = AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

        $members = Member::with(['ageGroup', 'group'])
            ->where('group_id', $groupId)
            ->where(function ($q) use ($targetGroupIds, $meeting, $shouldFilterByAge, $meetingLineageIds) {
                $q->where(function ($sq) use ($targetGroupIds, $meeting, $shouldFilterByAge) {
                    $sq->whereIn('group_id', $targetGroupIds)
                        ->when($meeting->target_gender !== MeetingTargetGender::ALL, function ($gq) use ($meeting) {
                            return $gq->where('gender', $meeting->target_gender->value);
                        })
                        ->when($shouldFilterByAge, function ($aq) use ($meeting) {
                            return $aq->whereHas('ageGroup', function ($ageQ) use ($meeting) {
                                return $ageQ->whereIn('name', (array) $meeting->target_age_groups);
                            });
                        });
                })
                    ->orWhere(function ($pq) use ($meetingLineageIds) {
                        $pq->where(function ($spq) use ($meetingLineageIds) {
                            $spq->where('membership_type', MembershipType::PENGURUS->value)
                                ->whereIn('group_id', $meetingLineageIds);
                        })
                            ->orWhereHas('positions', function ($posQ) use ($meetingLineageIds) {
                                $posQ->whereIn('group_id', $meetingLineageIds);
                            });
                    });
            })
            ->orderBy('full_name')
            ->get()
            ->map(function ($m) use ($meeting, $targetGroupIds, $shouldFilterByAge) {
                $isTargetGender = ($meeting->target_gender === MeetingTargetGender::ALL || $m->gender === $meeting->target_gender->value);
                $isTargetAge = ! $shouldFilterByAge || ($m->ageGroup && in_array($m->ageGroup->name, (array) $meeting->target_age_groups));
                $isDirectTarget = in_array($m->group_id, $targetGroupIds) && $isTargetGender && $isTargetAge;

                $isSupervisor = $m->hasPositionIn($meeting->group);
                $badge = (! $isDirectTarget && $isSupervisor) ? MembershipType::PENGURUS->getLabel() : '';

                return [
                    'id' => $m->id,
                    'full_name' => $m->full_name,
                    'member_code' => $m->member_code,
                    'group' => $m->group?->name ?? '-',
                    'badge' => $badge,
                ];
            });

        return response()->json([
            'status' => 'success',
            'members' => $members,
        ]);
    }

    public function lookupMember(Request $request, Meeting $meeting)
    {
        if ($request->has('member_id')) {
            $member = Member::find($request->member_id);
        } else {
            $code = $request->code;
            $member = Member::where('member_code', $code)->first();
        }

        if (! $member) {
            return response()->json([
                'status' => 'error',
                'message' => $request->has('member_id') ? 'Data anggota tidak ditemukan.' : "Kode KTA '$code' tidak terdaftar.",
            ]);
        }

        $validation = $this->checkMemberEligibility($meeting, $member);
        if ($validation) {
            return $validation;
        }

        $missingFields = [];
        if (empty($member->gender)) {
            $missingFields[] = 'gender';
        }
        if (empty($member->birth_date)) {
            $missingFields[] = 'birth_date';
        }

        if (! empty($missingFields)) {
            return response()->json([
                'status' => 'incomplete_biodata',
                'message' => 'Biodata Anda belum lengkap. Silakan lengkapi terlebih dahulu sebelum mengajukan perizinan.',
                'missing_fields' => $missingFields,
                'member' => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'gender' => $member->gender,
                    'birth_date' => $member->birth_date ? (is_string($member->birth_date) ? $member->birth_date : $member->birth_date->format('Y-m-d')) : null,
                    'has_gender' => ! empty($member->gender),
                    'has_birth_date' => ! empty($member->birth_date),
                ],
            ]);
        }

        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('member_id', $member->id)
            ->first();

        return response()->json([
            'status' => 'success',
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'member_code' => $member->member_code,
                'group' => $member->group?->name,
                'photo_url' => $member->profile_photo_path ? \Illuminate\Support\Facades\Storage::url($member->profile_photo_path) : null,
            ],
            'already_submitted' => $existing ? true : false,
            'message' => $existing ? 'Anda sudah tercatat ('.strtoupper($existing->status?->value ?? '').')' : 'Data ditemukan',
        ]);
    }

    public function store(Request $request, Meeting $meeting)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'status' => 'required|in:izin,sakit',
            'notes' => 'nullable|string|max:500',
            'evidence' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:2500',
        ]);

        $member = Member::find($request->member_id);

        $validation = $this->checkMemberEligibility($meeting, $member);
        if ($validation) {
            return $validation;
        }

        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'warning',
                'message' => 'Anda sudah tercatat ('.strtoupper($existing->status?->value ?? '').').',
            ]);
        }

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('attendance-evidence', 'public');
        }

        try {
            Attendance::create([
                'meeting_id' => $meeting->id,
                'member_id' => $member->id,
                'checkin_time' => now(),
                'method' => AttendanceMethod::SELF,
                'status' => $request->status === 'sakit' ? AttendanceStatus::SAKIT : AttendanceStatus::IZIN,
                'evidence_path' => $evidencePath,
                'notes' => $request->notes,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Anda sudah tercatat perizinan sebelumnya.',
                    'name' => $member->full_name,
                ]);
            }
            throw $e;
        }

        $statusLabel = ucfirst(strtolower($request->status));

        return response()->json([
            'status' => 'success',
            'message' => "$statusLabel: {$member->full_name}",
            'name' => $member->full_name,
            'time' => now()->format('H:i'),
        ]);
    }

    public function registerTemporary(Request $request, Meeting $meeting)
    {
        if ($meeting->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi presensi/perizinan untuk pertemuan ini sudah berakhir.',
            ], 422);
        }

        if (! $meeting->is_permission_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Perizinan untuk pertemuan ini sedang ditutup.',
            ], 422);
        }

        $request->validate([
            'full_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'group_id' => 'required|exists:groups,id',
            'birth_date' => 'required|date|before:today',
        ]);

        $allowedGroupIds = $meeting->group->getAllDescendantIds();
        if (! in_array($request->group_id, $allowedGroupIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelompok yang dipilih tidak termasuk dalam cakupan pertemuan ini.',
            ], 422);
        }

        $member = Member::create([
            'full_name' => $request->full_name,
            'gender' => $request->gender,
            'group_id' => $request->group_id,
            'birth_date' => $request->birth_date,
            'is_permanent' => false,
            'status' => false,
            'member_code' => null,
        ]);

        $member->load(['group', 'ageGroup']);

        return response()->json([
            'status' => 'success',
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'member_code' => $member->member_code,
                'group' => $member->group?->name,
                'photo_url' => null,
            ],
        ]);
    }

    public function completeBiodata(Request $request, Meeting $meeting)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date|before:today',
        ]);

        $member = Member::findOrFail($request->member_id);
        $updateData = [];

        // Proteksi Immutability: Hanya perbarui field yang saat ini masih kosong di database
        if (empty($member->gender) && $request->filled('gender')) {
            $updateData['gender'] = $request->gender;
        }
        if (empty($member->birth_date) && $request->filled('birth_date')) {
            $updateData['birth_date'] = $request->birth_date;
        }

        if (! empty($updateData)) {
            $member->update($updateData);
        }

        $member->load(['group', 'ageGroup']);

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata berhasil dilengkapi.',
            'member' => [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'member_code' => $member->member_code,
                'group' => $member->group?->name,
                'photo_url' => $member->profile_photo_path ? \Illuminate\Support\Facades\Storage::url($member->profile_photo_path) : null,
            ],
        ]);
    }

    private function checkMemberEligibility(Meeting $meeting, Member $member)
    {
        if ($meeting->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi presensi/perizinan untuk pertemuan ini sudah berakhir.',
            ]);
        }

        if (! $meeting->is_permission_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Perizinan untuk pertemuan ini sedang ditutup.',
            ]);
        }

        if ($member->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => "Anggota {$member->full_name} sudah tidak aktif (dihapus).",
            ]);
        }

        $isSupervisor = $member->hasPositionIn($meeting->group);
        $canBypassFilters = $isSupervisor;

        if (! $canBypassFilters) {
            $allowedGroupIds = $meeting->group->getAllDescendantIds();
            if (! in_array($member->group_id, $allowedGroupIds)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "{$member->full_name} bukan anggota {$meeting->group->name} atau turunannya.",
                ]);
            }
        }

        if (! $canBypassFilters && $meeting->target_gender !== MeetingTargetGender::ALL && $member->gender !== $meeting->target_gender) {
            $targetLabel = $meeting->target_gender->getLabel();

            return response()->json([
                'status' => 'warning',
                'message' => "Pertemuan ini hanya untuk $targetLabel.",
            ]);
        }

        if (! $canBypassFilters && ! empty($meeting->target_age_groups)) {
            $allAgeGroupsCount = \App\Models\AgeGroup::count();
            $selectedAgeGroupsCount = count($meeting->target_age_groups);
            $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

            if ($shouldFilterByAge) {
                $memberAgeGroupName = $member->ageGroup?->name;
                if (! in_array($memberAgeGroupName, (array) $meeting->target_age_groups)) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => "Kategori usia ({$memberAgeGroupName}) tidak sesuai target pertemuan.",
                    ]);
                }
            }
        }

        return null;
    }
}
