<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Tavolo '.fake()->unique()->numberBetween(1, 100000),
            'duration_minutes' => 60,
            'slot_interval_minutes' => 30,
            'turnaround_minutes' => 0,
            'assignment_priority' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * @param  list<int>  $partySizes
     */
    public function withPartySizes(array $partySizes): static
    {
        return $this->afterCreating(function (RestaurantTable $table) use ($partySizes) {
            foreach ($partySizes as $partySize) {
                $table->allowedPartySizes()->create(['party_size' => $partySize]);
            }
        });
    }

    /**
     * Adds the same booking range to the given days of the week (1 = Monday ... 7 = Sunday).
     *
     * @param  list<int>  $days
     */
    public function withSchedule(string $firstBookingTime, string $lastBookingTime, array $days = [1, 2, 3, 4, 5, 6, 7]): static
    {
        return $this->afterCreating(function (RestaurantTable $table) use ($firstBookingTime, $lastBookingTime, $days) {
            foreach ($days as $day) {
                $table->schedules()->create([
                    'day_of_week' => $day,
                    'first_booking_time' => $firstBookingTime,
                    'last_booking_time' => $lastBookingTime,
                ]);
            }
        });
    }
}
