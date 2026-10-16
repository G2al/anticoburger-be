<?php

namespace App\Services\Reservations;

use App\Models\Reservation;

/**
 * A newly created public reservation together with its plain cancellation token,
 * which is available only at creation time.
 */
final readonly class IssuedReservation
{
    public function __construct(
        public Reservation $reservation,
        public string $cancellationToken,
    ) {}
}
