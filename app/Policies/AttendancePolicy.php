<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    private function getAttendanceGroupId(Attendance $attendance): ?int
    {
        return $attendance->meeting?->group_id ?? $attendance->member?->group_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canAccessGroupScope($this->getAttendanceGroupId($attendance));
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR]);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($this->getAttendanceGroupId($attendance));
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($this->getAttendanceGroupId($attendance));
    }

    public function restore(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($this->getAttendanceGroupId($attendance));
    }

    public function forceDelete(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($this->getAttendanceGroupId($attendance));
    }

    public function scanAttendance(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR]);
    }
}
