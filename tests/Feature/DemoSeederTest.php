<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_keeps_existing_tables(): void
    {
        RestaurantTable::factory()->create(['name' => 'Tavolo 1', 'duration_minutes' => 45]);

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(5, RestaurantTable::query()->count());
        $this->assertSame(45, RestaurantTable::query()->where('name', 'Tavolo 1')->value('duration_minutes'));
        $this->assertSame([6, 8, 10], RestaurantTable::query()->where('name', 'Tavolo 5')->sole()->partySizes());
    }
}
