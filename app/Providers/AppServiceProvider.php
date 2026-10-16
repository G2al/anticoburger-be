<?php

namespace App\Providers;

use App\Services\Reservations\PhoneNumberNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();
        $this->configureApiRateLimits();
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
