<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MemberPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageGroup($member->group_id);
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR]);
    }

    public function update(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }

    public function delete(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($member->group_id);
    }

    public function restore(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($member->group_id);
    }

    public function forceDelete(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($member->group_id);
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function replicate(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }

    public function reorder(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function export(User $user, ?Member $member = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canExport() && (! $member || $user->canManageGroup($member->group_id));
    }

    public function import(User $user, ?Member $member = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && (! $member || $user->canManageGroup($member->group_id));
    }

    public function viewMeetingHistory(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageGroup($member->group_id);
    }

    public function generateCard(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }

    public function makePermanent(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }

    public function deactivate(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }

    public function downloadKta(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }

    public function downloadQr(User $user, Member $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($member->group_id);
    }
}
