<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Reservations\PhoneNumberNormalizer;
use GuzzleHttp\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebPush::class, fn () => new WebPush(
            ['VAPID' => [
                'subject' => config('push.vapid.subject'),
                'publicKey' => config('push.vapid.public_key'),
                'privateKey' => config('push.vapid.private_key'),
            ]],
            ['TTL' => config('push.ttl'), 'urgency' => 'high'],
            new Client(['timeout' => config('push.timeout'), 'connect_timeout' => config('push.timeout')]),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();
        $this->configureApiRateLimits();

        Gate::define('receive-push-notifications', fn (User $user) => $user->isAdmin());
    }

    private function configureTrustedProxies(): void
    {
        $trustedProxies = config('booking.trusted_proxies');

        if (filled($trustedProxies)) {
            TrustProxies::at($trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)));
        }
    }

    /**
     * Booking creation is limited per phone number (so customers sharing an IP do not
     * block each other) and, more loosely, per IP.
     */
    private function configureApiRateLimits(): void
    {
        RateLimiter::for('booking-read', fn (Request $request) => Limit::perMinute(config('booking.rate_limits.read_per_minute'))
            ->by('read|'.$request->ip()));

        RateLimiter::for('booking-write', function (Request $request) {
            $phone = is_string($request->input('phone')) ? $request->input('phone') : '';
            $phoneKey = app(PhoneNumberNormalizer::class)->normalize($phone) ?? $request->ip();

            return [
                Limit::perMinute(config('booking.rate_limits.booking_per_minute_per_phone'))->by('write-phone|'.$phoneKey),
                Limit::perMinute(config('booking.rate_limits.booking_per_minute_per_ip'))->by('write-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('booking-access', fn (Request $request) => Limit::perMinute(config('booking.rate_limits.access_per_minute'))
            ->by('access|'.$request->ip()));
    }
}
