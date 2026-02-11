<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MeetingAttendanceExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $meetingId;

    public function __construct($meetingId)
    {
        $this->meetingId = $meetingId;
    }

    public function query()
    {
        return Attendance::query()
            ->where('meeting_id', $this->meetingId)
            ->with(['member', 'member.group']);
    }

    public function headings(): array
    {
        return [
            'ID Anggota',
            'Nama Lengkap',
            'Grup',
            'Status',
            'Waktu Absen',
            'Metode',
            'Keterangan',
        ];
    }

    public function map($attendance): array
    {
        return [
            $attendance->member->member_code,
            $attendance->member->full_name,
            $attendance->member->group->name,
            strtoupper($attendance->status),
            $attendance->checkin_time ? $attendance->checkin_time->format('Y-m-d H:i:s') : '-',
            strtoupper($attendance->method),
            $attendance->notes ?? '-',
        ];
    }
}
