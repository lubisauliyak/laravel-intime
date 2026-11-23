<?php

namespace App\Exports;

use App\Models\Meeting;
use App\Exports\Sheets\MeetingAttendanceSummarySheet;
use App\Exports\Sheets\MeetingAttendanceDetailSheet;
use App\Exports\Sheets\MeetingPengurusDetailSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MeetingAttendanceExport implements WithMultipleSheets
{
    use Exportable;

    protected $meeting;
    protected $memberIds;

    public function __construct($meetingId, $memberIds = null)
    {
        $this->meeting = Meeting::findOrFail($meetingId);
        $this->memberIds = $memberIds;
    }

    public function sheets(): array
    {
        return [
            new MeetingAttendanceSummarySheet($this->meeting, $this->memberIds),
            new MeetingAttendanceDetailSheet($this->meeting, $this->memberIds),
            new MeetingPengurusDetailSheet($this->meeting, $this->memberIds),
        ];
    }
}
