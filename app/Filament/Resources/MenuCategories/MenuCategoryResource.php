<?php

namespace App\Filament\Resources\MenuCategories;

use App\Filament\NavigationGroup;
use App\Filament\Resources\MenuCategories\Pages\ManageMenuCategories;
use App\Models\MenuCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'categoria';

    protected static ?string $pluralModelLabel = 'categorie';

    protected static ?string $navigationLabel = 'Categorie';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MENU;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'categorie-menu';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->placeholder('Es. Panini, Antipasti, Bibite')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->description('Trascina le categorie per scegliere l\'ordine in cui compaiono nel menu.')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Prodotti')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMenuCategories::route('/'),
        ];
    }
}
