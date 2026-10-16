<?php

namespace App\Filament\Resources\AvailabilityBlocks\Schemas;

use App\Enums\BlockScope;
use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Models\AvailabilityBlock;
use App\Models\RestaurantTable;
use App\Services\Reservations\AvailabilityBlockService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AvailabilityBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Prenotazioni coinvolte')
                    ->visible(fn (?AvailabilityBlock $record) => $record !== null && self::affectedCount($record) > 0)
                    ->schema([
                        Text::make(fn (AvailabilityBlock $record) => 'Ci sono '.self::affectedCount($record).' prenotazioni confermate che si sovrappongono a questo blocco. Il blocco impedisce solo le nuove prenotazioni: quelle esistenti non vengono modificate.'),
                        Actions::make([
                            Action::make('viewAffected')
                                ->label('Visualizza prenotazioni coinvolte')
                                ->url(fn (AvailabilityBlock $record) => AvailabilityBlockResource::affectedReservationsUrl($record)),
                        ]),
                    ]),
                Section::make('Blocco')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->placeholder('Es. Chiusura per ferie')
                            ->required()
                            ->maxLength(150),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->helperText('Un blocco disattivato non ha alcun effetto.')
                            ->default(true)
                            ->inline(false),
                        ToggleButtons::make('scope')
                            ->label('Si applica a')
                            ->options(BlockScope::class)
                            ->default(BlockScope::Restaurant)
                            ->inline()
                            ->required()
                            ->live(),
                        Select::make('restaurant_table_id')
                            ->label('Tavolo')
                            ->options(fn () => RestaurantTable::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->visible(fn (Get $get) => self::scopeIs($get('scope'), BlockScope::Table))
                            ->required(fn (Get $get) => self::scopeIs($get('scope'), BlockScope::Table)),
                    ]),
                Section::make('Periodo')
                    ->description('Per le fasce orarie su più giorni, la stessa fascia si applica a ogni data inclusa. Le fasce non possono superare la mezzanotte: usa due blocchi separati.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('starts_on')
                            ->label('Dal')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->live(),
                        DatePicker::make('ends_on')
                            ->label('Al (incluso)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->afterOrEqual('starts_on'),
                        Toggle::make('is_all_day')
                            ->label('Intera giornata')
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),
                        TimePicker::make('start_time')
                            ->label('Dalle')
                            ->seconds(false)
                            ->visible(fn (Get $get) => ! $get('is_all_day'))
                            ->required(fn (Get $get) => ! $get('is_all_day')),
                        TimePicker::make('end_time')
                            ->label('Alle')
                            ->seconds(false)
                            ->visible(fn (Get $get) => ! $get('is_all_day'))
                            ->required(fn (Get $get) => ! $get('is_all_day'))
                            ->after('start_time'),
                    ]),
            ]);
    }

    private static function scopeIs(mixed $state, BlockScope $scope): bool
    {
        return $state === $scope || $state === $scope->value;
    }

    private static function affectedCount(AvailabilityBlock $block): int
    {
        return app(AvailabilityBlockService::class)->affectedReservations($block)->count();
    }
}
