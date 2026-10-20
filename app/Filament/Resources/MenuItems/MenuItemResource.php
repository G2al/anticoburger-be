<?php

namespace App\Filament\Resources\MenuItems;

use App\Filament\NavigationGroup;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\MenuItem;
use App\Services\Menu\MenuImageOptimizer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'prodotto';

    protected static ?string $pluralModelLabel = 'prodotti';

    protected static ?string $navigationLabel = 'Prodotti';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MENU;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'menu';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Prodotto')
                    ->columns(2)
                    ->schema([
                        Select::make('menu_category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name', fn (Builder $query) => $query->orderBy('sort_order'))
                            ->required()
                            ->preload()
                            ->searchable(),
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('price')
                            ->label('Prezzo')
                            ->prefix('€')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(9999.99)
                            ->required(),
                        Toggle::make('is_special')
                            ->label('Special')
                            ->helperText('Evidenzia il prodotto come speciale.')
                            ->inline(false),
                        Textarea::make('ingredients')
                            ->label('Ingredienti')
                            ->placeholder('Es. Pane brioche, hamburger di manzo 200 g, cheddar, bacon, salsa BBQ')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Foto')
                            ->helperText('La foto viene ridimensionata e convertita in WebP automaticamente per caricarsi velocemente nella PWA.')
                            ->image()
                            ->automaticallyResizeImagesMode('contain')
                            ->automaticallyResizeImagesToWidth((string) MenuImageOptimizer::MAX_SIDE)
                            ->automaticallyResizeImagesToHeight((string) MenuImageOptimizer::MAX_SIDE)
                            ->automaticallyUpscaleImagesWhenResizing(false)
                            ->disk(MenuItem::IMAGE_DISK)
                            ->directory(MenuItem::IMAGE_DIRECTORY)
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Foto')
                    ->disk(MenuItem::IMAGE_DISK)
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->description(fn (MenuItem $record) => $record->ingredients ? str($record->ingredients)->limit(60)->toString() : null),
                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray')
                    ->visible(fn ($livewire) => in_array($livewire->activeTab ?? null, [null, 'tutti'], true)),
                TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR', locale: 'it')
                    ->sortable(),
                ToggleColumn::make('is_special')
                    ->label('Special')
                    ->disabled(fn (MenuItem $record) => ! auth()->user()?->can('update', $record)),
            ])
            ->filters([
                TernaryFilter::make('is_special')
                    ->label('Special'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }
}
