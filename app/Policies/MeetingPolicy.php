<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Meeting;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    private function canManageMeeting(AuthUser $authUser, Meeting $meeting): bool
    {
        if (! $authUser instanceof \App\Models\User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        if ($meeting->group) {
            return $meeting->group->canBeManagedBy($authUser);
        }

        return false;
    }

    private function canAccessMeetingScope(AuthUser $authUser, Meeting $meeting): bool
    {
        if (! $authUser instanceof \App\Models\User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        if ($this->canManageMeeting($authUser, $meeting)) {
            return true;
        }

        if ($authUser->group_id && $meeting->group) {
            $meetingDescendantIds = $meeting->group->getAllDescendantIds();
            if (in_array($authUser->group_id, $meetingDescendantIds)) {
                return true;
            }
        }

        return false;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Meeting');
    }

    public function view(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('View:Meeting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Meeting');
    }

    public function update(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('Update:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function delete(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('Delete:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function restore(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('Restore:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function forceDelete(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('ForceDelete:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Meeting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Meeting');
    }

    public function replicate(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('Replicate:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Meeting');
    }

    public function export(AuthUser $authUser, ?Meeting $meeting = null): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        if ($meeting && ! $this->canAccessMeetingScope($authUser, $meeting)) {
            return false;
        }

        return $authUser->can('Export:Meeting') || $authUser->can('ExportExcel:Meeting');
    }

    public function viewStats(AuthUser $authUser, Meeting $meeting): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('ViewStats:Meeting') && $this->canAccessMeetingScope($authUser, $meeting);
    }

    public function viewAttendanceDetails(AuthUser $authUser, Meeting $meeting): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('ViewAttendanceDetails:Meeting') && $this->canAccessMeetingScope($authUser, $meeting);
    }

    public function manageSelfService(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('ManageSelfService:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function editSelfService(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('EditSelfService:Meeting') && $this->canManageMeeting($authUser, $meeting);
    }

    public function openScanner(AuthUser $authUser, Meeting $meeting): bool
    {
        return $authUser->can('OpenScanner:Meeting');
    }

    public function exportExcel(AuthUser $authUser, Meeting $meeting): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('ExportExcel:Meeting') && $this->canAccessMeetingScope($authUser, $meeting);
    }

    public function exportPdf(AuthUser $authUser, Meeting $meeting): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('ExportPdf:Meeting') && $this->canAccessMeetingScope($authUser, $meeting);
    }

}