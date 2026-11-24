<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\LogsActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, LogsActivity, Notifiable, SoftDeletes;

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return (bool) $this->status && $this->hasAnyRole([
                UserRole::SUPER_ADMIN,
                UserRole::ADMIN,
                UserRole::OPERATOR,
                UserRole::PENGURUS,
            ]);
        }

        return false;
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Auto-verify email so new users can login immediately
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'group_id',
        'role',
        'status',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => 'boolean',
        ];
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(UserRole|string $role): self
    {
        $this->role = $role instanceof UserRole ? $role : (UserRole::tryFrom((string) $role) ?? $role);
        $this->save();

        return $this;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(UserRole|string $role): bool
    {
        $roleValue = $role instanceof UserRole ? $role->value : $role;
        $currentRole = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        return $currentRole === $roleValue;
    }

    /**
     * Check if user has any of the given roles.
     *
     * @param  array<UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper to check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN);
    }

    /**
     * Helper check for admin role
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    /**
     * Helper check for operator role
     */
    public function isOperator(): bool
    {
        return $this->hasRole(UserRole::OPERATOR);
    }

    /**
     * Helper check for pengurus role
     */
    public function isPengurus(): bool
    {
        return $this->hasRole(UserRole::PENGURUS);
    }

    /**
     * Apakah pengguna memiliki akses manajemen (Super Admin atau Admin)
     */
    public function isManagement(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin();
    }

    /**
     * Memusatkan otoritas untuk fitur export
     */
    public function canExport(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->isPengurus();
    }

    /**
     * Check if the user can manage the given group (own group or any descendant).
     */
    public function canManageGroup(Group|int|null $group): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $group) {
            return false;
        }

        $groupId = $group instanceof Group ? $group->id : (int) $group;

        return in_array($groupId, $this->getManageableGroupIds(), true);
    }

    /**
     * Check if the user can access the given group's scope (manageable or ancestor branch).
     */
    public function canAccessGroupScope(Group|int|null $group): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $group) {
            return false;
        }

        $groupId = $group instanceof Group ? $group->id : (int) $group;

        if ($this->canManageGroup($groupId)) {
            return true;
        }

        if ($this->group_id && $this->group) {
            return in_array($groupId, $this->group->getAllAncestorIds(), true);
        }

        return false;
    }

    /**
     * Dapatkan daftar ID grup yang dapat dikelola oleh pengguna ini.
     * Jika Super Admin, mengembalikan seluruh ID grup.
     * Untuk role lain, mengembalikan ID grup sendiri dan seluruh grup turunannya.
     *
     * @return array<int>
     */
    public function getManageableGroupIds(): array
    {
        if (isset($this->_manageable_group_ids)) {
            return $this->_manageable_group_ids;
        }

        if ($this->isSuperAdmin()) {
            return $this->_manageable_group_ids = Group::pluck('id')->toArray();
        }

        if (! $this->group_id || ! $this->group) {
            return $this->_manageable_group_ids = [];
        }

        return $this->_manageable_group_ids = $this->group->getAllDescendantIds();
    }

    /**
     * Clear cached manageable group IDs in memory.
     */
    public function flushManageableGroupCache(): void
    {
        unset($this->_manageable_group_ids);
        $this->unsetRelation('group');
    }
}
