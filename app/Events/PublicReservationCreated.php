<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer confirmed a reservation through the public API.
 * Dispatched after the booking transaction has been committed.
 */
class PublicReservationCreated
{
    use Dispatchable;

    public function __construct(public readonly Reservation $reservation) {}
}
