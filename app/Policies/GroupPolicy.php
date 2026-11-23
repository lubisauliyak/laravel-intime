<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Group;
use Illuminate\Auth\Access\HandlesAuthorization;

class GroupPolicy
{
    use HandlesAuthorization;

    private function canManageGroup(AuthUser $authUser, Group $group): bool
    {
        if (! $authUser instanceof \App\Models\User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $group->canBeManagedBy($authUser);
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Group');
    }

    public function view(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('View:Group');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Group');
    }

    public function update(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('Update:Group') && $this->canManageGroup($authUser, $group);
    }

    public function delete(AuthUser $authUser, Group $group): bool
    {
        if ($authUser instanceof \App\Models\User && ! $authUser->isSuperAdmin() && $authUser->group_id === $group->id) {
            return false;
        }

        return $authUser->can('Delete:Group') && $this->canManageGroup($authUser, $group);
    }

    public function restore(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('Restore:Group') && $this->canManageGroup($authUser, $group);
    }

    public function forceDelete(AuthUser $authUser, Group $group): bool
    {
        if ($authUser instanceof \App\Models\User && ! $authUser->isSuperAdmin() && $authUser->group_id === $group->id) {
            return false;
        }

        return $authUser->can('ForceDelete:Group') && $this->canManageGroup($authUser, $group);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Group');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Group');
    }

    public function replicate(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('Replicate:Group') && $this->canManageGroup($authUser, $group);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Group');
    }

    public function export(AuthUser $authUser, ?Group $group = null): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        if ($group && ! $this->canManageGroup($authUser, $group)) {
            return false;
        }

        return $authUser->can('Export:Group');
    }

    public function import(AuthUser $authUser, ?Group $group = null): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('Import:Group');
    }

    public function viewMeetingHistory(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('ViewMeetingHistory:Group') && $this->canManageGroup($authUser, $group);
    }

    public function viewMemberActivity(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('ViewMemberActivity:Group') && $this->canManageGroup($authUser, $group);
    }

    public function openAttendanceDetails(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('OpenAttendanceDetails:Group') && $this->canManageGroup($authUser, $group);
    }

    public function viewMemberMeetingHistory(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('ViewMemberMeetingHistory:Group') && $this->canManageGroup($authUser, $group);
    }

    public function refreshMemberAge(AuthUser $authUser, Group $group): bool
    {
        return $authUser->can('RefreshMemberAge:Group') && $this->canManageGroup($authUser, $group);
    }
}