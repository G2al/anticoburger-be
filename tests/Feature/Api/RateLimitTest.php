<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
    }

    public function test_read_endpoints_are_rate_limited_per_ip(): void
    {
        config(['booking.rate_limits.read_per_minute' => 3]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->getJson('/api/v1/booking-settings')->assertOk();
        }

        $this->getJson('/api/v1/booking-settings')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After');

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->getJson('/api/v1/booking-settings')
            ->assertOk();
    }

    public function test_booking_is_limited_per_phone_without_blocking_other_customers_on_the_same_ip(): void
    {
        config([
            'booking.rate_limits.booking_per_minute_per_phone' => 2,
            'booking.rate_limits.booking_per_minute_per_ip' => 10,
        ]);

        $payload = fn (string $phone) => [
            'date' => self::WEDNESDAY,
            'time' => '20:00',
            'party_size' => 7,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'phone' => $phone,
        ];

        $this->postJson('/api/v1/reservations', $payload('3330000001'))->assertStatus(422);
        $this->postJson('/api/v1/reservations', $payload('+39 333 0000001'))->assertStatus(422);
        $this->postJson('/api/v1/reservations', $payload('3330000001'))->assertStatus(429);

        $this->postJson('/api/v1/reservations', $payload('3330000002'))->assertStatus(422);
    }

    public function test_booking_is_capped_per_ip(): void
    {
        config([
            'booking.rate_limits.booking_per_minute_per_phone' => 10,
            'booking.rate_limits.booking_per_minute_per_ip' => 3,
        ]);

        foreach (['3330000001', '3330000002', '3330000003'] as $phone) {
            $this->postJson('/api/v1/reservations', ['phone' => $phone])->assertStatus(422);
        }

        $this->postJson('/api/v1/reservations', ['phone' => '3330000004'])->assertStatus(429);
    }

    public function test_token_protected_endpoints_are_rate_limited(): void
    {
        config(['booking.rate_limits.access_per_minute' => 2]);

        $this->getJson('/api/v1/reservations/x', ['X-Reservation-Token' => 'a'])->assertNotFound();
        $this->postJson('/api/v1/reservations/x/cancel', [], ['X-Reservation-Token' => 'a'])->assertNotFound();
        $this->getJson('/api/v1/reservations/x', ['X-Reservation-Token' => 'a'])->assertStatus(429);
    }
}
