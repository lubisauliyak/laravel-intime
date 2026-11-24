<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PositionCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PositionCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PositionCategory $positionCategory): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PositionCategory $positionCategory): bool
    {
        return false;
    }

    public function delete(User $user, PositionCategory $positionCategory): bool
    {
        return false;
    }

    public function restore(User $user, PositionCategory $positionCategory): bool
    {
        return false;
    }

    public function forceDelete(User $user, PositionCategory $positionCategory): bool
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

    public function replicate(User $user, PositionCategory $positionCategory): bool
    {
        return false;
    }
}
