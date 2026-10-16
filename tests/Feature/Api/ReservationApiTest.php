<?php

namespace Tests\Feature\Api;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'date' => self::WEDNESDAY,
            'time' => '20:30',
            'party_size' => 2,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'phone' => '3331234567',
            'email' => 'mario@example.com',
            'notes' => 'Preferibilmente zona tranquilla',
            ...$overrides,
        ];
    }

    public function test_creates_a_confirmed_reservation(): void
    {
        $table = $this->createTable([2, 4], ['duration_minutes' => 30]);

        $response = $this->postJson('/api/v1/reservations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.date', self::WEDNESDAY)
            ->assertJsonPath('data.time', '20:30')
            ->assertJsonPath('data.party_size', 2)
            ->assertJsonPath('data.starts_at', '2026-10-14T20:30:00+02:00')
            ->assertJsonPath('data.ends_at', '2026-10-14T21:00:00+02:00')
            ->assertJsonPath('data.cancelled_at', null);

        $data = $response->json('data');
        $reservation = Reservation::query()->sole();

        $this->assertSame($reservation->public_reference, $data['reference']);
        $this->assertTrue(Str::isUuid($data['reference']));
        $this->assertSame(48, strlen($data['cancellation_token']));
        $this->assertTrue($reservation->matchesCancellationToken($data['cancellation_token']));
        $this->assertSame($table->id, $reservation->restaurant_table_id);
    }

    public function test_response_does_not_leak_internal_data(): void
    {
        $this->createTable([2], ['name' => 'Tavolo segreto']);

        $response = $this->postJson('/api/v1/reservations', $this->payload())->assertCreated();
        $body = $response->getContent();
        $reservation = Reservation::query()->sole();

        foreach (['restaurant_table', 'Tavolo segreto', 'cancellation_token_hash', 'phone_normalized', 'occupied_until', 'created_via', '"id"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }

        $this->assertStringNotContainsString($reservation->cancellation_token_hash, $body);
    }

    public function test_table_chosen_by_the_client_is_ignored(): void
    {
        $small = $this->createTable([2], ['name' => 'Piccolo']);
        $large = $this->createTable([2, 4, 6], ['name' => 'Grande']);

        $this->postJson('/api/v1/reservations', $this->payload(['restaurant_table_id' => $large->id]))->assertCreated();

        $this->assertSame($small->id, Reservation::query()->sole()->restaurant_table_id);
    }

    public function test_validation_errors_return_422(): void
    {
        $this->createTable([2]);

        $this->postJson('/api/v1/reservations', $this->payload([
            'date' => '2026/10/14',
            'time' => '25:00',
            'party_size' => 'due',
            'first_name' => '',
            'last_name' => str_repeat('x', 101),
            'phone' => '12',
            'email' => 'not-an-email',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['date', 'time', 'party_size', 'first_name', 'last_name', 'phone', 'email'], 'error.fields');

        $this->postJson('/api/v1/reservations', $this->payload(['email' => null, 'notes' => null, 'first_name' => "D'Angelo-Rossi José"]))
            ->assertCreated();
    }

    public function test_unavailable_slot_returns_409(): void
    {
        $this->createTable([2]);
        $this->postJson('/api/v1/reservations', $this->payload(['phone' => '3330000001']))->assertCreated();

        $this->postJson('/api/v1/reservations', $this->payload(['phone' => '3330000002']))
            ->assertStatus(409)
            ->assertExactJson([
                'error' => [
                    'code' => 'SLOT_NOT_AVAILABLE',
                    'message' => "L'orario selezionato non è più disponibile.",
                ],
            ]);
    }

    public function test_business_rule_errors_have_stable_codes(): void
    {
        $this->createTable([2]);
        $this->createTable([2]);

        $this->postJson('/api/v1/reservations', $this->payload(['party_size' => 7]))
            ->assertStatus(422)->assertJsonPath('error.code', 'PARTY_SIZE_NOT_SUPPORTED');

        $this->postJson('/api/v1/reservations', $this->payload(['date' => '2026-11-20']))
            ->assertStatus(422)->assertJsonPath('error.code', 'DATE_OUT_OF_RANGE');

        $this->postJson('/api/v1/reservations', $this->payload())->assertCreated();
        $this->postJson('/api/v1/reservations', $this->payload())
            ->assertStatus(409)->assertJsonPath('error.code', 'DUPLICATE_RESERVATION');
    }

    public function test_shows_the_reservation_only_with_the_right_token(): void
    {
        $this->createTable([2]);
        $created = $this->postJson('/api/v1/reservations', $this->payload())->json('data');
        $url = '/api/v1/reservations/'.$created['reference'];

        $this->getJson($url, ['X-Reservation-Token' => $created['cancellation_token']])
            ->assertOk()
            ->assertJsonPath('data.reference', $created['reference'])
            ->assertJsonPath('data.first_name', 'Mario')
            ->assertJsonMissingPath('data.cancellation_token');

        $this->getJson($url)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'RESERVATION_TOKEN_MISSING')
            ->assertJsonMissingPath('data');

        $this->getJson($url, ['X-Reservation-Token' => 'wrong-token'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESERVATION_NOT_FOUND')
            ->assertDontSee('Mario');
    }

    public function test_unknown_reservations_return_404(): void
    {
        $this->getJson('/api/v1/reservations/'.Str::uuid(), ['X-Reservation-Token' => 'anything'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESERVATION_NOT_FOUND');

        $this->getJson('/api/v1/reservations/not-a-uuid', ['X-Reservation-Token' => 'anything'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESERVATION_NOT_FOUND');
    }

    public function test_cancels_with_the_right_token_idempotently(): void
    {
        $this->createTable([2]);
        $created = $this->postJson('/api/v1/reservations', $this->payload())->json('data');
        $url = '/api/v1/reservations/'.$created['reference'].'/cancel';

        $this->postJson($url, [], ['X-Reservation-Token' => 'wrong'])->assertNotFound();
        $this->postJson($url)->assertStatus(401);
        $this->assertSame(ReservationStatus::Confirmed, Reservation::query()->sole()->status);

        $this->postJson($url, [], ['X-Reservation-Token' => $created['cancellation_token']])
            ->assertOk()
            ->assertJsonPath('data.reference', $created['reference'])
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson($url, [], ['X-Reservation-Token' => $created['cancellation_token']])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, Reservation::query()->count());
        $this->assertNotNull(Reservation::query()->sole()->cancelled_at);

        $this->getJson('/api/v1/availability?date='.self::WEDNESDAY.'&party_size=2')
            ->assertJsonFragment(['20:30']);
    }

    public function test_unknown_api_routes_return_json_404(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_unexpected_errors_do_not_leak_details_in_production(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/v1/test-failure', fn () => throw new RuntimeException('SQLSTATE secret detail'));

        $response = $this->getJson('/api/v1/test-failure')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'SERVER_ERROR');

        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('.php', $response->getContent());
    }

    public function test_cors_allows_only_configured_origins(): void
    {
        $this->call('OPTIONS', '/api/v1/availability', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');

        $response = $this->call('OPTIONS', '/api/v1/availability', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
