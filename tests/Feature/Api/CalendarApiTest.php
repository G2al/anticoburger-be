<?php

namespace Tests\Feature\Api;

use App\Models\AvailabilityBlock;
use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityService;
use App\Support\BookingTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
    }

    /**
     * @return array<string, string> date => status
     */
    private function calendar(string $month = '2026-10', int $partySize = 2): array
    {
        $days = $this->getJson("/api/v1/calendar?month={$month}&party_size={$partySize}")
            ->assertOk()
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.party_size', $partySize)
            ->json('data.days');

        return array_column($days, 'status', 'date');
    }

    public function test_distinguishes_every_day_status(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60, 'slot_interval_minutes' => 60], '19:00', '21:00', days: [1, 2, 3, 4, 5, 6]);
        foreach (['19:00', '20:00', '21:00'] as $time) {
            Reservation::factory()->on($table, self::WEDNESDAY, $time)->create();
        }
        Reservation::factory()->on($table, '2026-10-17', '19:00')->cancelled()->create();
        AvailabilityBlock::factory()->between(self::THURSDAY)->create();
        AvailabilityBlock::factory()->between('2026-10-16')->timeRange('19:00', '20:00')->create();

        $calendar = $this->calendar();

        $this->assertCount(31, $calendar);
        $this->assertSame('2026-10-01', array_key_first($calendar));
        $this->assertSame('2026-10-31', array_key_last($calendar));
        $this->assertSame('not_bookable', $calendar['2026-10-01']);
        $this->assertSame('not_bookable', $calendar['2026-10-11']);
        $this->assertSame('available', $calendar[self::TODAY]);
        $this->assertSame('fully_booked', $calendar[self::WEDNESDAY]);
        $this->assertSame('closed', $calendar[self::THURSDAY]);
        $this->assertSame('available', $calendar['2026-10-16']);
        $this->assertSame('available', $calendar['2026-10-17']);
        $this->assertSame('closed', $calendar['2026-10-18']);
    }

    public function test_today_is_not_bookable_when_every_start_is_within_the_lead_time(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 30], '08:00', '10:00');

        $calendar = $this->calendar();

        $this->assertSame('not_bookable', $calendar[self::TODAY]);
        $this->assertSame('available', $calendar['2026-10-13']);

        $this->updateSettings(['min_lead_minutes' => 0]);
        $this->assertSame('available', $this->calendar()[self::TODAY]);
    }

    public function test_days_beyond_the_advance_limit_are_not_bookable(): void
    {
        $this->createTable([2]);

        $november = $this->calendar('2026-11');
        $this->assertSame('available', $november['2026-11-11']);
        $this->assertSame('not_bookable', $november['2026-11-12']);
        $this->assertSame(['not_bookable'], array_values(array_unique($this->calendar('2026-12'))));

        $this->updateSettings(['max_advance_days' => 0]);
        $this->assertSame(['available'], array_values(array_unique($this->calendar('2026-12'))));
    }

    public function test_without_tables_or_compatible_party_size_bookable_days_are_closed(): void
    {
        $calendar = $this->calendar();
        $this->assertSame('closed', $calendar[self::WEDNESDAY]);
        $this->assertSame('not_bookable', $calendar['2026-10-11']);

        $this->createTable([2, 4]);
        $this->assertSame('closed', $this->calendar(partySize: 3)[self::WEDNESDAY]);
        $this->assertSame('available', $this->calendar(partySize: 4)[self::WEDNESDAY]);
    }

    public function test_inactive_tables_and_blocks_are_handled_like_the_slots_endpoint(): void
    {
        $this->createTable([2], ['is_active' => false]);
        $active = $this->createTable([2], ['slot_interval_minutes' => 60], '19:00', '20:00');
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->inactive()->create();
        AvailabilityBlock::factory()->forTable($active)->between(self::THURSDAY)->create();

        $calendar = $this->calendar();

        $this->assertSame('available', $calendar[self::WEDNESDAY]);
        $this->assertSame('closed', $calendar[self::THURSDAY]);
    }

    public function test_available_days_match_the_availability_endpoint(): void
    {
        $short = $this->createTable([2], ['duration_minutes' => 30, 'slot_interval_minutes' => 30], '19:00', '20:00', days: [1, 3, 5]);
        $long = $this->createTable([2, 4], ['duration_minutes' => 90, 'turnaround_minutes' => 15], '12:00', '14:00', days: [2, 3, 6]);
        foreach (['19:00', '19:30', '20:00'] as $time) {
            Reservation::factory()->on($short, '2026-10-16', $time)->create();
        }
        Reservation::factory()->on($long, '2026-10-20', '12:00')->create();
        AvailabilityBlock::factory()->between('2026-10-21', '2026-10-24')->timeRange('11:00', '19:30')->create();

        $settings = BookingSettings::current();
        $availability = app(AvailabilityService::class);

        foreach ($this->calendar() as $date => $status) {
            $slots = $status === 'not_bookable' && $date < self::TODAY
                ? []
                : $availability->publicSlotsFor(BookingTime::date($date), 2, $settings);

            $this->assertSame($slots !== [], $status === 'available', "Mismatch on {$date} ({$status})");
        }
    }

    public function test_uses_a_bounded_number_of_queries(): void
    {
        foreach (range(1, 5) as $index) {
            $table = $this->createTable([2]);
            Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
            Reservation::factory()->on($table, '2026-10-20', '21:00')->create();
        }
        AvailabilityBlock::factory()->between('2026-10-25', '2026-10-27')->create();

        DB::enableQueryLog();
        $this->calendar();

        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }

    public function test_invalid_parameters_return_422(): void
    {
        $this->getJson('/api/v1/calendar?month=2026-13&party_size=2')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['month']]]);

        $this->getJson('/api/v1/calendar?month=2026-10')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['party_size']]]);

        $this->getJson('/api/v1/calendar?month=2026-10-01&party_size=0')
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['month', 'party_size']]]);
    }

    public function test_only_a_limited_range_of_months_can_be_requested(): void
    {
        $this->getJson('/api/v1/calendar?month=2026-09&party_size=2')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MONTH_OUT_OF_RANGE');

        $this->getJson('/api/v1/calendar?month=2027-11&party_size=2')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MONTH_OUT_OF_RANGE');

        $this->getJson('/api/v1/calendar?month=2027-10&party_size=2')->assertOk();
    }

    public function test_shares_the_read_rate_limit(): void
    {
        config(['booking.rate_limits.read_per_minute' => 1]);

        $this->getJson('/api/v1/calendar?month=2026-10&party_size=2')->assertOk();
        $this->getJson('/api/v1/availability?date='.self::WEDNESDAY.'&party_size=2')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }
}
