<?php

namespace Database\Factories;

use App\Models\RestaurantTable;
use App\Models\TableSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSchedule>
 */
class TableScheduleFactory extends Factory
{
    protected $model = TableSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_table_id' => RestaurantTable::factory(),
            'day_of_week' => fake()->numberBetween(1, 7),
            'first_booking_time' => '19:00:00',
            'last_booking_time' => '23:00:00',
        ];
    }
}
