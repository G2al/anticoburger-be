<?php

namespace Tests\Feature\Availability;

use App\Enums\BlockScope;
use App\Enums\BookingError;
use App\Enums\ReservationStatus;
use App\Exceptions\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityBlockService;
use App\Services\Reservations\AvailabilityService;
use App\Services\Reservations\ReservationService;
use App\Support\BookingTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class AvailabilityBlocksTest extends TestCase
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

    public function test_whole_restaurant_block_for_an_entire_day_closes_every_table(): void
    {
        $this->createTable([2]);
        $this->createTable([2, 4]);
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->create();

        $this->assertSame([], $this->slots());
        $this->assertNotSame([], $this->slots(self::THURSDAY));
    }

    public function test_multi_day_block_closes_every_included_day(): void
    {
        $this->createTable([2]);
        AvailabilityBlock::factory()->between('2026-10-22', '2026-10-23')->create();

        $this->assertSame([], $this->slots('2026-10-22'));
        $this->assertSame([], $this->slots('2026-10-23'));
        $this->assertNotSame([], $this->slots('2026-10-21'));
        $this->assertNotSame([], $this->slots('2026-10-24'));
    }

    public function test_partial_block_rejects_any_overlapping_occupancy(): void
    {
        $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 30], '19:00', '23:00');
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->timeRange('20:00', '22:00')->create();

        $this->assertSame(['19:00', '22:00', '22:30', '23:00'], $this->slots());
    }

    public function test_partial_overlap_of_a_requested_reservation_is_refused(): void
    {
        $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 30], '19:00', '23:00');
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->timeRange('20:00', '21:00')->create();

        $this->expectExceptionObject(BookingException::because(BookingError::SlotNotAvailable));

        app(ReservationService::class)->createPublic($this->reservationData(['time' => '19:30']));
    }

    public function test_partial_block_on_several_days_applies_the_range_to_each_day(): void
    {
        $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 60], '19:00', '22:00');
        AvailabilityBlock::factory()->between(self::WEDNESDAY, self::THURSDAY)->timeRange('19:00', '21:00')->create();

        $this->assertSame(['21:00', '22:00'], $this->slots(self::WEDNESDAY));
        $this->assertSame(['21:00', '22:00'], $this->slots(self::THURSDAY));
        $this->assertSame(['19:00', '20:00', '21:00', '22:00'], $this->slots('2026-10-16'));
    }

    public function test_table_block_affects_only_that_table(): void
    {
        $blockedTable = $this->createTable([2], ['name' => 'Tavolo 6', 'slot_interval_minutes' => 60], '19:00', '21:00');
        $otherTable = $this->createTable([2, 4], ['name' => 'Tavolo 7', 'slot_interval_minutes' => 60], '19:00', '21:00');
        AvailabilityBlock::factory()->forTable($blockedTable)->between(self::WEDNESDAY)->timeRange('19:00', '21:00')->create();

        $this->assertSame(['19:00', '20:00', '21:00'], $this->slots());

        $reservation = app(ReservationService::class)->createPublic($this->reservationData(['time' => '19:00']))->reservation;

        $this->assertSame($otherTable->id, $reservation->restaurant_table_id);
    }

    public function test_inactive_blocks_are_ignored(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 60], '19:00', '20:00');
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->inactive()->create();

        $this->assertSame(['19:00', '20:00'], $this->slots());
    }

    public function test_new_block_preserves_existing_reservations_and_reports_them(): void
    {
        $table = $this->createTable([2]);
        $affected = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
        Reservation::factory()->on($table, self::THURSDAY, '20:00')->create();
        Reservation::factory()->on($table, self::WEDNESDAY, '22:00')->cancelled()->create();

        $block = app(AvailabilityBlockService::class)->create([
            'title' => 'Evento privato',
            'scope' => BlockScope::Restaurant->value,
            'restaurant_table_id' => null,
            'starts_on' => self::WEDNESDAY,
            'ends_on' => self::WEDNESDAY,
            'is_all_day' => false,
            'start_time' => '19:30',
            'end_time' => '23:00',
            'is_active' => true,
        ]);

        $this->assertSame([$affected->id], app(AvailabilityBlockService::class)->affectedReservations($block)->modelKeys());
        $this->assertSame(ReservationStatus::Confirmed, $affected->fresh()->status);
        $this->assertSame(3, Reservation::query()->count());
    }

    public function test_block_validation_rules(): void
    {
        $table = $this->createTable([2]);
        $service = app(AvailabilityBlockService::class);
        $valid = [
            'title' => 'Chiusura',
            'scope' => BlockScope::Table->value,
            'restaurant_table_id' => $table->id,
            'starts_on' => self::WEDNESDAY,
            'ends_on' => self::THURSDAY,
            'is_all_day' => false,
            'start_time' => '20:00',
            'end_time' => '22:00',
            'is_active' => true,
        ];

        $invalidCases = [
            'table scope without table' => ['restaurant_table_id' => null],
            'restaurant scope with table' => ['scope' => BlockScope::Restaurant->value],
            'ends before starts' => ['ends_on' => self::TODAY],
            'end time before start time' => ['end_time' => '19:00'],
            'range crossing midnight' => ['start_time' => '23:00', 'end_time' => '01:00'],
            'missing times' => ['start_time' => null],
        ];

        foreach ($invalidCases as $case => $override) {
            try {
                $service->validate([...$valid, ...$override]);
                $this->fail("Validation should fail: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $allDay = $service->validate([...$valid, 'is_all_day' => true, 'start_time' => null, 'end_time' => null]);
        $this->assertNull($allDay['start_time']);
        $this->assertSame('20:00:00', $service->validate($valid)['start_time']);
    }
}
