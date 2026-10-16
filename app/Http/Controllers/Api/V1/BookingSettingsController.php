<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookingSettingsResource;
use App\Models\BookingSettings;
use App\Services\Reservations\AvailabilityService;

class BookingSettingsController extends Controller
{
    public function __invoke(AvailabilityService $availability): BookingSettingsResource
    {
        return new BookingSettingsResource([
            'settings' => BookingSettings::current(),
            'party_sizes' => $availability->bookablePartySizes(),
        ]);
    }
}
