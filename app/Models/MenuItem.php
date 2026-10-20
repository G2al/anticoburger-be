<?php

namespace App\Models;

use App\Services\Menu\MenuImageOptimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A product of the menu. The photo is stored on the "public" disk, converted to a
 * light WebP when saved; replaced or orphaned photos are removed from the disk automatically.
 *
 * @property int $id
 * @property int $menu_category_id
 * @property string $name
 * @property string|null $image_path
 * @property string $price
 * @property string|null $ingredients
 * @property bool $is_special
 */
class MenuItem extends Model
{
    public const IMAGE_DISK = 'public';

    public const IMAGE_DIRECTORY = 'menu';

    protected $fillable = [
        'menu_category_id',
        'name',
        'image_path',
        'price',
        'ingredients',
        'is_special',
    ];

    protected $attributes = [
        'is_special' => false,
    ];

    protected function casts(): array
    {
        return [
            'menu_category_id' => 'integer',
            'price' => 'decimal:2',
            'is_special' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MenuItem $item) {
            if ($item->isDirty('image_path') && filled($item->image_path)) {
                $item->image_path = app(MenuImageOptimizer::class)->optimize(self::IMAGE_DISK, $item->image_path);
            }
        });

        static::updated(function (MenuItem $item) {
            if ($item->wasChanged('image_path')) {
                self::deleteImage($item->getOriginal('image_path'));
            }
        });

        static::deleted(function (MenuItem $item) {
            self::deleteImage($item->image_path);
        });
    }

    /** @return BelongsTo<MenuCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    private static function deleteImage(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }
}
