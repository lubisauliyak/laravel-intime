<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogTable;
use App\Models\ActivityLog;
use App\Models\Member;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

class MemberActivityLog extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithRecord;

    protected static string $resource = MemberResource::class;

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
        return 'Riwayat Aktivitas Anggota';
    }

    public function table(Table $table): Table
    {
        return ActivityLogTable::configure($table)
            ->query(
                ActivityLog::query()
                    ->where('subject_type', Member::class)
                    ->where('subject_id', $this->record->id)
            );
    }
}
