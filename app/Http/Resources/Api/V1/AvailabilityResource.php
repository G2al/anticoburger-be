<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{date: string, party_size: int, slots: list<string>} $resource
 */
class AvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource['date'],
            'party_size' => $this->resource['party_size'],
            'slots' => $this->resource['slots'],
        ];
    }
}
