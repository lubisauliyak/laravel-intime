<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Models\Member;
use App\Models\Attendance;
use App\Enums\MeetingTargetGender;
use App\Enums\Gender;
use App\Enums\AttendanceStatus;

class MeetingAttendanceInfolist
{
    public static function configure(Schema $schema, $meeting, $group, $isMeetingOver): Schema
    {
        $descendantIds = $group->getAllDescendantIds();

        // Total members (all registered in groups)
        $totalMembers = Member::whereIn('group_id', $descendantIds)->count();

        // Base Target members (status = true, filtered by gender and age category)
        $allAgeGroupsCount = \App\Models\AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;

        $baseTargetQuery = Member::whereIn('group_id', $descendantIds)
            ->where('status', true)
            ->when($meeting->target_gender !== MeetingTargetGender::ALL, function ($q) use ($meeting) {
                return $q->where('gender', $meeting->target_gender->value);
            })
            ->when($shouldFilterByAge, function ($q) use ($meeting) {
                return $q->whereHas('ageGroup', function ($aq) use ($meeting) {
                    return $aq->whereIn('name', (array) $meeting->target_age_groups);
                });
            });

        $baseTargetMemberIds = $baseTargetQuery->pluck('id')->toArray();
        $baseTargetCount = count($baseTargetMemberIds);

        // Fetch all attendances for this meeting within descendant groups
        $attendances = Attendance::with('member.ageGroup')
            ->where('meeting_id', $meeting->id)
            ->whereHas('member', fn($q) => $q->whereIn('group_id', $descendantIds))
            ->whereIn('status', [
                \App\Enums\AttendanceStatus::HADIR,
                \App\Enums\AttendanceStatus::IZIN,
                \App\Enums\AttendanceStatus::SAKIT,
            ])
            ->get();

        // Non-target attendances: members who attended/excused but not in base target
        $nonTargetAttendances = $attendances->reject(fn($att) => in_array($att->member_id, $baseTargetMemberIds));

        // Total Target Dinamis = Base Target + Non-Target yang hadir/izin/sakit
        $totalTarget = $baseTargetCount + $nonTargetAttendances->count();

        // Attendance counts (all valid attendees in meeting)
        $present = $attendances->where('status', \App\Enums\AttendanceStatus::HADIR)->count();
        $excusedSick = $attendances->whereIn('status', [
            \App\Enums\AttendanceStatus::IZIN,
            \App\Enums\AttendanceStatus::SAKIT,
        ])->count();

        // Absent (Alpa) = hanya dari target dasar yang belum hadir & belum izin/sakit (Non-target tidak kena alpa)
        $targetPresent = $attendances->whereIn('member_id', $baseTargetMemberIds)
            ->where('status', \App\Enums\AttendanceStatus::HADIR)
            ->count();
        $targetExcusedSick = $attendances->whereIn('member_id', $baseTargetMemberIds)
            ->whereIn('status', [
                \App\Enums\AttendanceStatus::IZIN,
                \App\Enums\AttendanceStatus::SAKIT,
            ])
            ->count();
        $absent = max(0, $baseTargetCount - $targetPresent - $targetExcusedSick);

        // Gender breakdown
        $baseMaleTarget = (clone $baseTargetQuery)->where('gender', Gender::MALE->value)->count();
        $baseFemaleTarget = (clone $baseTargetQuery)->where('gender', Gender::FEMALE->value)->count();

        $nonTargetMale = $nonTargetAttendances->filter(fn($a) => ($a->member?->gender instanceof Gender ? $a->member->gender->value : $a->member?->gender) === Gender::MALE->value)->count();
        $nonTargetFemale = $nonTargetAttendances->filter(fn($a) => ($a->member?->gender instanceof Gender ? $a->member->gender->value : $a->member?->gender) === Gender::FEMALE->value)->count();

        $maleTarget = $baseMaleTarget + $nonTargetMale;
        $femaleTarget = $baseFemaleTarget + $nonTargetFemale;

        $malePresent = $attendances->where('status', \App\Enums\AttendanceStatus::HADIR)
            ->filter(fn($a) => ($a->member?->gender instanceof Gender ? $a->member->gender->value : $a->member?->gender) === Gender::MALE->value)
            ->count();
        $femalePresent = $attendances->where('status', \App\Enums\AttendanceStatus::HADIR)
            ->filter(fn($a) => ($a->member?->gender instanceof Gender ? $a->member->gender->value : $a->member?->gender) === Gender::FEMALE->value)
            ->count();

        // Age category breakdown
        $ageCategoryStats = [];
        if (!empty($meeting->target_age_groups)) {
            $sortedNames = \App\Models\AgeGroup::whereIn('name', (array) $meeting->target_age_groups)
                ->orderBy('sort_order')
                ->pluck('name')
                ->toArray();

            foreach ($sortedNames as $ageGroupName) {
                $baseAgeTarget = (clone $baseTargetQuery)
                    ->whereHas('ageGroup', fn($q) => $q->where('name', $ageGroupName))
                    ->count();
                $nonTargetAge = $nonTargetAttendances->filter(fn($a) => $a->member?->ageGroup?->name === $ageGroupName)->count();

                $agePresent = $attendances->where('status', \App\Enums\AttendanceStatus::HADIR)
                    ->filter(fn($a) => $a->member?->ageGroup?->name === $ageGroupName)
                    ->count();

                $ageCategoryStats[$ageGroupName] = [
                    'target' => $baseAgeTarget + $nonTargetAge,
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
                                    ->formatStateUsing(fn ($state): ?string => $state instanceof \App\Enums\MeetingTargetGender ? $state->getLabel() : $state)
                                    ->color(fn ($state): ?string => $state instanceof \App\Enums\MeetingTargetGender ? $state->getColor() : 'gray'),
                                  TextEntry::make('target_age_groups')
                                    ->label('Target Kategori Usia')
                                    ->formatStateUsing(fn ($state) => empty($state) ? '✓ Semua Kategori Usia' : $state)
                                    ->badge()
                                    ->color(fn ($state): string => match (true) {
                                        empty($state) || $state === '✓ Semua Kategori Usia' => 'success',
                                        default => \App\Models\AgeGroup::getColorMapping()[(string)$state] ?? 'gray',
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
                                     ->label(AttendanceStatus::HADIR->getLabel())
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
