<?php

namespace App\Filament\Widgets;

use App\Models\Meeting;
use Carbon\Carbon;

class TodayAttendanceOverview extends AttendanceOverview
{
    protected static ?int $sort = 2;

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

    protected function getStats(): array
    {
        $meeting = $this->getMeetingRecord();
        if ($meeting) {
            $this->filters = [
                'meeting_id' => $meeting->id,
                'group_id' => $meeting->group_id,
                'level_id' => $meeting->group ? $meeting->group->level_id : null,
            ];
        }

        return parent::getStats();
    }
}
