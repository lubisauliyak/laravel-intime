<?php

namespace App\Filament\Widgets;

use App\Models\Meeting;
use Carbon\Carbon;

class TodayAttendanceTrend extends AttendanceTrend
{
    protected static ?int $sort = 3;

    protected ?Meeting $cachedTodayMeeting = null;

    public static function canView(): bool
    {
        return parent::canView() && Meeting::whereDate('meeting_date', Carbon::today())->exists();
    }

    protected function getMeetingRecord(): ?Meeting
    {
        if ($this->cachedTodayMeeting === null) {
            $this->cachedTodayMeeting = Meeting::whereDate('meeting_date', Carbon::today())
                ->latest('meeting_date')
                ->first();
        }

        return $this->cachedTodayMeeting;
    }

    protected function getData(): array
    {
        $meeting = $this->getMeetingRecord();
        if ($meeting) {
            $this->filters = [
                'meeting_id' => $meeting->id,
            ];
        }

        return parent::getData();
    }
}
