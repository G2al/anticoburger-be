<?php

namespace App\Listeners;

use App\Events\PublicReservationCreated;
use App\Events\PublicReservationRescheduled;
use App\Models\Reservation;
use App\Services\Notifications\CustomerWhatsAppNotifier;
use Illuminate\Support\Facades\Bus;

/**
 * Sends the WhatsApp confirmation (new or changed reservation) after the HTTP response has
 * been returned to the customer. Runs in the same process (never queued), so the token is
 * never persisted.
 */
class SendWhatsAppConfirmation
{
    public function handle(PublicReservationCreated|PublicReservationRescheduled $event): void
    {
        $reservationId = $event->reservation->id;
        $cancellationToken = $event->cancellationToken;
        $rescheduled = $event instanceof PublicReservationRescheduled;

        Bus::dispatchAfterResponse(function () use ($reservationId, $cancellationToken, $rescheduled) {
            $reservation = Reservation::query()->find($reservationId);

            if ($reservation?->isConfirmed()) {
                app(CustomerWhatsAppNotifier::class)->sendReservationConfirmation($reservation, $cancellationToken, $rescheduled);
            }
        });
    }
}
