<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ActivityLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        if ($authUser instanceof User && ($authUser->isSuperAdmin() || $authUser->isAdmin())) {
            return true;
        }

        return $authUser->can('ViewAny:ActivityLog');
    }

    public function view(AuthUser $authUser, ActivityLog $activityLog): bool
    {
        if (! $authUser instanceof User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        if ($authUser->isAdmin()) {
            $manageableIds = $authUser->getManageableGroupIds();
            return empty($activityLog->group_id) || in_array($activityLog->group_id, $manageableIds);
        }

        return $authUser->can('View:ActivityLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return false; // Audit trail imutabel (tidak dapat dibuat manual)
    }

    public function update(AuthUser $authUser, ActivityLog $activityLog): bool
    {
        return false; // Audit trail imutabel (tidak dapat diubah)
    }

    public function delete(AuthUser $authUser, ActivityLog $activityLog): bool
    {
        return false; // Log individual tidak dapat dihapus
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function prune(AuthUser $authUser): bool
    {
        if ($authUser instanceof User) {
            return $authUser->isSuperAdmin();
        }

        return false;
    }

    public function export(AuthUser $authUser): bool
    {
        if ($authUser instanceof User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('Export:ActivityLog');
    }
}
