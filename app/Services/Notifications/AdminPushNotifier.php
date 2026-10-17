<?php

namespace App\Services\Notifications;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\PushSubscription;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Sends Web Push notifications (VAPID) to the devices of the administrators.
 *
 * Sending never throws: a failing push service must not affect bookings.
 * Subscriptions reported as expired by the push service are deleted.
 * Endpoints are secret capability URLs, so only their host is logged.
 */
class AdminPushNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('push.vapid.public_key')) && filled(config('push.vapid.private_key'));
    }

    /**
     * @return int number of devices that accepted the notification
     */
    public function notifyNewReservation(Reservation $reservation): int
    {
        return $this->send($this->adminSubscriptions(), $this->newReservationPayload($reservation));
    }

    /**
     * @return int number of devices that accepted the notification
     */
    public function sendTest(User $user): int
    {
        return $this->send($user->pushSubscriptions()->get(), [
            'title' => 'Notifiche attive',
            'body' => 'Riceverai qui una notifica per ogni nuova prenotazione online.',
            'url' => url('/admin'),
            'tag' => 'test',
        ]);
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function newReservationPayload(Reservation $reservation): array
    {
        $reservation->loadMissing('restaurantTable');
        $startsAt = $reservation->localStartsAt()->locale('it');
        $people = $reservation->party_size === 1 ? '1 persona' : "{$reservation->party_size} persone";

        return [
            'title' => 'Nuova prenotazione',
            'body' => implode("\n", [
                "{$reservation->fullName()} · {$people}",
                ucfirst($startsAt->translatedFormat('l j F')).' alle '.$startsAt->format('H:i')
                    .' · '.$reservation->restaurantTable->name,
                "Tel. {$reservation->phone}",
            ]),
            'url' => ReservationResource::getUrl('view', ['record' => $reservation], panel: 'admin'),
            'tag' => 'reservation-'.$reservation->id,
        ];
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    private function adminSubscriptions(): Collection
    {
        return PushSubscription::query()
            ->whereHas('user', fn (Builder $users) => $users->where('is_admin', true))
            ->get();
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, string>  $payload
     */
    private function send(Collection $subscriptions, array $payload): int
    {
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        if (! $this->isConfigured()) {
            Log::warning('Push notifications skipped: VAPID keys are not configured.');

            return 0;
        }

        try {
            $webPush = app(WebPush::class);
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification($subscription->toWebPushSubscription(), $body);
            }

            $delivered = 0;

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $delivered++;

                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()
                        ->where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))
                        ->delete();

                    continue;
                }

                Log::warning('Push notification not delivered.', [
                    'push_service' => parse_url($report->getEndpoint(), PHP_URL_HOST),
                    'reason' => $report->getReason(),
                ]);
            }

            return $delivered;
        } catch (Throwable $exception) {
            Log::error('Push notifications failed.', ['exception' => $exception::class, 'message' => $exception->getMessage()]);

            return 0;
        }
    }
}
