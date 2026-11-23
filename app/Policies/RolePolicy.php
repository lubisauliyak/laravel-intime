<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('View:Role');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Role');
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        // Hanya Super Admin yang boleh mengubah role super_admin
        if ($role->name === config('filament-shield.super_admin.name', 'super_admin')) {
            return $authUser instanceof User && $authUser->isSuperAdmin();
        }

        return $authUser->can('Update:Role');
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        // Role super_admin mutlak tidak boleh dihapus
        if ($role->name === config('filament-shield.super_admin.name', 'super_admin')) {
            return false;
        }

        return $authUser->can('Delete:Role');
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Restore:Role');
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        // Role super_admin mutlak tidak boleh dihapus
        if ($role->name === config('filament-shield.super_admin.name', 'super_admin')) {
            return false;
        }

        return $authUser->can('ForceDelete:Role');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Replicate:Role');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Role');
    }

}