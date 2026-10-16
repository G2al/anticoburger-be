<?php

use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingSettingsController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:booking-read')->group(function () {
        Route::get('booking-settings', BookingSettingsController::class)->name('booking-settings');
        Route::get('availability', AvailabilityController::class)->name('availability');
        Route::get('calendar', CalendarController::class)->name('calendar');
    });

    Route::post('reservations', [ReservationController::class, 'store'])
        ->middleware('throttle:booking-write')
        ->name('reservations.store');

    Route::middleware('throttle:booking-access')->group(function () {
        Route::get('reservations/{reference}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::post('reservations/{reference}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });
});
