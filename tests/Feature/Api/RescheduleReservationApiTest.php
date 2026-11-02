<?php

namespace Tests\Feature\Api;

use App\Enums\ReservationStatus;
use App\Events\PublicReservationRescheduled;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class RescheduleReservationApiTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    private RestaurantTable $small;

    private RestaurantTable $large;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->small = $this->createTable([2], ['name' => 'Piccolo', 'slot_interval_minutes' => 60], '19:00', '22:00');
        $this->large = $this->createTable([2, 4, 6], ['name' => 'Grande', 'slot_interval_minutes' => 60], '19:00', '22:00');
    }

    /**
     * @return array{reference: string, cancellation_token: string}
     */
    private function book(array $overrides = []): array
    {
        return $this->postJson('/api/v1/reservations', $this->reservationData($overrides))->assertCreated()->json('data');
    }

    private function reschedule(array $booking, array $body, ?string $token = null): TestResponse
    {
        return $this->postJson(
            "/api/v1/reservations/{$booking['reference']}/reschedule",
            $body,
            ['X-Reservation-Token' => $token ?? $booking['cancellation_token']],
        );
    }

    public function test_customer_moves_the_reservation_and_frees_the_old_slot(): void
    {
        $booking = $this->book(['time' => '20:00']);

        $this->reschedule($booking, ['date' => self::THURSDAY, 'time' => '21:00', 'party_size' => 2])
            ->assertOk()
            ->assertJsonPath('data.reference', $booking['reference'])
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.date', self::THURSDAY)
            ->assertJsonPath('data.time', '21:00')
            ->assertJsonMissingPath('data.cancellation_token');

        $reservation = Reservation::query()->sole();
        $this->assertSame($this->small->id, $reservation->restaurant_table_id);
        $this->assertSame('2026-10-15 21:00', $reservation->localStartsAt()->format('Y-m-d H:i'));

        $this->postJson('/api/v1/reservations', $this->reservationData(['time' => '20:00', 'phone' => '3330000099']))->assertCreated();
        $this->assertSame($this->small->id, Reservation::query()->where('phone_normalized', '+393330000099')->value('restaurant_table_id'));
    }

    public function test_changing_party_size_reassigns_the_best_table(): void
    {
        $booking = $this->book(['party_size' => 2]);
        $this->assertSame($this->small->id, Reservation::query()->sole()->restaurant_table_id);

        $this->reschedule($booking, ['date' => self::WEDNESDAY, 'time' => '20:00', 'party_size' => 6])
            ->assertOk()
            ->assertJsonPath('data.party_size', 6);

        $this->assertSame($this->large->id, Reservation::query()->sole()->restaurant_table_id);
    }

    public function test_unavailable_slot_keeps_the_original_reservation(): void
    {
        $booking = $this->book(['time' => '20:00']);
        Reservation::factory()->on($this->small, self::WEDNESDAY, '21:00')->create();
        Reservation::factory()->on($this->large, self::WEDNESDAY, '21:00')->create();

        $this->reschedule($booking, ['date' => self::WEDNESDAY, 'time' => '21:00', 'party_size' => 2])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE');

        $reservation = Reservation::query()->where('public_reference', $booking['reference'])->sole();
        $this->assertSame('20:00', $reservation->localStartsAt()->format('H:i'));
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_same_values_change_nothing_and_send_nothing(): void
    {
        $booking = $this->book(['time' => '20:00']);
        Event::fake([PublicReservationRescheduled::class]);

        $this->reschedule($booking, ['date' => self::WEDNESDAY, 'time' => '20:00', 'party_size' => 2])
            ->assertOk()
            ->assertJsonPath('data.time', '20:00');

        Event::assertNotDispatched(PublicReservationRescheduled::class);
    }

    public function test_public_rules_apply_but_ignore_the_reservation_itself(): void
    {
        $this->updateSettings(['max_reservations_per_phone_per_day' => 1]);
        $booking = $this->book(['time' => '20:00']);

        $this->reschedule($booking, ['date' => self::WEDNESDAY, 'time' => '21:00', 'party_size' => 2])->assertOk();

        $this->reschedule($booking, ['date' => self::TODAY, 'time' => '09:00', 'party_size' => 2])
            ->assertStatus(422)->assertJsonPath('error.code', 'BOOKING_TOO_SOON');
        $this->reschedule($booking, ['date' => '2026-12-31', 'time' => '20:00', 'party_size' => 2])
            ->assertStatus(422)->assertJsonPath('error.code', 'DATE_OUT_OF_RANGE');
        $this->reschedule($booking, ['date' => self::WEDNESDAY, 'time' => '20:00', 'party_size' => 7])
            ->assertStatus(422)->assertJsonPath('error.code', 'PARTY_SIZE_NOT_SUPPORTED');
        $this->reschedule($booking, ['date' => '14/10/2026', 'time' => '25:00'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $other = $this->book(['date' => self::THURSDAY, 'time' => '20:00', 'phone' => '3330000001']);
        $this->reschedule($booking, ['date' => self::THURSDAY, 'time' => '21:00', 'party_size' => 2])->assertOk();
        $this->assertNotNull($other);
    }

    public function test_daily_phone_limit_still_counts_the_other_reservations(): void
    {
        $this->updateSettings(['max_reservations_per_phone_per_day' => 1]);
        $wednesday = $this->book(['date' => self::WEDNESDAY, 'time' => '20:00']);
        $this->book(['date' => self::THURSDAY, 'time' => '20:00']);

        $this->reschedule($wednesday, ['date' => self::THURSDAY, 'time' => '21:00', 'party_size' => 2])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PHONE_DAILY_LIMIT_REACHED');
    }

    public function test_token_is_required_and_checked(): void
    {
        $booking = $this->book();
        $body = ['date' => self::THURSDAY, 'time' => '20:00', 'party_size' => 2];

        $this->postJson("/api/v1/reservations/{$booking['reference']}/reschedule", $body)
            ->assertStatus(401)->assertJsonPath('error.code', 'RESERVATION_TOKEN_MISSING');
        $this->reschedule($booking, $body, 'wrong-token')
            ->assertNotFound()->assertJsonPath('error.code', 'RESERVATION_NOT_FOUND');

        $this->assertSame('20:00', Reservation::query()->sole()->localStartsAt()->format('H:i'));
    }

    public function test_cancelled_or_started_reservations_cannot_be_changed(): void
    {
        $cancelled = $this->book(['time' => '19:00']);
        $this->postJson("/api/v1/reservations/{$cancelled['reference']}/cancel", [], ['X-Reservation-Token' => $cancelled['cancellation_token']])->assertOk();

        $this->reschedule($cancelled, ['date' => self::THURSDAY, 'time' => '20:00', 'party_size' => 2])
            ->assertStatus(409)->assertJsonPath('error.code', 'RESERVATION_CANCELLED');

        $started = $this->book(['time' => '20:00', 'phone' => '3330000002']);
        $this->freezeBookingClock('2026-10-14 20:10');

        $this->reschedule($started, ['date' => self::THURSDAY, 'time' => '20:00', 'party_size' => 2])
            ->assertStatus(409)->assertJsonPath('error.code', 'RESERVATION_NOT_MODIFIABLE');
    }

    public function test_customer_gets_the_whatsapp_confirmation_of_the_change(): void
    {
        $booking = $this->book(['time' => '20:00']);
        config(['waha.url' => 'http://waha.test', 'waha.api_key' => 'key', 'waha.session' => 'anticoburger']);
        Http::fake([
            'http://waha.test/api/contacts/check-exists*' => Http::response(['numberExists' => true, 'chatId' => '393331234567@c.us']),
            'http://waha.test/api/sendText' => Http::response([], 201),
        ]);

        $this->reschedule($booking, ['date' => self::THURSDAY, 'time' => '21:00', 'party_size' => 2])->assertOk();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/sendText')
            && str_contains($request['text'], 'è stata modificata ✅')
            && str_contains($request['text'], 'Giovedì 15 ottobre alle 21:00')
            && str_contains($request['text'], "#ref={$booking['reference']}&token={$booking['cancellation_token']}"));
    }
}
