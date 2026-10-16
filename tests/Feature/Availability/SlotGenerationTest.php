<?php

namespace Tests\Feature\Availability;

use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Models\TableSchedule;
use App\Services\Reservations\AvailabilityService;
use App\Services\Reservations\ReservationService;
use App\Support\BookingTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class SlotGenerationTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
    }

    /**
     * @return list<string>
     */
    private function slots(string $date = self::WEDNESDAY, int $partySize = 2): array
    {
        return app(AvailabilityService::class)->publicSlotsFor(BookingTime::date($date), $partySize, BookingSettings::current());
    }

    public function test_last_booking_time_is_an_included_start_time(): void
    {
        $this->createTable([2, 3, 4], [
            'name' => 'Tavolo 6',
            'duration_minutes' => 30,
            'slot_interval_minutes' => 30,
        ], '19:00', '23:00');

        $this->assertSame(
            ['19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00'],
            $this->slots(),
        );

        $reservation = app(ReservationService::class)
            ->createPublic($this->reservationData(['time' => '23:00']))
            ->reservation;

        $this->assertSame('23:30', $reservation->localEndsAt()->format('H:i'));
    }

    public function test_one_range_follows_the_slot_interval(): void
    {
        $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 15], '19:00', '20:00');

        $this->assertSame(['19:00', '19:15', '19:30', '19:45', '20:00'], $this->slots());
    }

    public function test_multiple_ranges_on_the_same_day(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 60], '12:00', '14:00');
        $table->schedules()->createMany(array_map(fn (int $day) => [
            'day_of_week' => $day,
            'first_booking_time' => '19:00:00',
            'last_booking_time' => '21:00:00',
        ], array_keys(TableSchedule::DAY_NAMES)));

        $this->assertSame(['12:00', '13:00', '14:00', '19:00', '20:00', '21:00'], $this->slots());
    }

    public function test_day_without_ranges_is_not_bookable(): void
    {
        $this->createTable([2], [], '19:00', '23:00', days: [1, 2, 4, 5, 6, 7]);

        $this->assertSame([], $this->slots(self::WEDNESDAY));
        $this->assertNotSame([], $this->slots(self::THURSDAY));
    }

    public function test_each_day_uses_its_own_ranges(): void
    {
        $table = $this->createTable([2], ['slot_interval_minutes' => 60], '19:00', '20:00', days: [3]);
        $table->schedules()->create(['day_of_week' => 4, 'first_booking_time' => '12:00:00', 'last_booking_time' => '13:00:00']);

        $this->assertSame(['19:00', '20:00'], $this->slots(self::WEDNESDAY));
        $this->assertSame(['12:00', '13:00'], $this->slots(self::THURSDAY));
    }

    public function test_duration_longer_than_interval_blocks_overlapping_starts_on_the_same_table(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 30], '19:00', '21:00');
        Reservation::factory()->on($table, self::WEDNESDAY, '19:00')->create();

        $this->assertSame(['20:00', '20:30', '21:00'], $this->slots());
    }

    public function test_slots_of_several_tables_are_merged_without_duplicates(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30], '19:00', '20:00');
        $this->createTable([2, 4], ['slot_interval_minutes' => 60], '19:00', '21:00');

        $this->assertSame(['19:00', '19:30', '20:00', '21:00'], $this->slots());
    }

    public function test_only_tables_accepting_the_party_size_are_considered(): void
    {
        $this->createTable([2, 4, 6], ['slot_interval_minutes' => 60], '19:00', '20:00');

        $this->assertSame(['19:00', '20:00'], $this->slots(partySize: 4));
        $this->assertSame([], $this->slots(partySize: 3));
        $this->assertSame([], $this->slots(partySize: 5));
    }

    public function test_inactive_tables_offer_no_slots(): void
    {
        $this->createTable([2], ['is_active' => false]);

        $this->assertSame([], $this->slots());
    }

    public function test_no_tables_means_no_slots(): void
    {
        $this->assertSame([], $this->slots());
    }

    public function test_occupancy_running_past_midnight_blocks_the_next_day(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 90, 'slot_interval_minutes' => 30], '23:30', '23:30', days: [3]);
        $table->schedules()->create(['day_of_week' => 4, 'first_booking_time' => '00:00:00', 'last_booking_time' => '01:00:00']);

        $reservation = app(ReservationService::class)
            ->createPublic($this->reservationData(['time' => '23:30']))
            ->reservation;

        $this->assertSame('2026-10-15 01:00', $reservation->localEndsAt()->format('Y-m-d H:i'));
        $this->assertSame(['01:00'], $this->slots(self::THURSDAY));
    }
}
