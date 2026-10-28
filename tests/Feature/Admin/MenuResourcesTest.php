<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MenuCategories\Pages\ManageMenuCategories;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MenuResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MenuItem::IMAGE_DISK);
        $this->actingAs(User::factory()->admin()->create());
    }

    private function category(string $name, int $sortOrder = 0): MenuCategory
    {
        return MenuCategory::query()->create(['name' => $name, 'sort_order' => $sortOrder]);
    }

    private function item(MenuCategory $category, array $attributes = []): MenuItem
    {
        return MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Classic Burger',
            'price' => '12.50',
            ...$attributes,
        ]);
    }

    public function test_pages_are_reachable_only_by_admins(): void
    {
        $this->get('/admin/menu')->assertOk();
        $this->get('/admin/menu/create')->assertOk();
        $this->get('/admin/categorie-menu')->assertOk();

        $this->actingAs(User::factory()->create());
        $this->get('/admin/menu')->assertForbidden();
        $this->get('/admin/categorie-menu')->assertForbidden();
    }

    public function test_categories_are_created_in_order_and_reorderable(): void
    {
        foreach (['Panini', 'Antipasti', 'Bibite'] as $name) {
            Livewire::test(ManageMenuCategories::class)
                ->callAction('create', ['name' => $name])
                ->assertHasNoActionErrors();
        }

        $this->assertSame(['Panini', 'Antipasti', 'Bibite'], MenuCategory::query()->orderBy('sort_order')->pluck('name')->all());

        Livewire::test(ManageMenuCategories::class)
            ->callAction('create', ['name' => 'Panini'])
            ->assertHasActionErrors(['name' => 'unique']);

        $ids = MenuCategory::query()->orderBy('sort_order')->pluck('id')->all();
        Livewire::test(ManageMenuCategories::class)->call('reorderTable', array_map('strval', array_reverse($ids)));

        $this->assertSame(['Bibite', 'Antipasti', 'Panini'], MenuCategory::query()->orderBy('sort_order')->pluck('name')->all());
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $used = $this->category('Panini');
        $empty = $this->category('Dolci');
        $this->item($used);

        Livewire::test(ManageMenuCategories::class)
            ->assertActionHidden(TestAction::make('delete')->table($used))
            ->callAction(TestAction::make('delete')->table($empty));

        $this->assertModelExists($used);
        $this->assertModelMissing($empty);
    }

    public function test_admin_creates_a_product_with_photo(): void
    {
        $category = $this->category('Panini');

        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'menu_category_id' => $category->id,
                'name' => 'Antico Burger',
                'price' => '14.90',
                'ingredients' => 'Pane brioche, manzo 200 g, cheddar, bacon',
                'is_special' => true,
                'image_path' => UploadedFile::fake()->image('burger.jpg', 800, 600),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = MenuItem::query()->sole();
        $this->assertSame('Antico Burger', $item->name);
        $this->assertSame('14.90', $item->price);
        $this->assertTrue($item->is_special);
        $this->assertSame($category->id, $item->menu_category_id);
        $this->assertStringStartsWith('menu/', $item->image_path);
        Storage::disk(MenuItem::IMAGE_DISK)->assertExists($item->image_path);
    }

    public function test_adding_a_photo_to_an_existing_product_stays_on_the_page_and_shows_the_optimized_photo(): void
    {
        $item = $this->item($this->category('Panini'), ['name' => 'Antico']);

        $page = Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['image_path' => UploadedFile::fake()->image('antico.png', 2400, 2400)])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect()
            ->assertNotified('Salvato');

        $item->refresh();
        $expectedPath = function_exists('imagewebp') ? '.webp' : '.png';
        $this->assertStringEndsWith($expectedPath, $item->image_path);
        Storage::disk(MenuItem::IMAGE_DISK)->assertExists($item->image_path);

        $formPhoto = $page->get('data.image_path');
        $this->assertSame([$item->image_path], array_values((array) $formPhoto));
    }

    public function test_products_are_reorderable_inside_a_category_tab_only(): void
    {
        $burgers = $this->category('Panini');
        $first = $this->item($burgers, ['name' => 'Zeta Burger']);
        $second = $this->item($burgers, ['name' => 'Alfa Burger']);
        $third = $this->item($burgers, ['name' => 'Mezzo Burger']);

        $this->assertSame([1, 2, 3], [$first->sort_order, $second->sort_order, $third->sort_order]);

        Livewire::test(ListMenuItems::class)
            ->set('activeTab', 'categoria-'.$burgers->id)
            ->assertCanSeeTableRecords([$first, $second, $third], inOrder: true)
            ->call('reorderTable', [(string) $third->id, (string) $first->id, (string) $second->id]);

        $this->assertSame(
            ['Mezzo Burger', 'Zeta Burger', 'Alfa Burger'],
            MenuItem::query()->orderBy('sort_order')->pluck('name')->all(),
        );

        $this->getJson('/api/v1/menu')->assertJsonPath('data.0.items.0.name', 'Mezzo Burger');

        Livewire::test(ListMenuItems::class)
            ->assertSet('activeTab', 'tutti')
            ->call('reorderTable', [(string) $second->id, (string) $third->id, (string) $first->id]);

        $this->assertSame('Mezzo Burger', MenuItem::query()->orderBy('sort_order')->value('name'));
    }

    public function test_product_validation(): void
    {
        Livewire::test(CreateMenuItem::class)
            ->fillForm(['name' => '', 'price' => '-1', 'menu_category_id' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'price' => 'min', 'menu_category_id' => 'required']);

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_replaced_and_deleted_photos_are_removed_from_disk(): void
    {
        $disk = Storage::disk(MenuItem::IMAGE_DISK);
        $disk->put('menu/old.jpg', 'old');
        $disk->put('menu/new.jpg', 'new');
        $item = $this->item($this->category('Panini'), ['image_path' => 'menu/old.jpg']);

        $item->update(['image_path' => 'menu/new.jpg']);
        $disk->assertMissing('menu/old.jpg');
        $disk->assertExists('menu/new.jpg');

        Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])->callAction('delete');

        $this->assertModelMissing($item);
        $disk->assertMissing('menu/new.jpg');
    }

    public function test_special_toggle_and_filters_in_the_list(): void
    {
        $burgers = $this->category('Panini');
        $drinks = $this->category('Bibite');
        $burger = $this->item($burgers, ['name' => 'Classic Burger']);
        $special = $this->item($burgers, ['name' => 'Truffle Burger', 'is_special' => true]);
        $cola = $this->item($drinks, ['name' => 'Cola', 'price' => '3.00']);

        Livewire::test(ListMenuItems::class)
            ->assertCanSeeTableRecords([$burger, $special, $cola])
            ->call('updateTableColumnState', 'is_special', (string) $burger->getKey(), true);

        $this->assertTrue($burger->fresh()->is_special);

        Livewire::test(ListMenuItems::class)
            ->assertSet('activeTab', 'tutti')
            ->assertSeeInOrder(['Tutti', 'Panini', 'Bibite'])
            ->set('activeTab', 'categoria-'.$drinks->id)
            ->assertCanSeeTableRecords([$cola])
            ->assertCanNotSeeTableRecords([$burger, $special]);

        Livewire::test(ListMenuItems::class)
            ->set('activeTab', 'categoria-'.$burgers->id)
            ->assertCanSeeTableRecords([$burger, $special])
            ->assertCanNotSeeTableRecords([$cola])
            ->callAction(TestAction::make('edit')->table($burger))
            ->assertHasNoActionErrors();

        Livewire::test(ListMenuItems::class)
            ->filterTable('is_special', false)
            ->assertCanSeeTableRecords([$cola])
            ->assertCanNotSeeTableRecords([$burger, $special]);
    }
}
