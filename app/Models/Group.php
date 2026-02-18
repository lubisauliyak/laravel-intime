<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'parent_id',
        'level_id',
        'name',
        'status',
    ];

    protected static function booted()
    {
        static::saving(function ($group) {
            if ($group->name) {
                $group->name = strtoupper($group->name);
            }
        });

        static::saved(fn() => \Illuminate\Support\Facades\Cache::flush());
        static::deleted(fn() => \Illuminate\Support\Facades\Cache::flush());
    }

    public function level(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }

    public function meetings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function getFullNameAttribute(): string
    {
        $code = $this->level?->code ?? 'N/A';
        return "({$code}) {$this->name}";
    }

    /**
     * Get the parent group at a specific level number.
     */
    public function getParentAtLevel(int $levelNumber): ?Group
    {
        if ($this->level?->level_number === $levelNumber) {
            return $this;
        }

        if (! $this->parent) {
            return null;
        }

        return $this->parent->getParentAtLevel($levelNumber);
    }

    protected static array $_descendant_cache = [];

    /**
     * Get IDs of this group and all its descendants.
     */
    public function getAllDescendantIds(): array
    {
        if (isset(static::$_descendant_cache[$this->id])) {
            return static::$_descendant_cache[$this->id];
        }

        $cacheKey = "group_descendants_{$this->id}";
        
        return static::$_descendant_cache[$this->id] = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () {
            $ids = [$this->id];
            
            foreach ($this->children as $child) {
                $ids = array_merge($ids, $child->getAllDescendantIds());
            }
            
            return $ids;
        });
    }

    /**
     * Get IDs of all ancestor groups.
     */
    public function getAllAncestorIds(): array
    {
        $ids = [];
        $current = $this->parent;
        
        while ($current) {
            $ids[] = $current->id;
            $current = $current->parent;
        }
        
        return $ids;
    }

    /**
     * Check if this group can be managed by a specific user.
     */
    public function canBeManagedBy(\App\Models\User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$user->group_id) {
            return false;
        }

        // Cache descendant IDs in the user object to avoid repeated DB queries during table rendering
        if (!isset($user->_descendant_group_ids)) {
            $user->_descendant_group_ids = $user->group->getAllDescendantIds();
        }

        return in_array($this->id, $user->_descendant_group_ids);
    }
}
