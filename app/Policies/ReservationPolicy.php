<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

/**
 * Admins can cancel reservations (history kept) or delete them permanently.
 */
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin() && $reservation->isConfirmed();
    }

    public function move(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin() && $reservation->isConfirmed();
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
