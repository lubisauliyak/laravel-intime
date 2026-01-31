<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Level;
use Illuminate\Auth\Access\HandlesAuthorization;

class LevelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Level');
    }

    public function view(AuthUser $authUser, Level $level): bool
    {
        return $authUser->can('View:Level');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Level');
    }

    public function update(AuthUser $authUser, Level $level): bool
    {
        return $authUser->can('Update:Level');
    }

    public function delete(AuthUser $authUser, Level $level): bool
    {
        return $authUser->can('Delete:Level');
    }

    public function restore(AuthUser $authUser, Level $level): bool
    {
        return $authUser->can('Restore:Level');
    }

    public function forceDelete(AuthUser $authUser, Level $level): bool
    {
        return $authUser->can('ForceDelete:Level');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Level');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Level');
    }

    public function replicate(AuthUser $authUser, Level $level): bool
    {
        return $authUser->can('Replicate:Level');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Level');
    }

}