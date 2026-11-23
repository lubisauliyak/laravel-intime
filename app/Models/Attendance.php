<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Attendance extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceFactory> */
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Hanya catat event updated dan deleted untuk mencegah lonjakan I/O saat absensi massal.
     */
    public function getLoggedEvents(): array
    {
        return ['updated', 'deleted'];
    }

    protected $fillable = [
        'meeting_id',
        'member_id',
        'checkin_time',
        'method',
        'status',
        'notes',
        'evidence_path',
    ];

    protected $casts = [
        'checkin_time' => 'datetime',
        'method' => \App\Enums\AttendanceMethod::class,
        'status' => \App\Enums\AttendanceStatus::class,
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Scope for attendances that match meeting target age groups.
     */
    public function scopeTargetOnly($query, Meeting $meeting)
    {
        return $query->whereHas('member', function ($q) use ($meeting) {
            $ageGroupIds = \App\Models\AgeGroup::whereIn('name', $meeting->target_age_groups ?? [])->pluck('id');
            $q->whereIn('age_group_id', $ageGroupIds);
        });
    }

    /**
     * Scope for attendances by members with 'pengurus' membership type.
     */
    public function scopePengurusOnly($query)
    {
        return $query->whereHas('member', function ($q) {
            $q->where('membership_type', \App\Enums\MembershipType::PENGURUS->value);
        });
    }
    /**
     * Check if the member should have a supervisor badge for this meeting.
     */
    public function getIsSupervisorBadgeAttribute(): bool
    {
        $member = $this->member;
        $meeting = $this->meeting;

        if (!$member || !$meeting) return false;

        $isSupervisor = $member->hasPositionIn($meeting->group);
        
        // Determine if they are a direct target
        $targetGroupIds = $meeting->group->getAllDescendantIds();
        $isTargetGroup = in_array($member->group_id, $targetGroupIds);
        $isTargetGender = ($meeting->target_gender === \App\Enums\MeetingTargetGender::ALL || $member->gender === $meeting->target_gender);
        
        $allAgeGroupsCount = \App\Models\AgeGroup::count();
        $selectedAgeGroupsCount = empty($meeting->target_age_groups) ? 0 : count($meeting->target_age_groups);
        $shouldFilterByAge = $selectedAgeGroupsCount > 0 && $selectedAgeGroupsCount < $allAgeGroupsCount;
        $isTargetAge = !$shouldFilterByAge || ($member->ageGroup && in_array($member->ageGroup->name, (array) $meeting->target_age_groups));
        
        $isDirectTarget = $isTargetGroup && $isTargetGender && $isTargetAge;

        return $isSupervisor && !$isDirectTarget;
    }
}
