<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CardTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CardTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CardTemplate $cardTemplate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CardTemplate $cardTemplate): bool
    {
        return false;
    }

    public function delete(User $user, CardTemplate $cardTemplate): bool
    {
        return false;
    }

    public function restore(User $user, CardTemplate $cardTemplate): bool
    {
        return false;
    }

    public function forceDelete(User $user, CardTemplate $cardTemplate): bool
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

    public function replicate(User $user, CardTemplate $cardTemplate): bool
    {
        return false;
    }
}
