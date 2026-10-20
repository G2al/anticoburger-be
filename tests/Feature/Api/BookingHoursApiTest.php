<?php

namespace Tests\Feature\Api;

use Database\Seeders\AnticoBurgerTablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class BookingHoursApiTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    public function test_real_weekly_schedule_has_tuesday_closed_and_saturday_shorter(): void
    {
        $this->seed(AnticoBurgerTablesSeeder::class);

        $this->getJson('/api/v1/booking-hours')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.timezone', 'Europe/Rome')
            ->assertJsonCount(7, 'data.days')
            ->assertJsonPath('data.days.0.ranges', [['first_booking_time' => '18:30', 'last_booking_time' => '23:25']])
            ->assertJsonPath('data.days.1.ranges', [])
            ->assertJsonPath('data.days.5.ranges', [['first_booking_time' => '18:30', 'last_booking_time' => '21:00']]);
    }

    public function test_merges_overlaps_preserves_gaps_and_excludes_inactive_tables(): void
    {
        $this->createTable([2], [], '12:00', '14:00', [1]);
        $this->createTable([4], [], '13:00', '15:00', [1]);
        $this->createTable([2], [], '19:00', '22:00', [1]);
        $this->createTable([2], ['is_active' => false], '10:00', '23:00', [1, 2]);

        $this->getJson('/api/v1/booking-hours')->assertOk()
            ->assertJsonPath('data.days.0.ranges', [
                ['first_booking_time' => '12:00', 'last_booking_time' => '15:00'],
                ['first_booking_time' => '19:00', 'last_booking_time' => '22:00'],
            ])->assertJsonPath('data.days.1.ranges', []);
    }

    public function test_admin_schedule_changes_are_reflected_without_redeploying(): void
    {
        $table = $this->createTable([2], [], '19:00', '23:00', [1]);
        $this->getJson('/api/v1/booking-hours')->assertJsonPath('data.days.0.ranges.0.last_booking_time', '23:00');
        $table->schedules()->update(['last_booking_time' => '22:00:00']);
        $this->getJson('/api/v1/booking-hours')->assertJsonPath('data.days.0.ranges.0.last_booking_time', '22:00');
    }

    public function test_no_bookable_tables_returns_seven_closed_days(): void
    {
        $this->createTable([], [], '19:00', '23:00', [1]);
        $response = $this->getJson('/api/v1/booking-hours')->assertOk()->assertJsonCount(7, 'data.days');
        foreach (range(0, 6) as $index) {
            $response->assertJsonPath("data.days.$index.ranges", []);
        }
    }
}
