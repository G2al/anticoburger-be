<?php

namespace Tests\Feature\Api;

use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
    }

    public function test_returns_only_the_available_slots(): void
    {
        $table = $this->createTable([2, 3, 4], ['duration_minutes' => 30, 'slot_interval_minutes' => 30], '19:00', '21:00');
        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        $this->getJson('/api/v1/availability?date='.self::WEDNESDAY.'&party_size=2')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'date' => self::WEDNESDAY,
                    'party_size' => 2,
                    'slots' => ['19:00', '19:30', '20:30', '21:00'],
                ],
            ]);
    }

    public function test_respects_blocks(): void
    {
        $this->createTable([2], ['slot_interval_minutes' => 60], '19:00', '22:00');
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->timeRange('20:00', '22:00')->create();

        $this->getJson('/api/v1/availability?date='.self::WEDNESDAY.'&party_size=2')
            ->assertOk()
            ->assertJsonPath('data.slots', ['19:00', '22:00']);
    }

    public function test_returns_an_empty_list_without_tables(): void
    {
        $this->getJson('/api/v1/availability?date='.self::WEDNESDAY.'&party_size=4')
            ->assertOk()
            ->assertJsonPath('data.slots', []);
    }

    public function test_invalid_parameters_return_422(): void
    {
        $this->getJson('/api/v1/availability?date=14-10-2026&party_size=0')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['date', 'party_size']]]);

        $this->getJson('/api/v1/availability')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_dates_outside_the_booking_window_are_rejected(): void
    {
        $this->createTable([2]);

        $this->getJson('/api/v1/availability?date=2026-10-11&party_size=2')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DATE_OUT_OF_RANGE');

        $this->getJson('/api/v1/availability?date=2026-11-12&party_size=2')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DATE_OUT_OF_RANGE');
    }

    public function test_never_exposes_tables(): void
    {
        $table = $this->createTable([2], ['name' => 'Tavolo segreto']);

        $response = $this->getJson('/api/v1/availability?date='.self::WEDNESDAY.'&party_size=2')->assertOk();

        $this->assertStringNotContainsString('Tavolo segreto', $response->getContent());
        $this->assertStringNotContainsString('restaurant_table', $response->getContent());
        $this->assertSame(['date', 'party_size', 'slots'], array_keys($response->json('data')));
        $this->assertNotNull($table);
    }
}
