<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Restaurant timezone
    |--------------------------------------------------------------------------
    |
    | Every time typed by customers or configured by administrators (schedules,
    | blocks, requested booking times) is interpreted in this timezone. Instants
    | are always stored in UTC.
    |
    */

    'timezone' => env('BOOKING_TIMEZONE', 'Europe/Rome'),

    /*
    |--------------------------------------------------------------------------
    | Customer PWA
    |--------------------------------------------------------------------------
    |
    | Base URL of the customer booking app, used to build the personal
    | reservation management link sent to customers.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'https://app.anticoburger.it'),

    /*
    |--------------------------------------------------------------------------
    | Largest party size that can be configured on a table or requested
    |--------------------------------------------------------------------------
    */

    'max_party_size' => (int) env('BOOKING_MAX_PARTY_SIZE', 20),

    /*
    |--------------------------------------------------------------------------
    | Public calendar
    |--------------------------------------------------------------------------
    |
    | How many months after the current one can be requested from the public
    | calendar endpoint (one month per request).
    |
    */

    'calendar_max_months_ahead' => (int) env('BOOKING_CALENDAR_MAX_MONTHS_AHEAD', 12),

    /*
    |--------------------------------------------------------------------------
    | Public API rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | "read" covers booking settings and availability, "booking" covers the
    | creation of reservations (applied per IP + phone number, with a wider
    | cap per IP), "access" covers the token protected endpoints.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma separated proxy IPs (or "*") in front of the application, so that
    | rate limits use the real client IP instead of the proxy address.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'rate_limits' => [
        'read_per_minute' => (int) env('BOOKING_RATE_LIMIT_READ', 60),
        'booking_per_minute_per_phone' => (int) env('BOOKING_RATE_LIMIT_BOOKING_PER_PHONE', 5),
        'booking_per_minute_per_ip' => (int) env('BOOKING_RATE_LIMIT_BOOKING_PER_IP', 20),
        'access_per_minute' => (int) env('BOOKING_RATE_LIMIT_ACCESS', 30),
    ],

];
