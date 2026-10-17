<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer confirmed a reservation through the public API.
 * Dispatched after the booking transaction has been committed.
 *
 * The plain cancellation token exists only here, in memory: listeners may use it
 * (e.g. in the customer's confirmation message) but must never store or log it,
 * and must not be queued.
 */
class PublicReservationCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Reservation $reservation,
        #[\SensitiveParameter] public readonly string $cancellationToken,
    ) {}
}
