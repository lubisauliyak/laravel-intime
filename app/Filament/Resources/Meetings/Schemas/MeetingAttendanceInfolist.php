<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use App\Models\Member;
use App\Models\Attendance;

class MeetingAttendanceInfolist
{
    public static function configure(Schema $schema, $meeting, $group, $isMeetingOver): Schema
    {
        $descendantIds = $group->getAllDescendantIds();
        $totalMembers = Member::whereIn('group_id', $descendantIds)->where('status', true)->count();
        $memberIds = Member::whereIn('group_id', $descendantIds)->pluck('id');
        $present = Attendance::where('meeting_id', $meeting->id)
            ->whereIn('member_id', $memberIds)
            ->where('status', 'hadir')
            ->count();
        $excusedSick = Attendance::where('meeting_id', $meeting->id)
            ->whereIn('member_id', $memberIds)
            ->whereIn('status', ['izin', 'sakit'])
            ->count();

        return $schema
            ->state([
                'meeting_name' => $meeting->name,
                'meeting_date' => $meeting->meeting_date->translatedFormat('l, d F Y'),
                'meeting_group' => $meeting->group->name,
                'group_name' => $group->name,
                'total_members' => $totalMembers,
                'present_count' => $present,
                'excused_sick_count' => $excusedSick,
                'absent_count' => $totalMembers - ($present + $excusedSick),
            ])
            ->components([
                Section::make('Informasi Pertemuan')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('meeting_name')
                                    ->label('Nama Pertemuan')
                                    ->weight('bold'),
                                TextEntry::make('meeting_date')
                                    ->label('Tanggal Pelaksanaan'),
                                TextEntry::make('meeting_group')
                                    ->label('Penyelenggara'),
                                TextEntry::make('group_name')
                                    ->label('Grup yang Dilihat')
                                    ->hidden(fn ($state, $get) => $state === $get('meeting_group')),
                            ]),
                    ]),
                Section::make('Ringkasan Kehadiran Grup')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])
                             ->schema([
                                 TextEntry::make('total_members')
                                     ->label('Total Seluruh Anggota')
                                     ->badge()
                                     ->color('gray'),
                                 TextEntry::make('present_count')
                                     ->label('Hadir')
                                     ->badge()
                                     ->color('success'),
                                TextEntry::make('excused_sick_count')
                                    ->label('Izin / Sakit')
                                    ->badge()
                                    ->color('warning'),
                                 TextEntry::make('absent_count')
                                     ->label($isMeetingOver ? 'Tidak Hadir' : 'Belum Scan')
                                     ->badge()
                                     ->color('danger'),
                             ]),
                    ])
                    ->compact(),
            ]);
    }
}
