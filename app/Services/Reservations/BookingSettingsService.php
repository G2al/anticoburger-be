<?php

namespace App\Services\Reservations;

use App\Models\BookingSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingSettingsService
{
    public const MAX_LEAD_MINUTES = 10080;

    public const MAX_ADVANCE_DAYS = 365;

    public const MAX_RESERVATIONS_PER_PHONE_PER_DAY = 50;

    public function __construct(private readonly BookingLock $bookingLock) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(array $data): BookingSettings
    {
        $validated = Validator::make($data, [
            'min_lead_minutes' => ['required', 'integer', 'between:0,'.self::MAX_LEAD_MINUTES],
            'max_advance_days' => ['required', 'integer', 'between:0,'.self::MAX_ADVANCE_DAYS],
            'max_reservations_per_phone_per_day' => ['required', 'integer', 'between:0,'.self::MAX_RESERVATIONS_PER_PHONE_PER_DAY],
            'prevent_duplicate_bookings' => ['required', 'boolean'],
        ], [], [
            'min_lead_minutes' => 'anticipo minimo',
            'max_advance_days' => 'anticipo massimo',
            'max_reservations_per_phone_per_day' => 'prenotazioni per telefono al giorno',
            'prevent_duplicate_bookings' => 'protezione duplicati',
        ])->validate();

        return $this->bookingLock->run(function (BookingSettings $settings) use ($validated) {
            $settings->update($validated);

            return $settings;
        });
    }
}
