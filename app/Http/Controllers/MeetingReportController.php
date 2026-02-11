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

        $meeting->load(['attendances.member', 'group', 'creator']);
        
        $pdf = Pdf::loadView('reports.meeting-pdf', compact('meeting'));
        
        return $pdf->stream("Laporan-Kehadiran-{$meeting->name}.pdf");
    }

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
            abort(403, 'Anda tidak memiliki akses ke laporan pertemuan ini.');
        }
    }
}
