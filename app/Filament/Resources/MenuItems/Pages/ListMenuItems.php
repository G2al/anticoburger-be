<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Models\MenuCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuovo prodotto'),
        ];
    }

    /**
     * "Tutti" plus one tab per category, in the order chosen in "Categorie menu".
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'tutti' => Tab::make('Tutti')->excludeQueryWhenResolvingRecord(),
        ];

        $categories = MenuCategory::query()->withCount('items')->orderBy('sort_order')->orderBy('id')->get();

        foreach ($categories as $category) {
            $tabs['categoria-'.$category->id] = Tab::make($category->name)
                ->badge($category->items_count ?: null)
                ->excludeQueryWhenResolvingRecord()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('menu_category_id', $category->id));
        }

        return $tabs;
    }
}
