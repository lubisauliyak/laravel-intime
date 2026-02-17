<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MeetingReportController extends Controller
{
    public function pdf(Meeting $meeting)
    {
        $this->authorizeGroupAccess($meeting);

        $meeting->load(['attendances.member.group', 'group', 'creator']);
        
        $filename = "Laporan-Kehadiran {$meeting->name}-{$meeting->group->name}-" . $meeting->meeting_date->format('Y-m-d') . ".pdf";
        
        $pdf = Pdf::loadView('reports.meeting-pdf', compact('meeting'));
        
        return $pdf->stream($filename);
    }

    private function authorizeGroupAccess(Meeting $meeting): void
    {
        $user = auth()->user();

        if (!$user || $user->isSuperAdmin()) {
            return;
        }

        if (!$user->group_id) {
            abort(403, 'Akun Anda belum ditempatkan di grup manapun.');
        }

        $descendantIds = $user->group->getAllDescendantIds();
        $ancestorIds = $user->group->getAllAncestorIds();
        $allowedGroupIds = array_unique(array_merge($descendantIds, $ancestorIds));

        if (!in_array($meeting->group_id, $allowedGroupIds)) {
            abort(403, 'Anda tidak memiliki akses ke laporan pertemuan ini.');
        }
    }
}
