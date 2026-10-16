<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Only the public API is exposed cross-origin, and only to the origins listed
| in CORS_ALLOWED_ORIGINS (comma separated). CORS is not an authentication
| mechanism: it only tells browsers which frontends may read the responses.
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-Reservation-Token'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
