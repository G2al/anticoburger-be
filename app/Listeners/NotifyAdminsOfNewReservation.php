<?php

namespace App\Listeners;

use App\Events\PublicReservationCreated;
use App\Events\PublicReservationRescheduled;
use App\Models\Reservation;
use App\Services\Notifications\AdminPushNotifier;
use Illuminate\Support\Facades\Bus;

/**
 * Sends the push notification (new or changed online reservation) after the HTTP response
 * has been returned to the customer, so the booking is never slowed down or affected by
 * the push services.
 */
class NotifyAdminsOfNewReservation
{
    public function handle(PublicReservationCreated|PublicReservationRescheduled $event): void
    {
        $reservationId = $event->reservation->id;
        $rescheduled = $event instanceof PublicReservationRescheduled;

        Bus::dispatchAfterResponse(function () use ($reservationId, $rescheduled) {
            $reservation = Reservation::query()->find($reservationId);

            if (! $reservation?->isConfirmed()) {
                return;
            }

            $notifier = app(AdminPushNotifier::class);
            $rescheduled ? $notifier->notifyRescheduledReservation($reservation) : $notifier->notifyNewReservation($reservation);
        });
    }
}
