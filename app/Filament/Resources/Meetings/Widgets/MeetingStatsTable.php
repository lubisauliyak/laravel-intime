<?php

namespace App\Filament\Resources\Meetings\Widgets;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Member;
use App\Models\Meeting;
use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MeetingStatsTable extends TableWidget
{
    public ?Meeting $record = null;
    
    public $parentId = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Statistik per Grup Turunan';

    public function table(Table $table): Table
    {
        $meeting = $this->record;

        if (!$meeting) {
            return $table->query(fn() => Group::whereRaw('1 = 0'));
        }

        return $table
            ->query(function () use ($meeting) {
                // Jika sedang melihat sub-grup (setelah klik 'Lihat Sub-Grup')
                if ($this->parentId) {
                    return Group::where('parent_id', $this->parentId);
                }

                // Cek apakah grup penyelenggara punya anak
                if ($meeting->group->children()->count() === 0) {
                    // Jika tidak punya anak (level paling bawah), tampilkan dirinya sendiri
                    return Group::where('id', $meeting->group_id);
                }

                // Jika punya anak, tampilkan anak-anaknya (perilaku default)
                return $meeting->group->children();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Grup')
                    ->weight('bold')
                    ->searchable(),
                
                TextColumn::make('level.name')
                    ->label('Level')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subgroup_count')
                    ->label('Sub-Grup')
                    ->alignCenter()
                    ->getStateUsing(fn (Group $record) => $record->children()->count() . ' grup'),

                TextColumn::make('members_count')
                    ->label('Total Anggota')
                    ->alignCenter()
                    ->getStateUsing(function (Group $record) {
                        $descendantIds = $record->getAllDescendantIds();
                        return Member::whereIn('group_id', $descendantIds)->where('status', true)->count();
                    }),

                TextColumn::make('present_count')
                    ->label('Hadir')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        $memberIds = Member::whereIn('group_id', $descendantIds)->pluck('id');
                        
                        return Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('member_id', $memberIds)
                            ->where('status', 'hadir')
                            ->count();
                    }),

                TextColumn::make('excused_count')
                    ->label('Izin/Sakit')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        $memberIds = Member::whereIn('group_id', $descendantIds)->pluck('id');
                        
                        return Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('member_id', $memberIds)
                            ->whereIn('status', ['izin', 'sakit'])
                            ->count();
                    }),

                TextColumn::make('attendance_percentage')
                    ->label('% Hadir')
                    ->alignRight()
                    ->getStateUsing(function (Group $record) use ($meeting) {
                        $descendantIds = $record->getAllDescendantIds();
                        $memberIds = Member::whereIn('group_id', $descendantIds)->pluck('id');
                        $totalMembers = $memberIds->count();
                        
                        if ($totalMembers === 0) return '0%';

                        $isPresent = Attendance::where('meeting_id', $meeting->id)
                            ->whereIn('member_id', $memberIds)
                            ->where('status', 'hadir')
                            ->count();

                        return round(($isPresent / $totalMembers) * 100, 1) . '%';
                    })
                    ->weight('bold')
                    ->color(fn ($state) => floatval($state) >= 75 ? 'success' : (floatval($state) >= 50 ? 'warning' : 'danger')),
            ])
            ->headerActions([
                Action::make('back_to_main')
                    ->label('Kembali ke Atas')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn () => $this->parentId !== null)
                    ->action(fn () => $this->parentId = null),
            ])
            ->actions([
                Action::make('view_members')
                    ->label('Lihat Nama')
                    ->icon('heroicon-m-users')
                    ->color('info')
                    ->url(fn (Group $record) => MeetingResource::getUrl('attendance-details', [
                        'record' => $meeting->id,
                    ]) . "?group={$record->id}"),
                Action::make('view_subgroups')
                    ->label('Lihat Sub-Grup')
                    ->icon('heroicon-m-chevron-right')
                    ->color('primary')
                    ->visible(fn (Group $record) => $record->children()->exists())
                    ->action(fn (Group $record) => $this->parentId = $record->id),
            ])
            ->paginated(false);
    }
}
