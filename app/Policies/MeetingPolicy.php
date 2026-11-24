<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($meeting->group_id);
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR]);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($meeting->group_id);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($meeting->group_id);
    }

    public function restore(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($meeting->group_id);
    }

    public function forceDelete(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($meeting->group_id);
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

    public function replicate(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($meeting->group_id);
    }

    public function reorder(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin();
    }

    public function export(User $user, ?Meeting $meeting = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canExport() && (! $meeting || $user->canAccessGroupScope($meeting->group_id));
    }

    public function viewStats(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($meeting->group_id);
    }

    public function viewAttendanceDetails(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($meeting->group_id);
    }

    public function manageSelfService(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($meeting->group_id);
    }

    public function editSelfService(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($meeting->group_id);
    }

    public function openScanner(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canAccessGroupScope($meeting->group_id);
    }

    public function exportExcel(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canExport() && $user->canAccessGroupScope($meeting->group_id);
    }

    public function exportPdf(User $user, Meeting $meeting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canExport() && $user->canAccessGroupScope($meeting->group_id);
    }
}
