<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AvailabilityRequest;
use App\Http\Resources\Api\V1\AvailabilityResource;
use App\Models\BookingSettings;
use App\Services\Reservations\AvailabilityService;
use App\Support\BookingTime;

class AvailabilityController extends Controller
{
    /**
     * Informative only: slots are not held. Every check runs again when booking.
     */
    public function __invoke(AvailabilityRequest $request, AvailabilityService $availability): AvailabilityResource
    {
        $date = $request->validated('date');
        $partySize = (int) $request->validated('party_size');

        return new AvailabilityResource([
            'date' => $date,
            'party_size' => $partySize,
            'slots' => $availability->publicSlotsFor(BookingTime::date($date), $partySize, BookingSettings::current()),
        ]);
    }
}
