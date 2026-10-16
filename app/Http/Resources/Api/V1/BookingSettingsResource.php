<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BookingSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public booking rules. Party sizes are aggregated across active tables and never
 * reveal individual tables; min/max are null when nothing is bookable and do not
 * imply that every size in between is accepted (see party_sizes).
 *
 * @property array{settings: BookingSettings, party_sizes: list<int>} $resource
 */
class BookingSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'];
        $partySizes = $this->resource['party_sizes'];

        return [
            'timezone' => config('booking.timezone'),
            'min_lead_minutes' => $settings->min_lead_minutes,
            'max_advance_days' => $settings->max_advance_days,
            'min_party_size' => $partySizes === [] ? null : min($partySizes),
            'max_party_size' => $partySizes === [] ? null : max($partySizes),
            'party_sizes' => $partySizes,
        ];
    }
}
