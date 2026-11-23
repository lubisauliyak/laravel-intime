<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    private function canManageAttendance(AuthUser $authUser, Attendance $attendance): bool
    {
        if (! $authUser instanceof User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Attendance terikat pada Meeting dan Group
        if ($attendance->meeting && $attendance->meeting->group) {
            return $attendance->meeting->group->canBeManagedBy($authUser);
        }

        // Fallback bila meeting belum loaded, periksa member group
        if ($attendance->member && $attendance->member->group) {
            return $attendance->member->group->canBeManagedBy($authUser);
        }

        return false;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Attendance') || $authUser->can('ViewAttendanceDetails:Meeting');
    }

    public function view(AuthUser $authUser, Attendance $attendance): bool
    {
        return ($authUser->can('View:Attendance') || $authUser->can('ViewAttendanceDetails:Meeting'))
            && $this->canManageAttendance($authUser, $attendance);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Attendance') || $authUser->can('Update:Meeting');
    }

    public function update(AuthUser $authUser, Attendance $attendance): bool
    {
        return ($authUser->can('Update:Attendance') || $authUser->can('Update:Meeting'))
            && $this->canManageAttendance($authUser, $attendance);
    }

    public function delete(AuthUser $authUser, Attendance $attendance): bool
    {
        return ($authUser->can('Delete:Attendance') || $authUser->can('Update:Meeting'))
            && $this->canManageAttendance($authUser, $attendance);
    }

    public function setExcusedAttendance(AuthUser $authUser, Attendance $attendance): bool
    {
        return ($authUser->can('SetExcusedAttendance') || $authUser->can('Update:Attendance'))
            && $this->canManageAttendance($authUser, $attendance);
    }
}
