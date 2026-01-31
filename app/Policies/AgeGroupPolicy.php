<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AgeGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class AgeGroupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AgeGroup');
    }

    public function view(AuthUser $authUser, AgeGroup $ageGroup): bool
    {
        return $authUser->can('View:AgeGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AgeGroup');
    }

    public function update(AuthUser $authUser, AgeGroup $ageGroup): bool
    {
        return $authUser->can('Update:AgeGroup');
    }

    public function delete(AuthUser $authUser, AgeGroup $ageGroup): bool
    {
        return $authUser->can('Delete:AgeGroup');
    }

    public function restore(AuthUser $authUser, AgeGroup $ageGroup): bool
    {
        return $authUser->can('Restore:AgeGroup');
    }

    public function forceDelete(AuthUser $authUser, AgeGroup $ageGroup): bool
    {
        return $authUser->can('ForceDelete:AgeGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AgeGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AgeGroup');
    }

    public function replicate(AuthUser $authUser, AgeGroup $ageGroup): bool
    {
        return $authUser->can('Replicate:AgeGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AgeGroup');
    }

}