<?php

namespace App\Filament\Resources\Reservations;

use App\Filament\Support\BookingFeedback;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityService;
use App\Services\Reservations\ReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Reservation actions shared by the table, the view page and the edit page.
 */
final class ReservationActions
{
    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Annulla prenotazione')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('cancel')
            ->requiresConfirmation()
            ->modalHeading('Annullare la prenotazione?')
            ->modalDescription('La prenotazione resterà nello storico con stato "Annullata" e il tavolo tornerà disponibile.')
            ->modalSubmitActionLabel('Sì, annulla')
            ->action(function (Reservation $record): void {
                app(ReservationService::class)->cancel($record);

                Notification::make()->title('Prenotazione annullata')->success()->send();
            });
    }

    public static function moveToTable(): Action
    {
        return Action::make('moveToTable')
            ->label('Sposta tavolo')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->authorize('move')
            ->modalHeading('Sposta su un altro tavolo')
            ->modalDescription('Sono elencati solo i tavoli compatibili e liberi per lo stesso orario.')
            ->schema([
                Select::make('restaurant_table_id')
                    ->label('Nuovo tavolo')
                    ->required()
                    ->options(fn (Reservation $record) => app(AvailabilityService::class)
                        ->freeTablesAt($record->starts_at, $record->party_size, $record)
                        ->reject(fn ($table) => $table->id === $record->restaurant_table_id)
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->action(function (Reservation $record, array $data): void {
                BookingFeedback::run(
                    fn () => app(ReservationService::class)->moveToTable($record, (int) $data['restaurant_table_id'])
                );

                Notification::make()->title('Prenotazione spostata')->success()->send();
            });
    }
}
