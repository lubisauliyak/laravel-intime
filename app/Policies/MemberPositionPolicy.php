<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MemberPosition;
use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class MemberPositionPolicy
{
    use HandlesAuthorization;

    private function canManageMemberPosition(AuthUser $authUser, MemberPosition $memberPosition): bool
    {
        if (! $authUser instanceof User) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Posisi terikat pada grup posisi
        if ($memberPosition->group) {
            return $memberPosition->group->canBeManagedBy($authUser);
        }

        // Fallback: periksa grup anggota pemilik posisi
        if ($memberPosition->member && $memberPosition->member->group) {
            return $memberPosition->member->group->canBeManagedBy($authUser);
        }

        return false;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MemberPosition') || $authUser->can('View:Member');
    }

    public function view(AuthUser $authUser, MemberPosition $memberPosition): bool
    {
        return ($authUser->can('View:MemberPosition') || $authUser->can('View:Member'))
            && $this->canManageMemberPosition($authUser, $memberPosition);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MemberPosition') || $authUser->can('Update:Member');
    }

    public function update(AuthUser $authUser, MemberPosition $memberPosition): bool
    {
        return ($authUser->can('Update:MemberPosition') || $authUser->can('Update:Member'))
            && $this->canManageMemberPosition($authUser, $memberPosition);
    }

    public function delete(AuthUser $authUser, MemberPosition $memberPosition): bool
    {
        return ($authUser->can('Delete:MemberPosition') || $authUser->can('Update:Member'))
            && $this->canManageMemberPosition($authUser, $memberPosition);
    }
}
