<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($group);
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function update(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($group);
    }

    public function delete(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->group_id === $group->id) {
            return false;
        }

        return $user->isAdmin() && $user->canManageGroup($group);
    }

    public function restore(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($group);
    }

    public function forceDelete(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->group_id === $group->id) {
            return false;
        }

        return $user->isAdmin() && $user->canManageGroup($group);
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

    public function replicate(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($group);
    }

    public function reorder(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function export(User $user, ?Group $group = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canExport() && (! $group || $user->canManageGroup($group));
    }

    public function import(User $user, ?Group $group = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && (! $group || $user->canManageGroup($group));
    }

    public function viewMeetingHistory(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($group);
    }

    public function viewMemberActivity(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($group);
    }

    public function openAttendanceDetails(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($group);
    }

    public function viewMemberMeetingHistory(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($group);
    }

    public function refreshMemberAge(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($group);
    }
}
