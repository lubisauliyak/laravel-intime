<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Member;
use App\Models\Attendance;
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
            
        return view('scanner.live', compact('meeting', 'attendances'));
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
        if ($validation) return $validation;

        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'warning',
                'message' => "{$member->full_name} sudah diabsen jam " . ($existing->checkin_time?->format('H:i') ?? '-')
            ]);
        }

        $isLate = now()->greaterThan($meeting->meeting_date->setTimeFrom($meeting->start_time));
        $notes = $isLate ? 'TERLAMBAT' : null;

        $isGlobalPengurus = strcasecmp($member->membership_type, 'pengurus') === 0;
        $isLineagePengurus = $member->hasPositionIn($meeting->group);
        $isTargetAge = empty($meeting->target_age_groups) || ($member->ageGroup && in_array($member->ageGroup->name, $meeting->target_age_groups));

        Attendance::create([
            'meeting_id' => $meeting->id,
            'member_id' => $member->id,
            'checkin_time' => now(),
            'method' => 'qr_code',
            'status' => 'hadir',
            'notes' => $notes,
        ]);

        $label = ($isGlobalPengurus || $isLineagePengurus) && !$isTargetAge ? " [PENGURUS]" : "";
        $message = "Berhasil absen: {$member->full_name}{$label}";
        if ($isLate) {
            $message .= " (TERLAMBAT)";
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'name' => $member->full_name,
            'time' => now()->format('H:i'),
            'is_late' => $isLate
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
        if ($meeting->meeting_date->setTimeFrom($meeting->end_time)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => "Sesi presensi untuk pertemuan ini sudah berakhir."
            ]);
        }

        // 4. Validation: Member status
        if (!$member->status) {
            return response()->json([
                'status' => 'error',
                'message' => "Anggota {$member->full_name} sudah tidak aktif."
            ]);
        }

        // 5. Check if Member is Pengurus (Lineage or Global)
        $isGlobalPengurus = strcasecmp($member->membership_type, 'pengurus') === 0;
        $isPengurusLineage = $member->hasPositionIn($meeting->group);
        $canBypassFilters = $isGlobalPengurus || $isPengurusLineage;

        // 6. Validation: Group Hierarchy (Bypassed by Pengurus)
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
        if (!$canBypassFilters && $meeting->target_gender !== 'all' && $member->gender !== $meeting->target_gender) {
            $targetLabel = $meeting->target_gender === 'male' ? 'Laki-laki' : 'Perempuan';
            return response()->json([
                'status' => 'warning',
                'message' => "Pertemuan ini hanya untuk $targetLabel."
            ]);
        }

        // 8. Validation: Target Age Groups (Bypassed by Pengurus)
        if (!$canBypassFilters && !empty($meeting->target_age_groups)) {
            $memberAgeGroupName = $member->ageGroup?->name;
            if (!in_array($memberAgeGroupName, $meeting->target_age_groups)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => "Kategori usia ({$memberAgeGroupName}) tidak sesuai target pertemuan."
                ]);
            }
        }

        return null; // OK
    }


    public function search(Request $request, Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting, 'manage');
        $query = $request->q;
        
        $allowedGroupIds = $meeting->group->getAllDescendantIds();
        $lineageGroupIds = array_merge($allowedGroupIds, $meeting->group->getAllAncestorIds());

        $members = Member::with('ageGroup')->where(function($q) use ($query) {
                $q->where('full_name', 'like', "%$query%")
                  ->orWhere('member_code', 'like', "%$query%");
            })
            ->where('status', true)
            ->where(function($q) use ($allowedGroupIds, $lineageGroupIds, $meeting) {
                // Option A: Regular members who match meeting filters (Gender & Age)
                $q->where(function ($sq) use ($allowedGroupIds, $meeting) {
                    $sq->whereIn('group_id', $allowedGroupIds)
                        ->when($meeting->target_gender !== 'all', function ($gq) use ($meeting) {
                            return $gq->where('gender', $meeting->target_gender);
                        })
                        ->when(!empty($meeting->target_age_groups), function ($aq) use ($meeting) {
                            return $aq->whereHas('ageGroup', function ($ageQ) use ($meeting) {
                                return $ageQ->whereIn('name', $meeting->target_age_groups);
                            });
                        });
                })
                // Option B: Anyone who is a Pengurus (Global or in Lineage)
                ->orWhere(function ($pq) use ($lineageGroupIds) {
                    $pq->whereIn('membership_type', ['pengurus', 'PENGURUS'])
                        ->orWhereHas('positions', function ($posQ) use ($lineageGroupIds) {
                            $posQ->whereIn('group_id', $lineageGroupIds);
                        });
                });
            })
            ->limit(10)
            ->get()
            ->map(function($m) use ($meeting) {
                $isPengurus = $m->isPengurus();
                $isTargetAge = false;

                if (!empty($meeting->target_age_groups)) {
                    $isTargetAge = $m->ageGroup && in_array($m->ageGroup->name, $meeting->target_age_groups);
                }

                // Hide label if they are target age group, OR if they are not pengurus
                $label = ($isPengurus && !$isTargetAge) ? " [PENGURUS]" : "";
                
                return [
                    'id' => $m->id,
                    'text' => "{$m->full_name} ({$m->member_code}){$label}"
                ];
            });

        return response()->json($members);
    }

    public function manualStore(Request $request, Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting, 'manage');
        $memberId = $request->member_id;
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['status' => 'error', 'message' => 'Anggota tidak ditemukan.']);
        }

        $validation = $this->checkMemberEligibility($meeting, $member);
        if ($validation) return $validation;

        $status = $request->status ?? 'hadir';

        if (in_array($status, ['izin', 'sakit']) && !auth()->user()->can('SetExcusedAttendance')) {
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
            if ($existing->status === $status) {
                return response()->json([
                    'status' => 'warning', 
                    'message' => "{$member->full_name} sudah tercatat " . strtoupper($existing->status) . "."
                ]);
            }

            // Allow status update (e.g., from hadir to izin/sakit)
            $oldStatus = strtoupper($existing->status);
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
        if ($status === 'hadir') {
            $isLate = now()->greaterThan($meeting->meeting_date->setTimeFrom($meeting->start_time));
        }
        $finalNotes = $request->notes ?? ($isLate ? 'TERLAMBAT' : null);

        Attendance::create([
            'meeting_id' => $meeting->id,
            'member_id' => $member->id,
            'checkin_time' => now(),
            'method' => 'manual',
            'status' => $status,
            'evidence_path' => $evidencePath,
            'notes' => $finalNotes,
        ]);

        $isGlobalPengurus = strcasecmp($member->membership_type, 'pengurus') === 0;
        $isLineagePengurus = $member->hasPositionIn($meeting->group);
        $isTargetAge = empty($meeting->target_age_groups) || ($member->ageGroup && in_array($member->ageGroup->name, (array) $meeting->target_age_groups));
        $label = ($isGlobalPengurus || $isLineagePengurus) && !$isTargetAge ? " [PENGURUS]" : "";

        $statusLabel = strtoupper($status);
        $message = "Berhasil ($statusLabel): {$member->full_name}{$label}";
        if ($isLate) $message .= " (TERLAMBAT)";

        return response()->json(['status' => 'success', 'message' => $message]);
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

        if (!$user->can('View:ScanAttendance')) {
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
            ? 'Hanya penyelenggara (atau atasan langsung) yang dapat melakukan presensi.' 
            : 'Anda tidak memiliki akses ke pertemuan ini.');
    }
}
