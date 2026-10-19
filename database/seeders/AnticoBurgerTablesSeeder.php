<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use App\Services\Reservations\RestaurantTableService;
use Illuminate\Database\Seeder;

/**
 * Real tables of Antico Burger: `php artisan db:seed --class=AnticoBurgerTablesSeeder --force`.
 *
 * Idempotent: creates the missing tables and updates the ones with the same name
 * (party sizes, duration and weekly schedule are reset to the values below).
 * Existing reservations are never touched.
 */
class AnticoBurgerTablesSeeder extends Seeder
{
    private const DURATION_MINUTES = 35;

    /**
     * Same as the duration: start times follow each other back to back.
     */
    private const SLOT_INTERVAL_MINUTES = self::DURATION_MINUTES;

    private const TURNAROUND_MINUTES = 0;

    /**
     * Booking start ranges applied to every table: day (1 = Monday ... 7 = Sunday) => [[first, last], ...].
     * The restaurant closes at midnight, so the last start is 23:25 (23:25 + 35 min = 00:00);
     * with 35 minute steps from 18:30 the last offered time is 23:10. Tuesday is closed and
     * on Saturday bookings start from 18:30 to 21:00 (last offered time 20:50).
     */
    private const WEEKLY_SCHEDULE = [
        1 => [['18:30', '23:25']],
        3 => [['18:30', '23:25']],
        4 => [['18:30', '23:25']],
        5 => [['18:30', '23:25']],
        6 => [['18:30', '21:00']],
        7 => [['18:30', '23:25']],
    ];

    /**
     * Table name => accepted party sizes.
     */
    private const TABLES = [
        'Tavolo 1' => [2],
        'Tavolo 2' => [2],
        'Tavolo 3' => [2],
        'Tavolo 4' => [2],
        'Tavolo 5' => [3, 4],
        'Tavolo 6' => [3, 4],
        'Tavolo 7' => [3, 4],
        'Tavolo 8' => [3, 4],
        'Tavolo 9' => [3, 4],
        'Tavolo 10' => [3, 4],
        'Tavolo 11' => [2],
        'Tavolo 12' => [2],
        'Tavolo 13' => [3, 4],
        'Tavolo 14' => [2],
        'Tavolo 15' => [2],
        'Tavolo 16' => [5, 6],
        'Tavolo 17' => [5, 6],
        'Tavolo 18' => [2],
        'Tavolo 19' => [2],
        'Tavolone' => [7, 8, 9, 10],
    ];

    public function run(RestaurantTableService $tables): void
    {
        foreach (self::TABLES as $name => $partySizes) {
            $data = [
                'name' => $name,
                'duration_minutes' => self::DURATION_MINUTES,
                'slot_interval_minutes' => self::SLOT_INTERVAL_MINUTES,
                'turnaround_minutes' => self::TURNAROUND_MINUTES,
                'assignment_priority' => 0,
                'is_active' => true,
                'party_sizes' => $partySizes,
                'schedules' => $this->schedules(),
            ];

            $existing = RestaurantTable::query()->where('name', $name)->first();

            $existing ? $tables->update($existing, $data) : $tables->create($data);
        }
    }

    /**
     * @return list<array{day_of_week: int, first_booking_time: string, last_booking_time: string}>
     */
    private function schedules(): array
    {
        $schedules = [];

        foreach (self::WEEKLY_SCHEDULE as $day => $ranges) {
            foreach ($ranges as [$first, $last]) {
                $schedules[] = ['day_of_week' => $day, 'first_booking_time' => $first, 'last_booking_time' => $last];
            }
        }

        return $schedules;
    }
}
