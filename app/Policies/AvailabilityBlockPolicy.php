<?php

namespace App\Policies;

use App\Models\AvailabilityBlock;
use App\Models\User;

class AvailabilityBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AvailabilityBlock $block): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AvailabilityBlock $block): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AvailabilityBlock $block): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
