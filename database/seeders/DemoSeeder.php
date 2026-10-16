<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use App\Services\Reservations\RestaurantTableService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data: `php artisan db:seed --class=DemoSeeder`.
 * It is not called by DatabaseSeeder and never overwrites existing tables
 * (tables whose name already exists are skipped).
 */
class DemoSeeder extends Seeder
{
    public function run(RestaurantTableService $tables): void
    {
        $dinnerEveryDayButMonday = array_map(
            fn (int $day) => ['day_of_week' => $day, 'first_booking_time' => '19:00', 'last_booking_time' => '23:00'],
            [2, 3, 4, 5, 6, 7],
        );
        $weekendLunch = array_map(
            fn (int $day) => ['day_of_week' => $day, 'first_booking_time' => '12:00', 'last_booking_time' => '14:30'],
            [6, 7],
        );

        $demoTables = [
            ['name' => 'Tavolo 1', 'party_sizes' => [2], 'duration_minutes' => 60],
            ['name' => 'Tavolo 2', 'party_sizes' => [2, 3, 4], 'duration_minutes' => 75],
            ['name' => 'Tavolo 3', 'party_sizes' => [2, 3, 4], 'duration_minutes' => 75],
            ['name' => 'Tavolo 4', 'party_sizes' => [5, 6], 'duration_minutes' => 90, 'turnaround_minutes' => 10],
            ['name' => 'Tavolo 5', 'party_sizes' => [6, 8, 10], 'duration_minutes' => 120, 'turnaround_minutes' => 15],
        ];

        foreach ($demoTables as $demoTable) {
            if (RestaurantTable::query()->where('name', $demoTable['name'])->exists()) {
                continue;
            }

            $tables->create([
                'duration_minutes' => 60,
                'slot_interval_minutes' => 30,
                'turnaround_minutes' => 0,
                'assignment_priority' => 0,
                'is_active' => true,
                'schedules' => [...$dinnerEveryDayButMonday, ...$weekendLunch],
                ...$demoTable,
            ]);
        }
    }
}
