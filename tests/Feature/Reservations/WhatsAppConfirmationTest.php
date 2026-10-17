<?php

namespace Tests\Feature\Reservations;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\Reservations\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class WhatsAppConfirmationTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    private const WAHA_URL = 'http://waha.test';

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->table = $this->createTable([2], ['name' => 'Tavolo 2']);
        config([
            'waha.url' => self::WAHA_URL,
            'waha.api_key' => 'plain-api-key',
            'waha.session' => 'anticoburger',
            'booking.frontend_url' => 'https://app.anticoburger.it',
        ]);
    }

    private function fakeWaha(bool $numberExists = true): void
    {
        Http::fake([
            self::WAHA_URL.'/api/contacts/check-exists*' => Http::response(
                $numberExists ? ['numberExists' => true, 'chatId' => '38216635830523@lid'] : ['numberExists' => false]
            ),
            self::WAHA_URL.'/api/sendText' => Http::response(['id' => 'true_38216635830523@lid_ABC'], 201),
        ]);
    }

    public function test_online_booking_sends_the_confirmation_with_the_management_link(): void
    {
        $this->fakeWaha();

        $created = $this->postJson('/api/v1/reservations', $this->reservationData(['phone' => '333 123 4567']))
            ->assertCreated()
            ->json('data');

        Http::assertSentCount(2);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_starts_with($request->url(), self::WAHA_URL.'/api/contacts/check-exists')
            && $request['phone'] === '393331234567'
            && $request['session'] === 'anticoburger'
            && $request->hasHeader('X-Api-Key', 'plain-api-key'));

        Http::assertSent(function (Request $request) use ($created) {
            if ($request->url() !== self::WAHA_URL.'/api/sendText') {
                return false;
            }

            $this->assertSame('anticoburger', $request['session']);
            $this->assertSame('38216635830523@lid', $request['chatId']);
            $this->assertTrue($request->hasHeader('X-Api-Key', 'plain-api-key'));
            $this->assertSame(implode("\n", [
                'Ciao Mario, la tua prenotazione da *Antico Burger* è confermata ✅',
                '',
                '📅 Mercoledì 14 ottobre alle 20:00',
                '👥 2 persone',
                '',
                'Per vedere o annullare la prenotazione:',
                "https://app.anticoburger.it/prenotazione#ref={$created['reference']}&token={$created['cancellation_token']}",
                '',
                'A presto!',
            ]), $request['text']);

            return true;
        });
    }

    public function test_numbers_without_whatsapp_are_skipped(): void
    {
        $this->fakeWaha(numberExists: false);

        $this->postJson('/api/v1/reservations', $this->reservationData(['phone' => '06 1234567']))->assertCreated();

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/api/sendText'));
    }

    public function test_waha_failures_never_affect_the_booking_and_never_log_personal_data(): void
    {
        Log::spy();
        Http::fake([
            self::WAHA_URL.'/api/contacts/check-exists*' => Http::response(['numberExists' => true, 'chatId' => '393331234567@c.us']),
            self::WAHA_URL.'/api/sendText' => Http::response(['message' => 'Session is not working'], 422),
        ]);

        $created = $this->postJson('/api/v1/reservations', $this->reservationData())->assertCreated()->json('data');

        $this->assertSame(1, Reservation::query()->count());
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($created) {
            $serialized = $message.json_encode($context);

            return $context['status'] === 422
                && ! str_contains($serialized, $created['cancellation_token'])
                && ! str_contains($serialized, '3331234567');
        });
    }

    public function test_unreachable_waha_never_affects_the_booking(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertCreated();

        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_nothing_is_sent_when_waha_is_not_configured(): void
    {
        config(['waha.api_key' => null]);
        Http::fake();

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertCreated();

        Http::assertNothingSent();
    }

    public function test_reservations_created_from_the_admin_panel_get_no_message(): void
    {
        Http::fake();

        app(ReservationService::class)->createByAdmin($this->reservationData());

        Http::assertNothingSent();
    }

    public function test_failed_bookings_get_no_message(): void
    {
        Http::fake();
        Reservation::factory()->on($this->table, self::WEDNESDAY, '20:00')->create();

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertStatus(409);

        Http::assertNothingSent();
    }
}
