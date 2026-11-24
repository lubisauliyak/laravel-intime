<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MemberPosition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MemberPositionPolicy
{
    use HandlesAuthorization;

    private function getPositionGroupId(MemberPosition $memberPosition): ?int
    {
        return $memberPosition->group_id ?? $memberPosition->member?->group_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MemberPosition $memberPosition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageGroup($this->getPositionGroupId($memberPosition));
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR]);
    }

    public function update(User $user, MemberPosition $memberPosition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyRole([UserRole::ADMIN, UserRole::OPERATOR])
            && $user->canManageGroup($this->getPositionGroupId($memberPosition));
    }

    public function delete(User $user, MemberPosition $memberPosition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($this->getPositionGroupId($memberPosition));
    }

    public function restore(User $user, MemberPosition $memberPosition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($this->getPositionGroupId($memberPosition));
    }

    public function forceDelete(User $user, MemberPosition $memberPosition): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->canManageGroup($this->getPositionGroupId($memberPosition));
    }
}
