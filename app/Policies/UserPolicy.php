<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, User $targetUser): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($targetUser->isSuperAdmin()) {
            return false;
        }

        if ($user->isOperator()) {
            return $user->id === $targetUser->id;
        }

        return $user->isAdmin()
            && ($user->id === $targetUser->id || $user->canManageGroup($targetUser->group_id) || ! $targetUser->group_id);
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, User $targetUser): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($targetUser->isSuperAdmin()) {
            return false;
        }

        if ($user->isOperator()) {
            return $user->id === $targetUser->id;
        }

        return $user->isAdmin()
            && ($user->id === $targetUser->id || $user->canManageGroup($targetUser->group_id) || ! $targetUser->group_id);
    }

    public function delete(User $user, User $targetUser): bool
    {
        // Pengguna dilarang menghapus akunnya sendiri
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Non-super admin dilarang menghapus super admin
        if ($targetUser->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        // Cegah menghapus satu-satunya akun super admin
        if ($targetUser->isSuperAdmin() && User::where('role', UserRole::SUPER_ADMIN)->count() <= 1) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($targetUser->group_id);
    }

    public function restore(User $user, User $targetUser): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($targetUser->isSuperAdmin()) {
            return false;
        }

        return $user->isAdmin() && $user->canManageGroup($targetUser->group_id);
    }

    public function forceDelete(User $user, User $targetUser): bool
    {
        // Pengguna dilarang menghapus akunnya sendiri
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Non-super admin dilarang menghapus super admin
        if ($targetUser->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        // Cegah menghapus satu-satunya akun super admin
        if ($targetUser->isSuperAdmin() && User::where('role', UserRole::SUPER_ADMIN)->count() <= 1) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($targetUser->group_id);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function replicate(User $user, User $targetUser): bool
    {
        if ($targetUser->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($targetUser->group_id);
    }
}
