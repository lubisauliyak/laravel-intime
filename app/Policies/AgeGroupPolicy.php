<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AgeGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AgeGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AgeGroup $ageGroup): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AgeGroup $ageGroup): bool
    {
        return false;
    }

    public function delete(User $user, AgeGroup $ageGroup): bool
    {
        return false;
    }

    public function restore(User $user, AgeGroup $ageGroup): bool
    {
        return false;
    }

    public function forceDelete(User $user, AgeGroup $ageGroup): bool
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

    public function replicate(User $user, AgeGroup $ageGroup): bool
    {
        return false;
    }
}
