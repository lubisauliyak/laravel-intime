<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

class Group extends Model
{
    use SoftDeletes, LogsActivity;
    protected $fillable = [
        'uid',
        'parent_id',
        'level_id',
        'name',
        'code',
        'card_template_id',
        'status',
    ];

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = (string) Str::ulid();
            }
        });

        static::saving(function ($group) {
            if ($group->name) {
                $group->name = strtoupper($group->name);
            }
        });

        static::saved(function () {
            static::$_descendant_cache = [];
            static::$_ancestor_cache = [];
            static::$_ancestor_tree = null;
            static::$_children_tree = null;
            \Illuminate\Support\Facades\Cache::flush();
        });
        static::deleted(function () {
            static::$_descendant_cache = [];
            static::$_ancestor_cache = [];
            static::$_ancestor_tree = null;
            static::$_children_tree = null;
            \Illuminate\Support\Facades\Cache::flush();
        });
        static::restored(function () {
            static::$_descendant_cache = [];
            static::$_ancestor_cache = [];
            static::$_ancestor_tree = null;
            static::$_children_tree = null;
            \Illuminate\Support\Facades\Cache::flush();
        });
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

    public function cardTemplate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CardTemplate::class, 'card_template_id');
    }

    public function positions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberPosition::class)->orderBy('sort_order');
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
    protected static array $_ancestor_cache = [];
    protected static ?array $_ancestor_tree = null;
    protected static ?array $_children_tree = null;

    protected static function warmHierarchyTree(): void
    {
        if (static::$_ancestor_tree === null) {
            $pairs = static::query()->pluck('parent_id', 'id')->toArray();
            static::$_ancestor_tree = $pairs;
            $children = [];
            foreach ($pairs as $childId => $parentId) {
                if ($parentId) {
                    $children[$parentId][] = $childId;
                }
            }
            static::$_children_tree = $children;
        }
    }

    /**
     * Get IDs of this group and all its descendants.
     */
    public function getAllDescendantIds(): array
    {
        if (isset(static::$_descendant_cache[$this->id])) {
            return static::$_descendant_cache[$this->id];
        }

        static::warmHierarchyTree();

        $collectDescendants = function ($groupId) use (&$collectDescendants) {
            $ids = [$groupId];
            if (isset(static::$_children_tree[$groupId])) {
                foreach (static::$_children_tree[$groupId] as $childId) {
                    $ids = array_merge($ids, $collectDescendants($childId));
                }
            }
            return $ids;
        };

        return static::$_descendant_cache[$this->id] = $collectDescendants($this->id);
    }

    /**
     * Get IDs of all ancestor groups.
     */
    public function getAllAncestorIds(): array
    {
        if (isset(static::$_ancestor_cache[$this->id])) {
            return static::$_ancestor_cache[$this->id];
        }

        static::warmHierarchyTree();

        $ids = [];
        $currentId = static::$_ancestor_tree[$this->id] ?? null;

        while ($currentId) {
            $ids[] = $currentId;
            $currentId = static::$_ancestor_tree[$currentId] ?? null;
        }

        return static::$_ancestor_cache[$this->id] = $ids;
    }

    /**
     * Check if this group can be managed by a specific user.
     */
    public function canBeManagedBy(\App\Models\User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$user->group_id || !$user->group) {
            return false;
        }

        // Cache descendant IDs in the user object to avoid repeated DB queries during table rendering
        if (!isset($user->_descendant_group_ids)) {
            $user->_descendant_group_ids = $user->group->getAllDescendantIds();
        }

        return in_array($this->id, $user->_descendant_group_ids);
    }

    /**
     * Get the topmost root ancestor group in this hierarchy branch.
     */
    public function getRootAncestor(): Group
    {
        $curr = $this;
        while ($curr->parent_id && $curr->parent) {
            $curr = $curr->parent;
        }
        return $curr;
    }

    /**
     * Check if a group with the given name and level already exists in the same scope.
     */
    public static function isNameDuplicateInScope(string $name, ?int $levelId, ?int $parentId, ?int $ignoreId = null, ?\App\Models\User $user = null): bool
    {
        if (empty(trim($name)) || !$levelId) {
            return false;
        }

        $user = $user ?? auth()->user();
        $nameUpper = strtoupper(trim($name));

        // 1. Tentukan ID grup dalam scope
        $scopeGroupIds = null;
        if ($user && !$user->isSuperAdmin() && $user->group_id) {
            $scopeGroupIds = $user->getManageableGroupIds();
        } elseif ($parentId) {
            $parent = static::find($parentId);
            if ($parent) {
                $root = $parent->getRootAncestor();
                $scopeGroupIds = $root->getAllDescendantIds();
            }
        }

        // 2. Query pengecekan duplikasi nama pada level yang sama
        $query = static::whereRaw('UPPER(name) = ?', [$nameUpper])
            ->where('level_id', $levelId);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($scopeGroupIds !== null) {
            $query->whereIn('id', $scopeGroupIds);
        } else {
            // Jika grup tingkat teratas (parent_id null)
            if (!$parentId) {
                $query->whereNull('parent_id');
            }
        }

        return $query->exists();
    }
}

