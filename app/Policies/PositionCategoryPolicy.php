<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PositionCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class PositionCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PositionCategory');
    }

    public function view(AuthUser $authUser, PositionCategory $positionCategory): bool
    {
        return $authUser->can('View:PositionCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PositionCategory');
    }

    public function update(AuthUser $authUser, PositionCategory $positionCategory): bool
    {
        return $authUser->can('Update:PositionCategory');
    }

    public function delete(AuthUser $authUser, PositionCategory $positionCategory): bool
    {
        return $authUser->can('Delete:PositionCategory');
    }

    public function restore(AuthUser $authUser, PositionCategory $positionCategory): bool
    {
        return $authUser->can('Restore:PositionCategory');
    }

    public function forceDelete(AuthUser $authUser, PositionCategory $positionCategory): bool
    {
        return $authUser->can('ForceDelete:PositionCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PositionCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PositionCategory');
    }

    public function replicate(AuthUser $authUser, PositionCategory $positionCategory): bool
    {
        return $authUser->can('Replicate:PositionCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PositionCategory');
    }

}