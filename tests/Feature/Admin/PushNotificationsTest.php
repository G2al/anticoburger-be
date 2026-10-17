<?php

namespace Tests\Feature\Admin;

use App\Events\PublicReservationCreated;
use App\Filament\Widgets\PushNotificationsWidget;
use App\Models\PushSubscription;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Notifications\AdminPushNotifier;
use App\Services\Reservations\ReservationService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        config([
            'push.vapid.public_key' => 'test-public-key',
            'push.vapid.private_key' => 'test-private-key',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionBody(string $endpoint = 'https://fcm.googleapis.com/fcm/send/device-1'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPublicKeyOfTheBrowser', 'auth' => 'AuthSecret'],
            'content_encoding' => 'aes128gcm',
        ];
    }

    private function subscribe(User $user, string $endpoint): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'public_key' => 'BPublicKeyOfTheBrowser',
            'auth_token' => 'AuthSecret',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    /**
     * Replaces the WebPush client; each queued notification gets the given status code.
     *
     * @param  array<string, int>  $statusByEndpoint
     * @param  array<int, array{endpoint: string, payload: array<string, string>}>  $sent
     */
    private function fakeWebPush(array $statusByEndpoint = [], ?array &$sent = null): MockInterface
    {
        $sent = [];
        $queued = [];
        $mock = Mockery::mock(WebPush::class);

        $mock->shouldReceive('queueNotification')->andReturnUsing(
            function (Subscription $subscription, string $payload) use (&$queued, &$sent) {
                $queued[] = $subscription->getEndpoint();
                $sent[] = ['endpoint' => $subscription->getEndpoint(), 'payload' => json_decode($payload, true)];
            }
        );

        $mock->shouldReceive('flush')->andReturnUsing(function () use (&$queued, $statusByEndpoint) {
            foreach ($queued as $endpoint) {
                $status = $statusByEndpoint[$endpoint] ?? 201;

                yield new MessageSentReport(new Request('POST', $endpoint), new Response($status), $status < 300, 'status '.$status);
            }
        });

        $this->app->instance(WebPush::class, $mock);

        return $mock;
    }

    public function test_only_authenticated_admins_can_register_a_device(): void
    {
        $this->postJson('/admin/push-subscriptions', $this->subscriptionBody())->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/admin/push-subscriptions', $this->subscriptionBody())
            ->assertForbidden();

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_admin_registers_a_device_once(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/admin/push-subscriptions', $this->subscriptionBody())->assertCreated();
        $this->actingAs($admin)->postJson('/admin/push-subscriptions', $this->subscriptionBody())->assertOk();

        $subscription = PushSubscription::query()->sole();
        $this->assertSame($admin->id, $subscription->user_id);
        $this->assertSame('https://fcm.googleapis.com/fcm/send/device-1', $subscription->endpoint);
        $this->assertSame('aes128gcm', $subscription->content_encoding);

        $this->actingAs($admin)
            ->postJson('/admin/push-subscriptions', ['endpoint' => 'http://insecure.example', 'keys' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_admin_removes_only_its_own_device(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $mine = $this->subscribe($admin, 'https://push.example/mine');
        $theirs = $this->subscribe($otherAdmin, 'https://push.example/theirs');

        $this->actingAs($admin)->deleteJson('/admin/push-subscriptions', ['endpoint' => $theirs->endpoint])->assertNoContent();
        $this->actingAs($admin)->deleteJson('/admin/push-subscriptions', ['endpoint' => $mine->endpoint])->assertNoContent();

        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
    }

    public function test_public_booking_notifies_every_admin_device_with_customer_details(): void
    {
        $this->createTable([2], ['name' => 'Tavolo 2']);
        $admin = User::factory()->admin()->create();
        $this->subscribe($admin, 'https://push.example/admin-phone');
        $this->subscribe($admin, 'https://push.example/admin-desktop');
        $this->subscribe(User::factory()->create(), 'https://push.example/not-an-admin');
        $this->fakeWebPush(sent: $sent);

        $this->postJson('/api/v1/reservations', $this->reservationData(['party_size' => 2, 'phone' => '333 123 4567']))
            ->assertCreated();

        $reservation = Reservation::query()->sole();

        $this->assertEqualsCanonicalizing(
            ['https://push.example/admin-phone', 'https://push.example/admin-desktop'],
            array_column($sent, 'endpoint'),
        );
        $this->assertSame([
            'title' => 'Nuova prenotazione',
            'body' => "Mario Rossi · 2 persone\nMercoledì 14 ottobre alle 20:00 · Tavolo 2\nTel. 333 123 4567",
            'url' => url("/admin/prenotazioni/{$reservation->id}"),
            'tag' => 'reservation-'.$reservation->id,
        ], $sent[0]['payload']);
    }

    public function test_only_public_reservations_trigger_the_notification(): void
    {
        Event::fake([PublicReservationCreated::class]);
        $this->createTable([2]);
        $reservations = app(ReservationService::class);

        $reservations->createByAdmin($this->reservationData(['time' => '19:00']));
        Event::assertNotDispatched(PublicReservationCreated::class);

        $reservations->createPublic($this->reservationData(['time' => '21:00']));
        Event::assertDispatched(PublicReservationCreated::class, 1);
    }

    public function test_failed_bookings_do_not_notify(): void
    {
        $table = $this->createTable([2]);
        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
        $this->subscribe(User::factory()->admin()->create(), 'https://push.example/admin');
        $this->fakeWebPush(sent: $sent);

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertStatus(409);

        $this->assertSame([], $sent);
    }

    public function test_expired_devices_are_removed_and_failures_never_break_the_booking(): void
    {
        $this->createTable([2]);
        $admin = User::factory()->admin()->create();
        $expired = $this->subscribe($admin, 'https://push.example/uninstalled');
        $working = $this->subscribe($admin, 'https://push.example/working');
        $flaky = $this->subscribe($admin, 'https://push.example/flaky');
        Log::spy();
        $this->fakeWebPush([
            'https://push.example/uninstalled' => 410,
            'https://push.example/flaky' => 500,
        ]);

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertCreated();

        $this->assertModelMissing($expired);
        $this->assertModelExists($working);
        $this->assertModelExists($flaky);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context['push_service'] === 'push.example')->once();
    }

    public function test_push_service_exceptions_are_swallowed(): void
    {
        $this->createTable([2]);
        $this->subscribe(User::factory()->admin()->create(), 'https://push.example/admin');
        $mock = Mockery::mock(WebPush::class);
        $mock->shouldReceive('queueNotification')->andThrow(new RuntimeException('network down'));
        $this->app->instance(WebPush::class, $mock);

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertCreated();

        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_nothing_is_sent_without_vapid_keys(): void
    {
        config(['push.vapid.private_key' => null]);
        $this->createTable([2]);
        $admin = User::factory()->admin()->create();
        $this->subscribe($admin, 'https://push.example/admin');
        $mock = Mockery::mock(WebPush::class);
        $mock->shouldNotReceive('queueNotification');
        $this->app->instance(WebPush::class, $mock);

        $this->postJson('/api/v1/reservations', $this->reservationData())->assertCreated();

        $this->actingAs($admin)->postJson('/admin/push-subscriptions/test')->assertStatus(503);
    }

    public function test_admin_can_send_a_test_notification_to_its_devices(): void
    {
        $admin = User::factory()->admin()->create();
        $this->subscribe($admin, 'https://push.example/admin-phone');
        $this->subscribe(User::factory()->admin()->create(), 'https://push.example/someone-else');
        $this->fakeWebPush(sent: $sent);

        $this->actingAs($admin)
            ->postJson('/admin/push-subscriptions/test')
            ->assertOk()
            ->assertJsonPath('data.sent', 1);

        $this->assertSame(['https://push.example/admin-phone'], array_column($sent, 'endpoint'));
        $this->assertSame('Notifiche attive', $sent[0]['payload']['title']);
    }

    public function test_dashboard_shows_the_push_widget_to_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(PushNotificationsWidget::class)
            ->assertOk()
            ->assertSee('Notifiche push')
            ->assertSee('Attiva notifiche');

        $this->get('/admin')->assertOk()->assertSee('anticoBurgerPush', escape: false)->assertSee('test-public-key');
    }

    public function test_the_container_builds_the_real_web_push_client_from_the_configuration(): void
    {
        $base64Url = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        config([
            'push.vapid.public_key' => $base64Url("\x04".random_bytes(64)),
            'push.vapid.private_key' => $base64Url(random_bytes(32)),
        ]);

        $webPush = app(WebPush::class);

        $this->assertInstanceOf(WebPush::class, $webPush);
        $this->assertSame(3600, $webPush->getDefaultOptions()['TTL']);
        $this->assertSame('high', $webPush->getDefaultOptions()['urgency']);
    }

    public function test_real_vapid_signature_and_payload_encryption(): void
    {
        $browserKey = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        if ($browserKey === false) {
            $this->markTestSkipped('OpenSSL cannot create EC keys here (on Windows set OPENSSL_CONF before starting PHP).');
        }

        $this->artisan('app:vapid-keys')->assertSuccessful();

        $details = openssl_pkey_get_details($browserKey)['ec'];
        $base64Url = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $vapid = VAPID::createVapidKeys();
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(201)]));
        $handler->push(Middleware::history($history));

        $webPush = new WebPush(
            ['VAPID' => ['subject' => 'mailto:info@anticoburger.it', 'publicKey' => $vapid['publicKey'], 'privateKey' => $vapid['privateKey']]],
            ['TTL' => 3600, 'urgency' => 'high'],
            new Client(['handler' => $handler]),
        );
        $this->app->instance(WebPush::class, $webPush);
        config(['push.vapid.public_key' => $vapid['publicKey'], 'push.vapid.private_key' => $vapid['privateKey']]);

        $admin = User::factory()->admin()->create();
        PushSubscription::query()->create([
            'user_id' => $admin->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/real-device',
            'public_key' => $base64Url("\x04".$details['x'].$details['y']),
            'auth_token' => $base64Url(random_bytes(16)),
            'content_encoding' => 'aes128gcm',
        ]);

        $this->assertSame(1, app(AdminPushNotifier::class)->sendTest($admin));

        $request = $history[0]['request'];
        $this->assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertSame('3600', $request->getHeaderLine('TTL'));
        $this->assertStringNotContainsString('Notifiche attive', (string) $request->getBody());
    }
}
