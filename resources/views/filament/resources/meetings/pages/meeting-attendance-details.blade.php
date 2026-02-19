<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->getSchema('infolist') }}
        
        @php
            $meeting = $this->meeting;
            $group = $this->group;
            $descendantIds = $group->getAllDescendantIds();
            
            // Target members query
            $targetQuery = \App\Models\Member::whereIn('group_id', $descendantIds)
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
            
            // Gender breakdown
            $maleTarget = (clone $targetQuery)->where('gender', 'male')->count();
            $femaleTarget = (clone $targetQuery)->where('gender', 'female')->count();
            
            $malePresent = \App\Models\Attendance::where('meeting_id', $meeting->id)
                ->whereIn('member_id', $targetMemberIds)
                ->whereHas('member', fn($q) => $q->where('gender', 'male'))
                ->where('status', 'hadir')
                ->count();
            $femalePresent = \App\Models\Attendance::where('meeting_id', $meeting->id)
                ->whereIn('member_id', $targetMemberIds)
                ->whereHas('member', fn($q) => $q->where('gender', 'female'))
                ->where('status', 'hadir')
                ->count();
            
            $maleIzinSakit = \App\Models\Attendance::where('meeting_id', $meeting->id)
                ->whereIn('member_id', $targetMemberIds)
                ->whereHas('member', fn($q) => $q->where('gender', 'male'))
                ->whereIn('status', ['izin', 'sakit'])
                ->count();
            $femaleIzinSakit = \App\Models\Attendance::where('meeting_id', $meeting->id)
                ->whereIn('member_id', $targetMemberIds)
                ->whereHas('member', fn($q) => $q->where('gender', 'female'))
                ->whereIn('status', ['izin', 'sakit'])
                ->count();
            
            $maleAbsent = max(0, $maleTarget - $malePresent - $maleIzinSakit);
            $femaleAbsent = max(0, $femaleTarget - $femalePresent - $femaleIzinSakit);
        @endphp
        
        {{-- Tabel Ringkasan Kehadiran per Gender --}}
        @if($meeting->target_gender !== 'all')
        <div class="fi-section">
            <div class="fi-section-header">
                <div class="fi-section-header-heading">
                    <h3 class="fi-section-header-heading-text text-base font-semibold text-gray-950 dark:text-white">
                        Ringkasan Kehadiran per Gender
                    </h3>
                </div>
            </div>
            <div class="fi-section-content">
                <div class="fi-table-container">
                    <table class="fi-table">
                        <thead>
                            <tr>
                                <th class="fi-table-header-cell">Gender</th>
                                <th class="fi-table-header-cell">Target</th>
                                <th class="fi-table-header-cell">Hadir</th>
                                <th class="fi-table-header-cell">Izin / Sakit</th>
                                <th class="fi-table-header-cell">{{ $this->isMeetingOver() ? 'Tidak Hadir' : 'Belum Scan' }}</th>
                                <th class="fi-table-header-cell">Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($meeting->target_gender === 'male' || $meeting->target_gender === 'all')
                            <tr>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--info-50); color: var(--info-700);">
                                        Laki-laki
                                    </span>
                                </td>
                                <td class="fi-table-cell">{{ $maleTarget }}</td>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--success-50); color: var(--success-700);">
                                        {{ $malePresent }}
                                    </span>
                                </td>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--warning-50); color: var(--warning-700);">
                                        {{ $maleIzinSakit }}
                                    </span>
                                </td>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--danger-50); color: var(--danger-700);">
                                        {{ $maleAbsent }}
                                    </span>
                                </td>
                                <td class="fi-table-cell">
                                    @php $malePercentage = $maleTarget > 0 ? round(($malePresent / $maleTarget) * 100, 1) : 0; @endphp
                                    <span class="font-medium">{{ $malePercentage }}%</span>
                                </td>
                            </tr>
                            @endif
                            @if($meeting->target_gender === 'female' || $meeting->target_gender === 'all')
                            <tr>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--danger-50); color: var(--danger-700);">
                                        Perempuan
                                    </span>
                                </td>
                                <td class="fi-table-cell">{{ $femaleTarget }}</td>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--success-50); color: var(--success-700);">
                                        {{ $femalePresent }}
                                    </span>
                                </td>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--warning-50); color: var(--warning-700);">
                                        {{ $femaleIzinSakit }}
                                    </span>
                                </td>
                                <td class="fi-table-cell">
                                    <span class="fi-badge" style="background-color: var(--danger-50); color: var(--danger-700);">
                                        {{ $femaleAbsent }}
                                    </span>
                                </td>
                                <td class="fi-table-cell">
                                    @php $femalePercentage = $femaleTarget > 0 ? round(($femalePresent / $femaleTarget) * 100, 1) : 0; @endphp
                                    <span class="font-medium">{{ $femalePercentage }}%</span>
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
        
        <div style="margin-top: 24px;">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
