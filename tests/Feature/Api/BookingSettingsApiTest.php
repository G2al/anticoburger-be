<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class BookingSettingsApiTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    public function test_public_settings_aggregate_party_sizes_of_active_tables(): void
    {
        $this->createTable([2, 4]);
        $this->createTable([6, 8]);
        $this->createTable([12], ['is_active' => false]);

        $this->getJson('/api/v1/booking-settings')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'timezone' => 'Europe/Rome',
                    'min_lead_minutes' => 30,
                    'max_advance_days' => 30,
                    'min_party_size' => 2,
                    'max_party_size' => 8,
                    'party_sizes' => [2, 4, 6, 8],
                ],
            ]);
    }

    public function test_party_size_range_is_null_without_bookable_tables(): void
    {
        $this->getJson('/api/v1/booking-settings')
            ->assertOk()
            ->assertJsonPath('data.min_party_size', null)
            ->assertJsonPath('data.max_party_size', null)
            ->assertJsonPath('data.party_sizes', []);
    }

    public function test_settings_changes_are_reflected(): void
    {
        $this->updateSettings(['min_lead_minutes' => 0, 'max_advance_days' => 60]);

        $this->getJson('/api/v1/booking-settings')
            ->assertJsonPath('data.min_lead_minutes', 0)
            ->assertJsonPath('data.max_advance_days', 60);
    }
}
