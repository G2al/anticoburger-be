<?php

namespace Tests\Feature\Reservations;

use App\Enums\BookingError;
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

class BookingLimitsTest extends TestCase
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

    public function test_settings_singleton_has_the_initial_values(): void
    {
        $settings = BookingSettings::current();

        $this->assertSame(1, BookingSettings::query()->count());
        $this->assertSame(30, $settings->min_lead_minutes);
        $this->assertSame(30, $settings->max_advance_days);
        $this->assertSame(2, $settings->max_reservations_per_phone_per_day);
        $this->assertTrue($settings->prevent_duplicate_bookings);

        BookingSettings::query()->delete();
        BookingSettings::current();
        BookingSettings::current();
        $this->assertSame(1, BookingSettings::query()->count());
    }

    public function test_minimum_lead_time(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30], '10:00', '12:00');
        $this->updateSettings(['min_lead_minutes' => 60]);

        $slots = app(AvailabilityService::class)->publicSlotsFor(BookingTime::date(self::TODAY), 2, BookingSettings::current());

        $this->assertSame(['11:00', '11:30', '12:00'], $slots);
        $this->assertBookingFails(BookingError::BookingTooSoon, fn () => $this->reservations->createPublic($this->reservationData(['date' => self::TODAY, 'time' => '10:30'])));
        $this->assertTrue($this->reservations->createPublic($this->reservationData(['date' => self::TODAY, 'time' => '11:00']))->reservation->exists);
    }

    public function test_past_times_are_never_publicly_bookable(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30], '09:00', '12:00');
        $this->updateSettings(['min_lead_minutes' => 0]);

        $slots = app(AvailabilityService::class)->publicSlotsFor(BookingTime::date(self::TODAY), 2, BookingSettings::current());

        $this->assertSame(['10:00', '10:30', '11:00', '11:30', '12:00'], $slots);
        $this->assertBookingFails(BookingError::BookingTooSoon, fn () => $this->reservations->createPublic($this->reservationData(['date' => self::TODAY, 'time' => '09:30'])));
        $this->assertBookingFails(BookingError::DateOutOfRange, fn () => $this->reservations->createPublic($this->reservationData(['date' => '2026-10-11'])));
    }

    public function test_maximum_advance_in_calendar_days(): void
    {
        $this->createTable([2]);

        $this->assertTrue($this->reservations->createPublic($this->reservationData(['date' => '2026-11-11']))->reservation->exists);
        $this->assertBookingFails(BookingError::DateOutOfRange, fn () => $this->reservations->createPublic($this->reservationData(['date' => '2026-11-12', 'phone' => '3330000002'])));
    }

    public function test_disabled_limits(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30], '00:00', '23:30');
        $this->createTable([2]);
        $this->updateSettings([
            'min_lead_minutes' => 0,
            'max_advance_days' => 0,
            'max_reservations_per_phone_per_day' => 0,
            'prevent_duplicate_bookings' => false,
        ]);

        $this->assertTrue($this->reservations->createPublic($this->reservationData(['date' => self::TODAY, 'time' => '10:00']))->reservation->exists);
        $this->assertTrue($this->reservations->createPublic($this->reservationData(['date' => '2027-06-30']))->reservation->exists);

        foreach (['19:00', '20:00', '21:00'] as $time) {
            $this->assertTrue($this->reservations->createPublic($this->reservationData(['time' => $time]))->reservation->exists);
        }

        $this->assertTrue($this->reservations->createPublic($this->reservationData(['time' => '20:00']))->reservation->exists);
    }

    public function test_daily_limit_per_phone_number_uses_the_normalized_number(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30]);

        $this->reservations->createPublic($this->reservationData(['time' => '19:00', 'phone' => '333 123 4567']));
        $this->reservations->createPublic($this->reservationData(['time' => '20:00', 'phone' => '+39 3331234567']));

        $this->assertBookingFails(BookingError::PhoneDailyLimitReached, fn () => $this->reservations->createPublic($this->reservationData(['time' => '21:00', 'phone' => '0039 333 1234567'])));
        $this->assertTrue($this->reservations->createPublic($this->reservationData(['date' => self::THURSDAY, 'time' => '21:00']))->reservation->exists);
    }

    public function test_cancelled_reservations_do_not_count_towards_the_daily_limit(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30]);

        $first = $this->reservations->createPublic($this->reservationData(['time' => '19:00']))->reservation;
        $this->reservations->createPublic($this->reservationData(['time' => '20:00']));
        $this->reservations->cancel($first);

        $this->assertTrue($this->reservations->createPublic($this->reservationData(['time' => '21:00']))->reservation->exists);
    }

    public function test_duplicate_request_is_rejected(): void
    {
        $this->createTable([2]);
        $this->createTable([2]);

        $this->reservations->createPublic($this->reservationData());

        $this->assertBookingFails(BookingError::DuplicateReservation, fn () => $this->reservations->createPublic($this->reservationData(['phone' => '+393331234567'])));
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_duplicate_of_a_cancelled_reservation_is_allowed(): void
    {
        $this->createTable([2]);

        $first = $this->reservations->createPublic($this->reservationData())->reservation;
        $this->reservations->cancel($first);

        $this->assertTrue($this->reservations->createPublic($this->reservationData())->reservation->exists);
    }

    public function test_admin_reservations_ignore_public_anti_abuse_limits_but_not_availability(): void
    {
        $table = $this->createTable([2], ['slot_interval_minutes' => 30], '09:00', '23:00');
        $this->updateSettings(['max_reservations_per_phone_per_day' => 1]);

        $this->reservations->createByAdmin($this->reservationData(['date' => self::TODAY, 'time' => '10:00']));
        $this->reservations->createByAdmin($this->reservationData(['date' => self::TODAY, 'time' => '19:00']));
        $this->reservations->createByAdmin($this->reservationData(['date' => '2026-12-30', 'time' => '20:00']));

        $this->assertSame(3, $table->reservations()->count());
        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createByAdmin($this->reservationData(['date' => self::TODAY, 'time' => '19:30'])));
        $this->assertBookingFails(BookingError::SlotNotAvailable, fn () => $this->reservations->createByAdmin($this->reservationData(['date' => self::TODAY, 'time' => '23:30'])));
    }
}
