<?php

namespace App\Services\Reservations;

use App\Enums\BookingError;
use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Exceptions\BookingException;
use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates, changes and cancels reservations. Shared by the public API and Filament,
 * so every path runs the same checks under the same BookingLock.
 *
 * Expected input keys: date (Y-m-d, local), time (H:i, local), party_size,
 * first_name, last_name, phone, email, notes and, for admins only, restaurant_table_id.
 */
class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PhoneNumberNormalizer $phoneNormalizer,
        private readonly BookingLock $bookingLock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws BookingException
     * @throws ValidationException
     */
    public function createPublic(array $data): IssuedReservation
    {
        $customer = $this->customerAttributes($data);
        $startsAt = $this->startsAtFrom($data);
        $partySize = (int) $data['party_size'];

        return $this->bookingLock->run(function (BookingSettings $settings) use ($customer, $startsAt, $partySize) {
            $this->availability->ensureStartIsPubliclyBookable($startsAt, $settings);
            $this->ensurePartySizeIsBookable($partySize);
            $this->ensureNotDuplicate($customer['phone_normalized'], $startsAt, $partySize, $settings);
            $this->ensurePhoneDailyLimitNotReached($customer['phone_normalized'], $startsAt, $settings);

            $table = $this->availability->freeTablesAt($startsAt, $partySize)->first()
                ?? throw BookingException::because(BookingError::SlotNotAvailable);

            $reservation = new Reservation([
                ...$customer,
                ...$this->occupancyAttributes($table, $startsAt, $partySize),
                'status' => ReservationStatus::Confirmed,
                'created_via' => ReservationChannel::Public,
            ]);

            $cancellationToken = $reservation->issueCancellationToken();
            $reservation->save();

            return new IssuedReservation($reservation, $cancellationToken);
        });
    }

    /**
     * Manual reservation: capacities, schedules, blocks and conflicts are enforced,
     * the public anti-abuse limits are not. Without restaurant_table_id the best table is assigned.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BookingException
     * @throws ValidationException
     */
    public function createByAdmin(array $data): Reservation
    {
        $customer = $this->customerAttributes($data);
        $startsAt = $this->startsAtFrom($data);
        $partySize = (int) $data['party_size'];
        $requestedTableId = $this->requestedTableId($data);

        return $this->bookingLock->run(function () use ($customer, $startsAt, $partySize, $requestedTableId) {
            $table = $this->chooseTable($startsAt, $partySize, $requestedTableId);

            return Reservation::query()->create([
                ...$customer,
                ...$this->occupancyAttributes($table, $startsAt, $partySize),
                'status' => ReservationStatus::Confirmed,
                'created_via' => ReservationChannel::Admin,
            ]);
        });
    }

    /**
     * Customer data changes never touch the occupancy. When date, time, party size or
     * table change, availability is checked again (ignoring the reservation itself).
     * A missing restaurant_table_id keeps the current table, an empty one asks for
     * automatic assignment.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BookingException
     * @throws ValidationException
     */
    public function updateByAdmin(Reservation $reservation, array $data): Reservation
    {
        $customer = $this->customerAttributes($data);
        $startsAt = $this->startsAtFrom($data);
        $partySize = (int) $data['party_size'];
        $requestedTableId = array_key_exists('restaurant_table_id', $data)
            ? $this->requestedTableId($data)
            : $reservation->restaurant_table_id;

        $occupancyChanged = ! $startsAt->equalTo($reservation->starts_at)
            || $partySize !== $reservation->party_size
            || $requestedTableId !== $reservation->restaurant_table_id;

        if (! $occupancyChanged) {
            $reservation->update($customer);

            return $reservation;
        }

        return $this->reschedule($reservation, $startsAt, $partySize, $requestedTableId, $customer);
    }

    /**
     * @throws BookingException
     */
    public function moveToTable(Reservation $reservation, int $restaurantTableId): Reservation
    {
        return $this->reschedule($reservation, $reservation->starts_at, $reservation->party_size, $restaurantTableId);
    }

    /**
     * Idempotent: cancelling an already cancelled reservation changes nothing.
     */
    public function cancel(Reservation $reservation): Reservation
    {
        if ($reservation->isCancelled()) {
            return $reservation;
        }

        return $this->bookingLock->run(function () use ($reservation) {
            $reservation->refresh();

            if ($reservation->isConfirmed()) {
                $reservation->forceFill([
                    'status' => ReservationStatus::Cancelled,
                    'cancelled_at' => CarbonImmutable::now('UTC'),
                ])->save();
            }

            return $reservation;
        });
    }

    /**
     * Finds a reservation by public reference, only when the token matches.
     * Unknown references and wrong tokens are indistinguishable to the caller.
     *
     * @throws BookingException
     */
    public function findForCustomer(string $reference, ?string $token): Reservation
    {
        if ($token === null || $token === '') {
            throw BookingException::because(BookingError::ReservationTokenMissing);
        }

        $reservation = Str::isUuid($reference)
            ? Reservation::query()->where('public_reference', $reference)->first()
            : null;

        if ($reservation === null || ! $reservation->matchesCancellationToken($token)) {
            throw BookingException::because(BookingError::ReservationNotFound);
        }

        return $reservation;
    }

    /**
     * @param  array<string, mixed>|null  $customer
     *
     * @throws BookingException
     */
    private function reschedule(
        Reservation $reservation,
        CarbonImmutable $startsAt,
        int $partySize,
        ?int $requestedTableId,
        ?array $customer = null,
    ): Reservation {
        return $this->bookingLock->run(function () use ($reservation, $startsAt, $partySize, $requestedTableId, $customer) {
            $reservation->refresh();

            if (! $reservation->isConfirmed()) {
                throw BookingException::because(BookingError::ReservationCancelled);
            }

            $table = $this->chooseTable($startsAt, $partySize, $requestedTableId, $reservation);

            $reservation->update([
                ...($customer ?? []),
                ...$this->occupancyAttributes($table, $startsAt, $partySize),
            ]);

            return $reservation;
        });
    }

    /**
     * @throws BookingException
     */
    private function chooseTable(
        CarbonImmutable $startsAt,
        int $partySize,
        ?int $requestedTableId,
        ?Reservation $ignoring = null,
    ): RestaurantTable {
        $this->ensurePartySizeIsBookable($partySize);

        $freeTables = $this->availability->freeTablesAt($startsAt, $partySize, $ignoring);

        if ($requestedTableId === null) {
            return $freeTables->first() ?? throw BookingException::because(BookingError::SlotNotAvailable);
        }

        return $freeTables->firstWhere('id', $requestedTableId)
            ?? throw BookingException::because(BookingError::TableNotAvailable);
    }

    /**
     * @throws BookingException
     */
    private function ensurePartySizeIsBookable(int $partySize): void
    {
        if (! $this->availability->isPartySizeBookable($partySize)) {
            throw BookingException::because(BookingError::PartySizeNotSupported);
        }
    }

    /**
     * @throws BookingException
     */
    private function ensureNotDuplicate(string $phoneNormalized, CarbonImmutable $startsAt, int $partySize, BookingSettings $settings): void
    {
        if (! $settings->prevent_duplicate_bookings) {
            return;
        }

        $duplicateExists = Reservation::query()
            ->confirmed()
            ->where('phone_normalized', $phoneNormalized)
            ->where('starts_at', $startsAt->utc())
            ->where('party_size', $partySize)
            ->exists();

        if ($duplicateExists) {
            throw BookingException::because(BookingError::DuplicateReservation);
        }
    }

    /**
     * The daily limit counts confirmed reservations starting on the same local date.
     *
     * @throws BookingException
     */
    private function ensurePhoneDailyLimitNotReached(string $phoneNormalized, CarbonImmutable $startsAt, BookingSettings $settings): void
    {
        if ($settings->max_reservations_per_phone_per_day === 0) {
            return;
        }

        $reservationsThatDay = Reservation::query()
            ->confirmed()
            ->where('phone_normalized', $phoneNormalized)
            ->startingOnLocalDate(BookingTime::toLocal($startsAt)->startOfDay())
            ->count();

        if ($reservationsThatDay >= $settings->max_reservations_per_phone_per_day) {
            throw BookingException::because(BookingError::PhoneDailyLimitReached);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function customerAttributes(array $data): array
    {
        $phone = trim((string) $data['phone']);
        $phoneNormalized = $this->phoneNormalizer->normalize($phone)
            ?? throw ValidationException::withMessages(['phone' => 'Il numero di telefono non è valido.']);

        return [
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'phone' => $phone,
            'phone_normalized' => $phoneNormalized,
            'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function occupancyAttributes(RestaurantTable $table, CarbonImmutable $startsAt, int $partySize): array
    {
        return [
            'restaurant_table_id' => $table->id,
            'party_size' => $partySize,
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes($table->duration_minutes)->utc(),
            'occupied_until' => $this->availability->occupiedUntil($table, $startsAt)->utc(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function startsAtFrom(array $data): CarbonImmutable
    {
        return BookingTime::at(BookingTime::date((string) $data['date']), (string) $data['time']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestedTableId(array $data): ?int
    {
        return filled($data['restaurant_table_id'] ?? null) ? (int) $data['restaurant_table_id'] : null;
    }
}
