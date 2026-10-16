<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'party_size' => ['required', 'integer', 'between:1,'.config('booking.max_party_size')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'month' => 'mese',
            'party_size' => 'numero di persone',
        ];
    }
}
