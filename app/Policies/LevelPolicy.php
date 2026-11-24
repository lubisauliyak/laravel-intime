<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Level;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LevelPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Level $level): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Level $level): bool
    {
        return false;
    }

    public function delete(User $user, Level $level): bool
    {
        return false;
    }

    public function restore(User $user, Level $level): bool
    {
        return false;
    }

    public function forceDelete(User $user, Level $level): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Level $level): bool
    {
        return false;
    }
}
