<?php

namespace App\Filament\Resources\RestaurantTables\Tables;

use App\Filament\Support\BookingFeedback;
use App\Models\RestaurantTable;
use App\Services\Reservations\RestaurantTableService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RestaurantTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('party_sizes')
                    ->label('Capienze')
                    ->state(fn (RestaurantTable $record) => $record->partySizes())
                    ->badge()
                    ->placeholder('Nessuna'),
                TextColumn::make('duration_minutes')
                    ->label('Durata')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('slot_interval_minutes')
                    ->label('Intervallo')
                    ->suffix(' min'),
                TextColumn::make('turnaround_minutes')
                    ->label('Riassetto')
                    ->suffix(' min'),
                TextColumn::make('assignment_priority')
                    ->label('Priorità')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->disabled(fn (RestaurantTable $record) => ! auth()->user()?->can('update', $record))
                    ->updateStateUsing(fn (RestaurantTable $record, bool $state) => self::toggleActive($record, $state)),
                TextColumn::make('reservations_count')
                    ->label('Prenotazioni')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Attivo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(fn (RestaurantTable $record) => self::delete($record)),
            ]);
    }

    public static function delete(RestaurantTable $record): bool
    {
        BookingFeedback::run(fn () => app(RestaurantTableService::class)->delete($record));

        return true;
    }

    /**
     * @return bool|array{error: string}
     */
    private static function toggleActive(RestaurantTable $record, bool $state): bool|array
    {
        try {
            BookingFeedback::run(fn () => app(RestaurantTableService::class)->setActive($record, $state));
        } catch (Halt) {
            return ['error' => 'Modifica non salvata.'];
        }

        Notification::make()
            ->title($state ? 'Tavolo attivato' : 'Tavolo disattivato')
            ->success()
            ->send();

        return $state;
    }
}
