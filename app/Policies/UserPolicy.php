<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser, User $user): bool
    {
        return $authUser->can('View:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(AuthUser $authUser, User $user): bool
    {
        // Pengguna non-Super Admin tidak boleh mengedit akun Super Admin
        if ($user->isSuperAdmin() && ! ($authUser instanceof User && $authUser->isSuperAdmin())) {
            return false;
        }

        return $authUser->can('Update:User');
    }

    public function delete(AuthUser $authUser, User $user): bool
    {
        // Pengguna dilarang menghapus akunnya sendiri yang sedang aktif
        if ($authUser->id === $user->id) {
            return false;
        }

        // Pengguna non-Super Admin tidak boleh menghapus akun Super Admin
        if ($user->isSuperAdmin() && ! ($authUser instanceof User && $authUser->isSuperAdmin())) {
            return false;
        }

        // Cegah menghapus satu-satunya akun Super Admin
        if ($user->isSuperAdmin() && User::role(config('filament-shield.super_admin.name', 'super_admin'))->count() <= 1) {
            return false;
        }

        return $authUser->can('Delete:User');
    }

    public function restore(AuthUser $authUser, User $user): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(AuthUser $authUser, User $user): bool
    {
        // Pengguna dilarang menghapus akunnya sendiri yang sedang aktif
        if ($authUser->id === $user->id) {
            return false;
        }

        // Pengguna non-Super Admin tidak boleh menghapus akun Super Admin
        if ($user->isSuperAdmin() && ! ($authUser instanceof User && $authUser->isSuperAdmin())) {
            return false;
        }

        // Cegah menghapus satu-satunya akun Super Admin
        if ($user->isSuperAdmin() && User::role(config('filament-shield.super_admin.name', 'super_admin'))->count() <= 1) {
            return false;
        }

        return $authUser->can('ForceDelete:User');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser, User $user): bool
    {
        // Pengguna non-Super Admin tidak boleh menduplikasi akun Super Admin
        if ($user->isSuperAdmin() && ! ($authUser instanceof User && $authUser->isSuperAdmin())) {
            return false;
        }

        return $authUser->can('Replicate:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}