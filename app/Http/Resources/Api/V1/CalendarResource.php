<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CalendarDayStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{month: string, party_size: int, days: array<string, CalendarDayStatus>} $resource
 */
class CalendarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $days = [];

        foreach ($this->resource['days'] as $date => $status) {
            $days[] = ['date' => $date, 'status' => $status->value];
        }

        return [
            'month' => $this->resource['month'],
            'party_size' => $this->resource['party_size'],
            'days' => $days,
        ];
    }
}
