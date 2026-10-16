<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

/**
 * Reservations are never deleted: they can only be cancelled, to keep the history.
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
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
