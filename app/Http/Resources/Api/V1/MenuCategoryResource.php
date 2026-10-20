<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin MenuCategory
 */
class MenuCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'items' => $this->items->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'ingredients' => $item->ingredients,
                'is_special' => $item->is_special,
                'image_url' => $item->image_path ? Storage::disk(MenuItem::IMAGE_DISK)->url($item->image_path) : null,
            ])->values()->all(),
        ];
    }
}
