<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panel administrator (used only by AdminUserSeeder)
    |--------------------------------------------------------------------------
    |
    | Set these in .env, run `php artisan db:seed --class=AdminUserSeeder --force`,
    | then remove ADMIN_PASSWORD from .env.
    |
    */

    'email' => env('ADMIN_EMAIL', 'admin@anticoburger.it'),

    'name' => env('ADMIN_NAME', 'Admin'),

    'password' => env('ADMIN_PASSWORD'),

];
