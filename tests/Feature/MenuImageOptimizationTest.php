<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Menu\MenuImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD without WebP support.');
        }

        Storage::fake(MenuItem::IMAGE_DISK);
        $this->category = MenuCategory::query()->create(['name' => 'Panini', 'sort_order' => 1]);
    }

    private function storeImage(string $name, int $width, int $height): string
    {
        return UploadedFile::fake()->image($name, $width, $height)->store(MenuItem::IMAGE_DIRECTORY, MenuItem::IMAGE_DISK);
    }

    public function test_uploaded_photo_is_resized_and_converted_to_webp(): void
    {
        $path = $this->storeImage('burger.png', 3000, 2000);

        $item = MenuItem::query()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'Antico',
            'price' => 12,
            'image_path' => $path,
        ]);

        $disk = Storage::disk(MenuItem::IMAGE_DISK);
        $this->assertStringEndsWith('.webp', $item->image_path);
        $disk->assertExists($item->image_path);
        $disk->assertMissing($path);

        [$width, $height, $type] = getimagesize($disk->path($item->image_path));
        $this->assertSame(IMAGETYPE_WEBP, $type);
        $this->assertSame(MenuImageOptimizer::MAX_SIDE, $width);
        $this->assertSame(800, $height);
    }

    public function test_small_photos_are_converted_without_upscaling(): void
    {
        $item = MenuItem::query()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'Cola',
            'price' => 3,
            'image_path' => $this->storeImage('cola.jpg', 400, 600),
        ]);

        [$width, $height] = getimagesize(Storage::disk(MenuItem::IMAGE_DISK)->path($item->image_path));
        $this->assertSame([400, 600], [$width, $height]);
    }

    public function test_unreadable_files_are_kept_as_they_are(): void
    {
        Storage::disk(MenuItem::IMAGE_DISK)->put('menu/broken.jpg', 'not an image');

        $item = MenuItem::query()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'Rotto',
            'price' => 1,
            'image_path' => 'menu/broken.jpg',
        ]);

        $this->assertSame('menu/broken.jpg', $item->image_path);
        Storage::disk(MenuItem::IMAGE_DISK)->assertExists('menu/broken.jpg');
    }

    public function test_command_optimizes_photos_uploaded_before(): void
    {
        $path = $this->storeImage('old.png', 2400, 2400);
        $item = MenuItem::query()->create(['menu_category_id' => $this->category->id, 'name' => 'Vecchio', 'price' => 5]);
        $item->forceFill(['image_path' => $path])->saveQuietly();

        $this->artisan('app:optimize-menu-images')->assertSuccessful();

        $item->refresh();
        $this->assertStringEndsWith('.webp', $item->image_path);
        Storage::disk(MenuItem::IMAGE_DISK)->assertMissing($path);
        $this->assertSame(MenuImageOptimizer::MAX_SIDE, getimagesize(Storage::disk(MenuItem::IMAGE_DISK)->path($item->image_path))[0]);

        $this->artisan('app:optimize-menu-images')->expectsOutputToContain('Foto ottimizzate: 0')->assertSuccessful();
    }
}
