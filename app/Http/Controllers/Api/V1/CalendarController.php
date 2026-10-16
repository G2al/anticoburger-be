<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CalendarRequest;
use App\Http\Resources\Api\V1\CalendarResource;
use App\Models\BookingSettings;
use App\Services\Reservations\AvailabilityService;
use App\Support\BookingTime;

class CalendarController extends Controller
{
    /**
     * Informative only: the final booking always runs every check again.
     */
    public function __invoke(CalendarRequest $request, AvailabilityService $availability): CalendarResource
    {
        $month = $request->validated('month');
        $partySize = (int) $request->validated('party_size');

        return new CalendarResource([
            'month' => $month,
            'party_size' => $partySize,
            'days' => $availability->publicCalendarFor(BookingTime::date($month.'-01'), $partySize, BookingSettings::current()),
        ]);
    }
}
