<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MeetingReportController extends Controller
{
    public function pdf(Meeting $meeting)
    {
        $meeting->load(['attendances.member', 'group', 'creator']);
        
        $pdf = Pdf::loadView('reports.meeting-pdf', compact('meeting'));
        
        return $pdf->stream("Laporan-Kehadiran-{$meeting->name}.pdf");
    }
}
