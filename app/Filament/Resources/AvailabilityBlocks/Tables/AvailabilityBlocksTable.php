<?php

namespace App\Filament\Resources\AvailabilityBlocks\Tables;

use App\Enums\BlockScope;
use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Filament\Support\BookingFeedback;
use App\Models\AvailabilityBlock;
use App\Services\Reservations\AvailabilityBlockService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AvailabilityBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable(),
                TextColumn::make('scope')
                    ->label('Ambito')
                    ->badge(),
                TextColumn::make('restaurantTable.name')
                    ->label('Tavolo')
                    ->placeholder('Tutti'),
                TextColumn::make('starts_on')
                    ->label('Periodo')
                    ->formatStateUsing(fn (AvailabilityBlock $record) => $record->describePeriod())
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->disabled(fn (AvailabilityBlock $record) => ! auth()->user()?->can('update', $record))
                    ->updateStateUsing(fn (AvailabilityBlock $record, bool $state) => self::toggleActive($record, $state)),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Attivo'),
                SelectFilter::make('scope')->label('Ambito')->options(BlockScope::class),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('viewAffected')
                        ->label('Prenotazioni coinvolte')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->url(fn (AvailabilityBlock $record) => AvailabilityBlockResource::affectedReservationsUrl($record)),
                    DeleteAction::make()
                        ->using(fn (AvailabilityBlock $record) => self::delete($record)),
                ]),
            ]);
    }

    public static function delete(AvailabilityBlock $record): bool
    {
        app(AvailabilityBlockService::class)->delete($record);

        return true;
    }

    private static function toggleActive(AvailabilityBlock $record, bool $state): bool
    {
        BookingFeedback::run(fn () => app(AvailabilityBlockService::class)->setActive($record, $state));

        Notification::make()
            ->title($state ? 'Blocco attivato' : 'Blocco disattivato')
            ->success()
            ->send();

        if ($state) {
            AvailabilityBlockResource::warnAboutAffectedReservations($record);
        }

        return $state;
    }
}
