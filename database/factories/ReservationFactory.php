<?php

namespace Database\Factories;

use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = BookingTime::at(BookingTime::today()->addDays(fake()->numberBetween(1, 20)), '20:00');

        return [
            'restaurant_table_id' => RestaurantTable::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '333'.fake()->numerify('#######'),
            'phone_normalized' => fn (array $attributes) => '+39'.preg_replace('/\D/', '', $attributes['phone']),
            'email' => fake()->optional()->safeEmail(),
            'notes' => null,
            'party_size' => 2,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(60),
            'occupied_until' => $startsAt->addMinutes(60),
            'status' => ReservationStatus::Confirmed,
            'created_via' => ReservationChannel::Public,
        ];
    }

    /**
     * Places the reservation on a table at a local date and time, using the
     * table's current duration and turnaround.
     */
    public function on(RestaurantTable $table, string $localDate, string $localTime): static
    {
        $startsAt = BookingTime::at(BookingTime::date($localDate), $localTime);

        return $this->state([
            'restaurant_table_id' => $table->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($table->duration_minutes),
            'occupied_until' => $startsAt->addMinutes($table->duration_minutes + $table->turnaround_minutes),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
