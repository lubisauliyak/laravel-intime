<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\UserRole;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\LogsActivity;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles, LogsActivity;

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            // Menggunakan permission check atau role check yang terpusat
            return $this->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
                UserRole::OPERATOR->value,
                UserRole::PENGURUS->value,
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
        'role', // Tetap dipertahankan sementara sebagai jembatan/info
        'status',
    ];

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
        ];
    }

    /**
     * Helper to check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', UserRole::SUPER_ADMIN->value));
    }

    /**
     * Helper check for admin role
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN->value);
    }

    /**
     * Helper check for operator role
     */
    public function isOperator(): bool
    {
        return $this->hasRole(UserRole::OPERATOR->value);
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
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->can('Export:Member')
            || $this->can('Export:Group')
            || $this->can('ExportExcel:Meeting')
            || $this->can('ExportPdf:Meeting');
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

        if (!$this->group_id || !$this->group) {
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
