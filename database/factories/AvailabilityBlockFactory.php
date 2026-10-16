<?php

namespace Database\Factories;

use App\Enums\BlockScope;
use App\Models\AvailabilityBlock;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityBlock>
 */
class AvailabilityBlockFactory extends Factory
{
    protected $model = AvailabilityBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now(config('booking.timezone'))->addDays(fake()->numberBetween(1, 20))->toDateString();

        return [
            'title' => 'Chiusura '.fake()->word(),
            'scope' => BlockScope::Restaurant,
            'restaurant_table_id' => null,
            'starts_on' => $date,
            'ends_on' => $date,
            'is_all_day' => true,
            'start_time' => null,
            'end_time' => null,
            'is_active' => true,
        ];
    }

    public function forTable(RestaurantTable $table): static
    {
        return $this->state([
            'scope' => BlockScope::Table,
            'restaurant_table_id' => $table->id,
        ]);
    }

    public function between(string $startsOn, ?string $endsOn = null): static
    {
        return $this->state([
            'starts_on' => $startsOn,
            'ends_on' => $endsOn ?? $startsOn,
        ]);
    }

    public function timeRange(string $startTime, string $endTime): static
    {
        return $this->state([
            'is_all_day' => false,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
