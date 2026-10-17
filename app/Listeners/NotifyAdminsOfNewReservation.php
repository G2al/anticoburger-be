<?php

namespace App\Listeners;

use App\Events\PublicReservationCreated;
use App\Models\Reservation;
use App\Services\Notifications\AdminPushNotifier;
use Illuminate\Support\Facades\Bus;

/**
 * Sends the push notification after the HTTP response has been returned to the
 * customer, so the booking is never slowed down or affected by the push services.
 */
class NotifyAdminsOfNewReservation
{
    public function handle(PublicReservationCreated $event): void
    {
        $reservationId = $event->reservation->id;

        Bus::dispatchAfterResponse(function () use ($reservationId) {
            $reservation = Reservation::query()->find($reservationId);

            if ($reservation?->isConfirmed()) {
                app(AdminPushNotifier::class)->notifyNewReservation($reservation);
            }
        });
    }
}
