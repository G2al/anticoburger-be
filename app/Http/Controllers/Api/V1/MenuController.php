<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MenuCategoryResource;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    private const CACHE_SECONDS = 60;

    /**
     * Public menu: categories in the admin order (only those with products),
     * products in the admin order within each category.
     */
    public function __invoke(): JsonResponse
    {
        $categories = MenuCategory::query()
            ->whereHas('items')
            ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return MenuCategoryResource::collection($categories)
            ->response()
            ->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }
}
