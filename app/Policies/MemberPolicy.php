<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Member;
use Illuminate\Auth\Access\HandlesAuthorization;

class MemberPolicy
{
    use HandlesAuthorization;

    private function canManageMember(AuthUser $authUser, Member $member): bool
    {
        if (! $authUser instanceof \App\Models\User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        if ($member->group) {
            return $member->group->canBeManagedBy($authUser);
        }

        return false;
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Member');
    }

    public function view(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('View:Member') && $this->canManageMember($authUser, $member);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Member');
    }

    public function update(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('Update:Member') && $this->canManageMember($authUser, $member);
    }

    public function delete(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('Delete:Member') && $this->canManageMember($authUser, $member);
    }

    public function restore(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('Restore:Member') && $this->canManageMember($authUser, $member);
    }

    public function forceDelete(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('ForceDelete:Member') && $this->canManageMember($authUser, $member);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Member');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Member');
    }

    public function replicate(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('Replicate:Member') && $this->canManageMember($authUser, $member);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Member');
    }

    public function export(AuthUser $authUser, ?Member $member = null): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        if ($member && ! $this->canManageMember($authUser, $member)) {
            return false;
        }

        return $authUser->can('Export:Member');
    }

    public function import(AuthUser $authUser, ?Member $member = null): bool
    {
        if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->can('Import:Member');
    }

    public function viewMeetingHistory(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('ViewMeetingHistory:Member') && $this->canManageMember($authUser, $member);
    }

    public function generateCard(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('GenerateCard:Member') && $this->canManageMember($authUser, $member);
    }

    public function makePermanent(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('MakePermanent:Member') && $this->canManageMember($authUser, $member);
    }

    public function deactivate(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('Deactivate:Member') && $this->canManageMember($authUser, $member);
    }

    public function downloadKta(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('DownloadKta:Member') && $this->canManageMember($authUser, $member);
    }

    public function downloadQr(AuthUser $authUser, Member $member): bool
    {
        return $authUser->can('DownloadQr:Member') && $this->canManageMember($authUser, $member);
    }

}