<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogTable;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Meeting;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

class MeetingActivityLog extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = MeetingResource::class;

    protected string $view = 'filament.resources.activity-logs.pages.record-activity-log';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function getNavigationLabel(): string
    {
        return 'Riwayat Aktivitas';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-clock';
    }

    public function getTitle(): string
    {
        return 'Riwayat Aktivitas Pertemuan';
    }

    public function table(Table $table): Table
    {
        $meetingId = $this->record->id;
        $attendanceIds = $this->record->attendances()->pluck('id')->toArray();

        return ActivityLogTable::configure($table)
            ->query(
                ActivityLog::query()->where(function ($q) use ($meetingId, $attendanceIds) {
                    $q->where(function ($sub) use ($meetingId) {
                        $sub->where('subject_type', Meeting::class)
                            ->where('subject_id', $meetingId);
                    });

                    if (!empty($attendanceIds)) {
                        $q->orWhere(function ($sub) use ($attendanceIds) {
                            $sub->where('subject_type', Attendance::class)
                                ->whereIn('subject_id', $attendanceIds);
                        });
                    }
                })
            );
    }
}
