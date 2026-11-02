<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CalendarDayStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * "message" is the title of the whole-restaurant block that closes the day, only for
 * closed days; null otherwise (e.g. weekly closing day).
 *
 * @property array{month: string, party_size: int, days: array<string, CalendarDayStatus>, messages: array<string, string>} $resource
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
            $days[] = [
                'date' => $date,
                'status' => $status->value,
                'message' => $status === CalendarDayStatus::Closed ? ($this->resource['messages'][$date] ?? null) : null,
            ];
        }

        return [
            'month' => $this->resource['month'],
            'party_size' => $this->resource['party_size'],
            'days' => $days,
        ];
    }
}
