<?php

namespace Tests\Feature\Api;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_categories_in_admin_order_with_their_products(): void
    {
        Storage::fake(MenuItem::IMAGE_DISK);
        $drinks = MenuCategory::query()->create(['name' => 'Bibite', 'sort_order' => 3]);
        $burgers = MenuCategory::query()->create(['name' => 'Panini', 'sort_order' => 1]);
        MenuCategory::query()->create(['name' => 'Dolci', 'sort_order' => 2]);

        MenuItem::query()->create(['menu_category_id' => $burgers->id, 'name' => 'Truffle Burger', 'price' => 16.5, 'ingredients' => 'Tartufo, manzo', 'is_special' => true, 'image_path' => 'menu/truffle.jpg']);
        MenuItem::query()->create(['menu_category_id' => $burgers->id, 'name' => 'Classic Burger', 'price' => 12]);
        MenuItem::query()->create(['menu_category_id' => $drinks->id, 'name' => 'Cola', 'price' => 3]);

        $this->getJson('/api/v1/menu')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $burgers->id,
                        'name' => 'Panini',
                        'items' => [
                            ['id' => 1, 'name' => 'Truffle Burger', 'price' => '16.50', 'ingredients' => 'Tartufo, manzo', 'is_special' => true, 'image_url' => Storage::disk(MenuItem::IMAGE_DISK)->url('menu/truffle.jpg')],
                            ['id' => 2, 'name' => 'Classic Burger', 'price' => '12.00', 'ingredients' => null, 'is_special' => false, 'image_url' => null],
                        ],
                    ],
                    [
                        'id' => $drinks->id,
                        'name' => 'Bibite',
                        'items' => [
                            ['id' => 3, 'name' => 'Cola', 'price' => '3.00', 'ingredients' => null, 'is_special' => false, 'image_url' => null],
                        ],
                    ],
                ],
            ]);
    }

    public function test_empty_menu(): void
    {
        $this->getJson('/api/v1/menu')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_menu_shares_the_read_rate_limit(): void
    {
        config(['booking.rate_limits.read_per_minute' => 1]);

        $this->getJson('/api/v1/menu')->assertOk();
        $this->getJson('/api/v1/menu')->assertStatus(429)->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }
}
