<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Meeting;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Meeting');
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->can('View:Meeting');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Meeting');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if (!$user->can('Update:Meeting')) {
            return false;
        }

        return $meeting->group?->canBeManagedBy($user) ?? false;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if (!$user->can('Delete:Meeting')) {
            return false;
        }

        return $meeting->group?->canBeManagedBy($user) ?? false;
    }

    public function restore(User $user, Meeting $meeting): bool
    {
        if (!$user->can('Restore:Meeting')) {
            return false;
        }

        return $meeting->group?->canBeManagedBy($user) ?? false;
    }

    public function forceDelete(User $user, Meeting $meeting): bool
    {
        if (!$user->can('ForceDelete:Meeting')) {
            return false;
        }

        return $meeting->group?->canBeManagedBy($user) ?? false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Meeting');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Meeting');
    }

    public function replicate(User $user, Meeting $meeting): bool
    {
        if (!$user->can('Replicate:Meeting')) {
            return false;
        }

        return $meeting->group?->canBeManagedBy($user) ?? false;
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Meeting');
    }

    public function export(User $user, Meeting $meeting): bool
    {
        return $user->can('Export:Meeting');
    }

}