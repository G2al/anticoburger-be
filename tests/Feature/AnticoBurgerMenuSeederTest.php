<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\AnticoBurgerMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnticoBurgerMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_whole_menu_in_order(): void
    {
        $this->seed(AnticoBurgerMenuSeeder::class);

        $this->assertSame(
            ['Antipasti', 'Panini', 'Dolci', 'Distillati', 'Bibite', 'Birre alla spina', 'Birre in bottiglia', 'Extra'],
            MenuCategory::query()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame(48, MenuItem::query()->count());

        $ispanico = MenuItem::query()->where('name', 'Ispanico')->sole();
        $this->assertSame('12.50', $ispanico->price);
        $this->assertStringContainsString('burratina pugliese', $ispanico->ingredients);
        $this->assertFalse($ispanico->is_special);
        $this->assertNull($ispanico->image_path);

        $this->assertSame('8.50', MenuItem::query()->where('name', 'Corsendonk Rousse (cl 50)')->value('price'));
        $this->assertSame('2.00', MenuItem::query()->where('name', 'Coperto')->value('price'));
    }

    public function test_is_idempotent_and_keeps_photos_and_special_flags(): void
    {
        $this->seed(AnticoBurgerMenuSeeder::class);
        MenuItem::query()->where('name', 'Antico')->update(['is_special' => true, 'image_path' => 'menu/antico.jpg', 'price' => 99]);

        $this->seed(AnticoBurgerMenuSeeder::class);

        $this->assertSame(8, MenuCategory::query()->count());
        $this->assertSame(48, MenuItem::query()->count());
        $antico = MenuItem::query()->where('name', 'Antico')->sole();
        $this->assertTrue($antico->is_special);
        $this->assertSame('menu/antico.jpg', $antico->image_path);
        $this->assertSame('12.00', $antico->price);
    }
}
