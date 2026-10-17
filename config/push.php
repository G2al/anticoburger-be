<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Keys identifying this server to the browsers' push services. Generate
    | them once with `php artisan app:vapid-keys` and never change them:
    | new keys invalidate every existing subscription. The private key must
    | stay on the server; the public key is sent to the admin browsers.
    |
    */

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:info@anticoburger.it'),
    ],

    /*
    | Seconds a push service keeps an undelivered notification (e.g. phone off).
    */

    'ttl' => (int) env('PUSH_TTL', 3600),

    /*
    | Seconds to wait for a push service before giving up.
    */

    'timeout' => (int) env('PUSH_TIMEOUT', 10),

];
