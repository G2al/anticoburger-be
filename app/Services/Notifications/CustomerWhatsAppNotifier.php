<?php

namespace App\Services\Notifications;

use App\Models\Reservation;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the WhatsApp booking confirmation to the customer through WAHA.
 *
 * Never throws: WhatsApp problems must not affect the booking. Logs only the
 * reservation id and the outcome, never the phone number or the token.
 */
class CustomerWhatsAppNotifier
{
    public function __construct(private readonly WahaClient $waha) {}

    /**
     * @return bool true when the message was handed to WAHA
     */
    public function sendReservationConfirmation(Reservation $reservation, #[\SensitiveParameter] string $cancellationToken, bool $rescheduled = false): bool
    {
        if (! $this->waha->isConfigured()) {
            return false;
        }

        try {
            $chatId = $this->waha->chatIdFor($reservation->phone_normalized);

            if ($chatId === null) {
                Log::info('WhatsApp confirmation skipped: the number has no WhatsApp.', ['reservation_id' => $reservation->id]);

                return false;
            }

            $this->waha->sendText($chatId, $this->confirmationText($reservation, $cancellationToken, $rescheduled));

            return true;
        } catch (Throwable $exception) {
            Log::warning('WhatsApp confirmation not sent.', [
                'reservation_id' => $reservation->id,
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            ]);

            return false;
        }
    }

    public function confirmationText(Reservation $reservation, #[\SensitiveParameter] string $cancellationToken, bool $rescheduled = false): string
    {
        $startsAt = $reservation->localStartsAt()->locale('it');
        $people = $reservation->party_size === 1 ? '1 persona' : "{$reservation->party_size} persone";
        $outcome = $rescheduled ? 'è stata modificata' : 'è confermata';

        return implode("\n", [
            "Ciao {$reservation->first_name}, la tua prenotazione da *Antico Burger* {$outcome} ✅",
            '',
            '📅 '.ucfirst($startsAt->translatedFormat('l j F')).' alle '.$startsAt->format('H:i'),
            "👥 {$people}",
            '',
            'Per vedere, modificare o annullare la prenotazione:',
            $this->managementUrl($reservation, $cancellationToken),
            '',
            'A presto!',
        ]);
    }

    /**
     * Personal link of the customer PWA. Reference and token travel in the URL
     * fragment, which browsers never send to servers.
     */
    public function managementUrl(Reservation $reservation, string $cancellationToken): string
    {
        return rtrim((string) config('booking.frontend_url'), '/')
            .'/prenotazione#ref='.$reservation->public_reference.'&token='.$cancellationToken;
    }
}
