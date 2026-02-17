<?php

namespace App\Exports;

use App\Models\Meeting;
use App\Exports\Sheets\MeetingAttendanceSummarySheet;
use App\Exports\Sheets\MeetingAttendanceDetailSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MeetingAttendanceExport implements WithMultipleSheets
{
    use Exportable;

    protected $meeting;

    public function __construct($meetingId)
    {
        $this->meeting = Meeting::findOrFail($meetingId);
    }

    public function sheets(): array
    {
        return [
            new MeetingAttendanceSummarySheet($this->meeting),
            new MeetingAttendanceDetailSheet($this->meeting),
        ];
    }
}
