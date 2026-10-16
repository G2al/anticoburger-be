<?php

namespace Tests\Feature\Reservations;

use App\Enums\BookingError;
use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Exceptions\BookingException;
use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityService;
use App\Services\Reservations\ReservationService;
use App\Support\BookingTime;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    private ReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->reservations = app(ReservationService::class);
    }

    private function assertBookingFails(BookingError $expected, Closure $operation): void
    {
        try {
            $operation();
            $this->fail("Expected booking error {$expected->value}");
        } catch (BookingException $exception) {
            $this->assertSame($expected, $exception->error);
        }
    }

    public function test_public_reservation_is_confirmed_with_stored_end_times_and_hashed_token(): void
    {
        $table = $this->createTable([2, 4], ['duration_minutes' => 90, 'turnaround_minutes' => 15]);

        $issued = $this->reservations->createPublic($this->reservationData(['notes' => 'Zona tranquilla']));
        $reservation = $issued->reservation->fresh();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(ReservationChannel::Public, $reservation->created_via);
        $this->assertSame($table->id, $reservation->restaurant_table_id);
        $this->assertSame('+393331234567', $reservation->phone_normalized);
        $this->assertSame('333 123 4567', $reservation->phone);
        $this->assertSame('2026-10-14 18:00:00', $reservation->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-14 21:30', $reservation->localEndsAt()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-14 21:45', BookingTime::toLocal($reservation->occupied_until)->format('Y-m-d H:i'));
        $this->assertTrue(str($reservation->public_reference)->isUuid());
        $this->assertNotSame($issued->cancellationToken, $reservation->cancellation_token_hash);
        $this->assertSame(hash('sha256', $issued->cancellationToken), $reservation->cancellation_token_hash);
        $this->assertTrue($reservation->matchesCancellationToken($issued->cancellationToken));
        $this->assertFalse($reservation->matchesCancellationToken('wrong'));
    }

    public function test_changing_table_settings_does_not_alter_existing_reservations(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60]);
        $reservation = $this->reservations->createPublic($this->reservationData())->reservation;

        $table->update(['duration_minutes' => 120, 'turnaround_minutes' => 30]);

        $this->assertSame('21:00', $reservation->fresh()->localEndsAt()->format('H:i'));
    }

    public function test_best_fit_prefers_the_smallest_suitable_table(): void
    {
        $tableOne = $this->createTable([2], ['name' => 'Tavolo 1']);
        $tableTwo = $this->createTable([2, 3, 4], ['name' => 'Tavolo 2']);
        $this->createTable([2, 3, 4], ['name' => 'Tavolo 3']);
        $tableFour = $this->createTable([5, 6], ['name' => 'Tavolo 4']);

        $this->assertSame($tableOne->id, $this->reservations->createPublic($this->reservationData(['phone' => '3330000001']))->reservation->restaurant_table_id);
        $this->assertSame($tableTwo->id, $this->reservations->createPublic($this->reservationData(['phone' => '3330000002', 'party_size' => 3]))->reservation->restaurant_table_id);
        $this->assertSame($tableFour->id, $this->reservations->createPublic($this->reservationData(['phone' => '3330000003', 'party_size' => 6]))->reservation->restaurant_table_id);
    }

    public function test_best_fit_criteria_order(): void
    {
        $wide = $this->createTable([2, 3, 4], ['name' => 'Ampio']);
        $narrow = $this->createTable([2, 4], ['name' => 'Stretto']);
        $narrowLowPriority = $this->createTable([2, 4], ['name' => 'Stretto bis', 'assignment_priority' => 5]);
        $narrowPreferred = $this->createTable([2, 4], ['name' => 'Stretto preferito', 'assignment_priority' => 0]);

        $freeTables = app(AvailabilityService::class)->freeTablesAt(BookingTime::at(BookingTime::date(self::WEDNESDAY), '20:00'), 2);

        $this->assertSame(
            [$narrow->id, $narrowPreferred->id, $narrowLowPriority->id, $wide->id],
            $freeTables->modelKeys(),
        );
    }

    public function test_falls_back_to_the_next_compatible_table(): void
    {
        $tableOne = $this->createTable([2], ['name' => 'Tavolo 1']);
        $tableTwo = $this->createTable([2, 3, 4], ['name' => 'Tavolo 2']);
        Reservation::factory()->on($tableOne, self::WEDNESDAY, '20:00')->create();

        $reservation = $this->reservations->createPublic($this->reservationData())->reservation;

        $this->assertSame($tableTwo->id, $reservation->restaurant_table_id);
    }

    public function test_party_size_without_compatible_table_is_rejected(): void
    {
        $this->createTable([2, 4, 6]);

        $this->assertBookingFails(BookingError::PartySizeNotSupported, fn () => $this->reservations->createPublic($this->reservationData(['party_size' => 5])));
    }

    public function test_no_free_table_means_slot_not_available(): void
    {
        $table = $this->createTable([2]);
        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData()));
    }

    public function test_time_outside_the_schedule_is_not_available(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30], '19:00', '23:00');

        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData(['time' => '23:30'])));
        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData(['time' => '20:10'])));
    }

    public function test_consecutive_reservations_on_the_same_table_are_allowed(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 30]);

        $first = $this->reservations->createPublic($this->reservationData(['phone' => '3330000001']))->reservation;
        $second = $this->reservations->createPublic($this->reservationData(['phone' => '3330000002', 'time' => '21:00']))->reservation;

        $this->assertSame($table->id, $first->restaurant_table_id);
        $this->assertSame($table->id, $second->restaurant_table_id);
    }

    public function test_overlapping_reservations_on_the_same_table_are_refused(): void
    {
        $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 30]);
        $this->reservations->createPublic($this->reservationData(['phone' => '3330000001']));

        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData(['phone' => '3330000002', 'time' => '20:30'])));
        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData(['phone' => '3330000003', 'time' => '19:30'])));
    }

    public function test_turnaround_extends_the_occupancy(): void
    {
        $this->createTable([2], ['duration_minutes' => 30, 'slot_interval_minutes' => 30, 'turnaround_minutes' => 10]);
        $first = $this->reservations->createPublic($this->reservationData(['phone' => '3330000001']))->reservation;

        $this->assertSame('20:30', $first->localEndsAt()->format('H:i'));
        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData(['phone' => '3330000002', 'time' => '20:30'])));

        $later = $this->reservations->createPublic($this->reservationData(['phone' => '3330000003', 'time' => '21:00']))->reservation;
        $this->assertTrue($later->exists);
    }

    public function test_tables_with_different_durations_are_evaluated_separately(): void
    {
        $short = $this->createTable([2], ['name' => 'Corto', 'duration_minutes' => 30, 'slot_interval_minutes' => 30]);
        $long = $this->createTable([2, 4], ['name' => 'Lungo', 'duration_minutes' => 90, 'slot_interval_minutes' => 30]);

        $first = $this->reservations->createPublic($this->reservationData(['phone' => '3330000001']))->reservation;
        $second = $this->reservations->createPublic($this->reservationData(['phone' => '3330000002']))->reservation;

        $this->assertSame($short->id, $first->restaurant_table_id);
        $this->assertSame('20:30', $first->localEndsAt()->format('H:i'));
        $this->assertSame($long->id, $second->restaurant_table_id);
        $this->assertSame('21:30', $second->localEndsAt()->format('H:i'));

        $slots = app(AvailabilityService::class)->publicSlotsFor(BookingTime::date(self::WEDNESDAY), 2, BookingSettings::current());
        $this->assertContains('20:30', $slots);
        $this->assertNotContains('20:00', $slots);
    }

    public function test_cancellation_frees_the_table_and_is_idempotent(): void
    {
        $this->createTable([2]);
        $reservation = $this->reservations->createPublic($this->reservationData())->reservation;

        $this->reservations->cancel($reservation);
        $cancelledAt = $reservation->fresh()->cancelled_at;

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertNotNull($cancelledAt);

        $this->travel(5)->minutes();
        $this->reservations->cancel($reservation->fresh());
        $this->assertEquals($cancelledAt, $reservation->fresh()->cancelled_at);

        $again = $this->reservations->createPublic($this->reservationData(['phone' => '3330000009']))->reservation;
        $this->assertSame($reservation->restaurant_table_id, $again->restaurant_table_id);
        $this->assertSame(2, Reservation::query()->count());
    }

    public function test_availability_is_checked_again_when_saving(): void
    {
        $table = $this->createTable([2]);
        $slots = app(AvailabilityService::class)->publicSlotsFor(BookingTime::date(self::WEDNESDAY), 2, BookingSettings::current());
        $this->assertContains('20:00', $slots);

        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createPublic($this->reservationData()));
    }

    public function test_inactive_table_is_never_assigned_but_keeps_its_reservations(): void
    {
        $table = $this->createTable([2]);
        $existing = $this->reservations->createPublic($this->reservationData(['phone' => '3330000001']))->reservation;

        $table->update(['is_active' => false]);

        $this->assertSame(ReservationStatus::Confirmed, $existing->fresh()->status);
        $this->assertBookingFails(BookingError::PartySizeNotSupported, fn () => $this->reservations->createPublic($this->reservationData(['phone' => '3330000002', 'time' => '21:00'])));
    }

    public function test_reservation_in_winter_time_is_stored_in_utc(): void
    {
        $this->createTable([2]);

        $reservation = $this->reservations->createPublic($this->reservationData(['date' => '2026-10-25', 'time' => '20:00']))->reservation->fresh();

        $this->assertSame('2026-10-25 19:00:00', $reservation->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25T20:00:00+01:00', $reservation->localStartsAt()->toIso8601String());
    }
}
