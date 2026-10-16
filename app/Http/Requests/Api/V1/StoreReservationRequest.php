<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Reservations\PhoneNumberNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'between:1,'.config('booking.max_party_size')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', $this->validPhoneNumber()],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'data',
            'time' => 'ora',
            'party_size' => 'numero di persone',
            'first_name' => 'nome',
            'last_name' => 'cognome',
            'phone' => 'telefono',
            'email' => 'email',
            'notes' => 'note',
        ];
    }

    private function validPhoneNumber(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || app(PhoneNumberNormalizer::class)->normalize($value) === null) {
                $fail('Il numero di telefono non è valido.');
            }
        };
    }
}
