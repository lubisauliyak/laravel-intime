<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'member_code',
        'full_name',
        'nick_name',
        'group_id',
        'birth_date',
        'age',
        'age_group_id',
        'gender',
        'status',
        'membership_type',
        'qr_code_path',
    ];

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function ageGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function positions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberPosition::class);
    }

    public function isPengurus(): bool
    {
        return strcasecmp($this->membership_type, 'pengurus') === 0 || $this->positions()->exists();
    }

    /**
     * Check if member has a position in a specific group or its ancestors.
     */
    public function hasPositionIn(Group $group): bool
    {
        if (!$this->isPengurus()) return false;

        $lineageIds = array_unique(array_merge(
            [$group->id],
            $group->getAllAncestorIds(),
            $group->getAllDescendantIds()
        ));

        // 1. Direct check: Is their primary group in the meeting lineage?
        if (strcasecmp($this->membership_type, 'pengurus') === 0 && in_array($this->group_id, $lineageIds)) {
            return true;
        }

        // 2. Position check: Do they have a specific position in the meeting lineage?
        return $this->positions()
            ->whereIn('group_id', $lineageIds)
            ->exists();
    }

    /**
     * Get the highest-priority position for this member.
     * Priority: Category sort_order ASC, then Group Level level_number ASC.
     */
    public function getPrimaryPosition()
    {
        return $this->positions()
            ->with(['category', 'group.level'])
            ->get()
            ->sortBy(fn($pos) => [
                $pos->category?->sort_order ?? 999,
                -($pos->group?->level?->level_number ?? 0),
            ])
            ->first();
    }
}
