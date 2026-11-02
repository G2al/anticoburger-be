<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer moved an online reservation to another date, time and/or party size.
 * Dispatched after the booking transaction has been committed.
 *
 * The plain cancellation token (sent by the customer with the request) exists only here,
 * in memory: listeners may use it but must never store or log it, and must not be queued.
 */
class PublicReservationRescheduled
{
    use Dispatchable;

    public function __construct(
        public readonly Reservation $reservation,
        #[\SensitiveParameter] public readonly string $cancellationToken,
    ) {}
}
