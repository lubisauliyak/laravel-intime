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
        $this->authorizeGroupAccess($meeting);
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

        Attendance::create([
            'meeting_id' => $meeting->id,
            'member_id' => $member->id,
            'checkin_time' => now(),
            'method' => 'qr_code',
            'status' => 'hadir',
            'notes' => $notes,
        ]);

        $message = "Berhasil absen: {$member->full_name}";
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

        // 2. Validation: Meeting session has ended
        if ($meeting->meeting_date->setTimeFrom($meeting->end_time)->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => "Sesi presensi untuk pertemuan ini sudah berakhir."
            ]);
        }

        // 3. Validation: Member status
        if (!$member->status) {
            return response()->json([
                'status' => 'error',
                'message' => "Anggota {$member->full_name} sudah tidak aktif."
            ]);
        }

        // 4. Validation: Group Hierarchy (Member must belong to meeting's group or its descendants)
        $allowedGroupIds = $meeting->group->getAllDescendantIds();
        if (!in_array($member->group_id, $allowedGroupIds)) {
            return response()->json([
                'status' => 'warning',
                'message' => "{$member->full_name} bukan anggota {$meeting->group->name} atau turunannya."
            ]);
        }

        // 5. Validation: Target Gender
        if ($meeting->target_gender !== 'all' && $member->gender !== $meeting->target_gender) {
            $targetLabel = $meeting->target_gender === 'male' ? 'Laki-laki' : 'Perempuan';
            return response()->json([
                'status' => 'warning',
                'message' => "Pertemuan ini hanya untuk $targetLabel."
            ]);
        }

        // 6. Validation: Target Age Groups
        if (!empty($meeting->target_age_groups)) {
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
        $this->authorizeGroupAccess($meeting);
        $query = $request->q;
        
        // Only return members that belong to the meeting's group or its descendants
        $allowedGroupIds = $meeting->group->getAllDescendantIds();
        
        $members = Member::where(function($q) use ($query) {
                $q->where('full_name', 'like', "%$query%")
                  ->orWhere('member_code', 'like', "%$query%");
            })
            ->whereIn('group_id', $allowedGroupIds)
            ->where('status', true) // Only active members
            ->limit(10)
            ->get()
            ->map(function($m) {
                return [
                    'id' => $m->id,
                    'text' => "{$m->full_name} ({$m->member_code})"
                ];
            });

        return response()->json($members);
    }

    public function manualStore(Request $request, Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting);
        $memberId = $request->member_id;
        $member = Member::find($memberId);

        if (!$member) {
            return response()->json(['status' => 'error', 'message' => 'Anggota tidak ditemukan.']);
        }

        $validation = $this->checkMemberEligibility($meeting, $member);
        if ($validation) return $validation;

        $status = $request->status ?? 'hadir';
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

        $statusLabel = strtoupper($status);
        $message = "Berhasil ($statusLabel): {$member->full_name}";
        if ($isLate) $message .= " (TERLAMBAT)";

        return response()->json(['status' => 'success', 'message' => $message]);
    }

    /**
     * Validate that the authenticated user has group hierarchy access to the given meeting.
     * Super Admin bypasses this check. Admin/Operator must belong to the meeting's group or its ancestors.
     */
    private function authorizeGroupAccess(Meeting $meeting): void
    {
        $user = auth()->user();

        if (!$user || $user->hasRole('super_admin')) {
            return;
        }

        if (!$user->group_id) {
            abort(403, 'Akun Anda belum ditempatkan di grup manapun.');
        }

        $allowedGroupIds = $user->group->getAllDescendantIds();

        if (!in_array($meeting->group_id, $allowedGroupIds)) {
            abort(403, 'Anda tidak memiliki akses ke pertemuan ini.');
        }
    }
}
