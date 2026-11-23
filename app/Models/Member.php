<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

/**
 * @property string $member_code
 * @property string $full_name
 * @property \App\Enums\Gender $gender
 * @property \App\Enums\MembershipType $membership_type
 * @property bool $status
 * @property bool $is_permanent
 * @property \App\Models\AgeGroup|null $ageGroup
 */
class Member extends Model
{
    use SoftDeletes, LogsActivity;
    protected $fillable = [
        'uid',
        'member_code',
        'full_name',
        'nick_name',
        'group_id',
        'birth_date',
        'age',
        'age_group_id',
        'gender',
        'status',
        'is_permanent',
        'membership_type',
        'qr_code_path',
        'profile_photo_path',
        'membership_card_photo_path',
    ];

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::ulid();
            }
        });

        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('group_direct_member_counts_map');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('group_direct_member_counts_map');
        });
    }

    protected $casts = [
        'status' => 'boolean',
        'is_permanent' => 'boolean',
        'gender' => \App\Enums\Gender::class,
        'membership_type' => \App\Enums\MembershipType::class,
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
        return $this->hasMany(MemberPosition::class)->orderBy('sort_order');
    }

    public function isPengurus(): bool
    {
        $isTypePengurus = ($this->membership_type instanceof \App\Enums\MembershipType)
            ? $this->membership_type === \App\Enums\MembershipType::PENGURUS
            : ($this->membership_type === \App\Enums\MembershipType::PENGURUS->value || $this->membership_type === 'pengurus');

        return $isTypePengurus || ($this->relationLoaded('positions') ? $this->positions->isNotEmpty() : $this->positions()->exists());
    }

    public function getMemberColor(): ?string
    {
        if ($this->trashed()) {
            return 'danger';
        }

        if (!$this->is_permanent || !$this->status) {
            return 'warning';
        }

        if ($this->isPengurus()) {
            return 'info';
        }

        return null;
    }

    public function getMemberColorTooltip(): ?string
    {
        $details = [];

        if ($this->trashed()) {
            $details[] = 'Dihapus';
        }

        if ($this->isPengurus()) {
            if ($this->positions->isNotEmpty()) {
                $this->positions->loadMissing(['category', 'group']);
                $positionsText = $this->positions->map(function ($pos) {
                    $parts = array_filter([
                        $pos->position_name,
                        $pos->category?->name,
                        $pos->group?->name,
                    ]);
                    return Str::title(strtolower(implode(' ', $parts)));
                })->filter()->unique()->implode(', ');

                if ($positionsText) {
                    $details[] = $positionsText;
                }
            } else {
                $groupName = $this->group?->name ? Str::title(strtolower($this->group->name)) : null;
                $details[] = $groupName ? "Pengurus {$groupName}" : 'Pengurus';
            }
        }

        if (!$this->status) {
            $details[] = 'Kurang Aktif';
        }

        if (!$this->is_permanent) {
            $details[] = 'Menunggu Verifikasi';
        }

        return !empty($details) ? implode(' • ', $details) : null;
    }

    /**
     * Check if member has a position in a specific group or its ancestors.
     */
    public function hasPositionIn(Group $group): bool
    {
        if (!$this->isPengurus())
            return false;

        $lineageIds = array_unique(array_merge(
            [$group->id],
            $group->getAllAncestorIds()
        ));

        // 1. Direct check: Is their primary group in the meeting lineage?
        if ($this->membership_type === \App\Enums\MembershipType::PENGURUS && in_array($this->group_id, $lineageIds)) {
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
                $pos->sort_order ?? 0,
            ])
            ->first();
    }
}
