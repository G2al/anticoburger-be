<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Reservation;
use App\Services\Reservations\IssuedReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer facing view of a reservation. Never exposes the table, internal ids
 * or the token hash. The plain cancellation token is included only right after creation.
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    private ?string $cancellationToken = null;

    public static function issued(IssuedReservation $issued): self
    {
        $resource = new self($issued->reservation);
        $resource->cancellationToken = $issued->cancellationToken;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $startsAt = $this->localStartsAt();

        return [
            'reference' => $this->public_reference,
            'status' => $this->status->value,
            'date' => $startsAt->toDateString(),
            'time' => $startsAt->format('H:i'),
            'party_size' => $this->party_size,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $this->localEndsAt()->toIso8601String(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at?->setTimezone(config('booking.timezone'))->toIso8601String(),
            'cancellation_token' => $this->when($this->cancellationToken !== null, $this->cancellationToken),
        ];
    }
}
