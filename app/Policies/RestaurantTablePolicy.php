<?php

namespace App\Policies;

use App\Models\RestaurantTable;
use App\Models\User;

class RestaurantTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin();
    }

    /**
     * Tables with reservation history must be deactivated, not deleted.
     */
    public function delete(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin() && ! $table->hasReservationHistory();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
