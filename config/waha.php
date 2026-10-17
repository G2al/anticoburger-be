<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WAHA (WhatsApp HTTP API)
    |--------------------------------------------------------------------------
    |
    | Used to send the WhatsApp confirmation to customers who book online.
    | Sending is disabled while the URL or the API key are empty. The API key
    | is the plain key (WAHA may store it hashed on its side).
    |
    */

    'url' => env('WAHA_URL'),

    'api_key' => env('WAHA_API_KEY'),

    'session' => env('WAHA_SESSION', 'anticoburger'),

    'timeout' => (int) env('WAHA_TIMEOUT', 10),

];
