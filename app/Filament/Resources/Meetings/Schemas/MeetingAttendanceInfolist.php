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
        
        // Total members (all)
        $totalMembers = Member::whereIn('group_id', $descendantIds)->where('status', true)->count();
        
        // Target members (filtered by gender and age category)
        $targetQuery = Member::whereIn('group_id', $descendantIds)
            ->where('status', true)
            ->when($meeting->target_gender !== 'all', function ($q) use ($meeting) {
                return $q->where('gender', $meeting->target_gender);
            })
            ->when(!empty($meeting->target_age_groups), function ($q) use ($meeting) {
                return $q->whereHas('ageGroup', function ($aq) use ($meeting) {
                    return $aq->whereIn('name', $meeting->target_age_groups);
                });
            });
        
        $totalTarget = $targetQuery->count();
        $targetMemberIds = $targetQuery->pluck('id')->toArray();
        
        // Attendance counts for target members
        $present = Attendance::where('meeting_id', $meeting->id)
            ->whereIn('member_id', $targetMemberIds)
            ->where('status', 'hadir')
            ->count();
        $excusedSick = Attendance::where('meeting_id', $meeting->id)
            ->whereIn('member_id', $targetMemberIds)
            ->whereIn('status', ['izin', 'sakit'])
            ->count();
        $absent = max(0, $totalTarget - $present - $excusedSick);
        
        // Gender breakdown for target members
        $maleTarget = (clone $targetQuery)->where('gender', 'male')->count();
        $femaleTarget = (clone $targetQuery)->where('gender', 'female')->count();
        
        $malePresent = Attendance::where('meeting_id', $meeting->id)
            ->whereIn('member_id', $targetMemberIds)
            ->whereHas('member', fn($q) => $q->where('gender', 'male'))
            ->where('status', 'hadir')
            ->count();
        $femalePresent = Attendance::where('meeting_id', $meeting->id)
            ->whereIn('member_id', $targetMemberIds)
            ->whereHas('member', fn($q) => $q->where('gender', 'female'))
            ->where('status', 'hadir')
            ->count();
        
        // Age category breakdown for target members
        $ageCategoryStats = [];
        if (!empty($meeting->target_age_groups)) {
            $sortedNames = \App\Models\AgeGroup::whereIn('name', (array) $meeting->target_age_groups)
                ->orderBy('sort_order')
                ->pluck('name')
                ->toArray();

            foreach ($sortedNames as $ageGroupName) {
                $ageTarget = (clone $targetQuery)
                    ->whereHas('ageGroup', fn($q) => $q->where('name', $ageGroupName))
                    ->count();
                $agePresent = Attendance::where('meeting_id', $meeting->id)
                    ->whereIn('member_id', $targetMemberIds)
                    ->whereHas('member.ageGroup', fn($q) => $q->where('name', $ageGroupName))
                    ->where('status', 'hadir')
                    ->count();
                $ageCategoryStats[$ageGroupName] = [
                    'target' => $ageTarget,
                    'present' => $agePresent,
                ];
            }
        }

        return $schema
            ->state([
                'meeting_name' => $meeting->name,
                'meeting_date' => $meeting->meeting_date->translatedFormat('l, d F Y'),
                'meeting_group' => $meeting->group->name,
                'group_name' => $group->name,
                'target_gender' => $meeting->target_gender,
                'target_age_groups' => empty($meeting->target_age_groups) ? [] : \App\Models\AgeGroup::whereIn('name', (array) $meeting->target_age_groups)->orderBy('sort_order')->pluck('name')->toArray(),
                'total_target' => $totalTarget,
                'total_members' => $totalMembers,
                'present_count' => $present,
                'excused_sick_count' => $excusedSick,
                'absent_count' => $absent,
                'male_target' => $maleTarget,
                'female_target' => $femaleTarget,
                'male_present' => $malePresent,
                'female_present' => $femalePresent,
                'age_category_stats' => $ageCategoryStats,
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
                                TextEntry::make('target_gender')
                                    ->label('Target Gender')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'all' => 'Semua',
                                        'male' => 'Laki-laki',
                                        'female' => 'Perempuan',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        'male' => 'info',
                                        'female' => 'danger',
                                        default => 'gray',
                                    }),
                                 TextEntry::make('target_age_groups')
                                    ->label('Target Kategori Usia')
                                    ->placeholder('Semua')
                                    ->badge()
                                    ->color(fn (string $state): string => match (true) {
                                        str_contains(strtolower($state), 'pra remaja') => 'info',
                                        str_contains(strtolower($state), 'remaja') => 'warning',
                                        str_contains(strtolower($state), 'pra nikah') => 'success',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),
                Section::make('Ringkasan Kehadiran')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])
                             ->schema([
                                 TextEntry::make('total_target')
                                     ->label('Total Anggota')
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
