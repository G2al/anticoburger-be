<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\Reservations\AvailabilityService;
use App\Support\BookingTime;
use Database\Seeders\AnticoBurgerTablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnticoBurgerTablesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_real_tables_with_their_party_sizes(): void
    {
        $this->seed(AnticoBurgerTablesSeeder::class);

        $tables = RestaurantTable::query()->with(['allowedPartySizes', 'schedules'])->get()->keyBy('name');

        $this->assertCount(20, $tables);
        $this->assertSame([2], $tables['Tavolo 1']->partySizes());
        $this->assertSame([3, 4], $tables['Tavolo 7']->partySizes());
        $this->assertSame([3, 4], $tables['Tavolo 13']->partySizes());
        $this->assertSame([5, 6], $tables['Tavolo 16']->partySizes());
        $this->assertSame([5, 6], $tables['Tavolo 17']->partySizes());
        $this->assertSame([2], $tables['Tavolo 19']->partySizes());
        $this->assertSame([7, 8, 9, 10], $tables['Tavolone']->partySizes());
        $this->assertSame(10, $tables->filter(fn (RestaurantTable $table) => $table->partySizes() === [2])->count());
        $this->assertTrue($tables->every(fn (RestaurantTable $table) => $table->is_active && $table->schedules->count() === 6));
    }

    public function test_offered_start_times_follow_the_opening_hours(): void
    {
        $this->seed(AnticoBurgerTablesSeeder::class);

        $table = RestaurantTable::query()->where('name', 'Tavolo 1')->with('schedules')->sole();
        $availability = app(AvailabilityService::class);
        $startTimes = fn (string $date) => array_map(
            fn ($startsAt) => BookingTime::toLocal($startsAt)->format('H:i'),
            $availability->startTimesFor($table, BookingTime::date($date)),
        );

        // 2026-10-19 Monday, 2026-10-20 Tuesday, 2026-10-24 Saturday, 2026-10-25 Sunday.
        $evening = ['18:30', '19:05', '19:40', '20:15', '20:50', '21:25', '22:00', '22:35', '23:10'];

        $this->assertSame($evening, $startTimes('2026-10-19'));
        $this->assertSame([], $startTimes('2026-10-20'));
        $this->assertSame(['18:30', '19:05', '19:40', '20:15', '20:50'], $startTimes('2026-10-24'));
        $this->assertSame($evening, $startTimes('2026-10-25'));
        $this->assertSame(35, $table->duration_minutes);
    }

    public function test_is_idempotent_and_fixes_existing_tables_without_touching_reservations(): void
    {
        $demoTable = RestaurantTable::factory()->withPartySizes([2, 3, 4])->create(['name' => 'Tavolo 3', 'duration_minutes' => 75]);
        $reservation = Reservation::factory()->for($demoTable)->create();

        $this->seed(AnticoBurgerTablesSeeder::class);
        $this->seed(AnticoBurgerTablesSeeder::class);

        $this->assertSame(20, RestaurantTable::query()->count());
        $this->assertSame([2], $demoTable->fresh()->load('allowedPartySizes')->partySizes());
        $this->assertSame(35, $demoTable->fresh()->duration_minutes);
        $this->assertSame(35, $demoTable->fresh()->slot_interval_minutes);
        $this->assertSame(0, $demoTable->fresh()->turnaround_minutes);
        $this->assertModelExists($reservation);
        $this->assertSame($demoTable->id, $reservation->fresh()->restaurant_table_id);
    }
}
