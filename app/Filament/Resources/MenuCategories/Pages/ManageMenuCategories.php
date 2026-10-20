<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use App\Models\MenuCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMenuCategories extends ManageRecords
{
    protected static string $resource = MenuCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuova categoria')
                ->mutateDataUsing(fn (array $data) => [
                    ...$data,
                    'sort_order' => (int) MenuCategory::query()->max('sort_order') + 1,
                ]),
        ];
    }
}
