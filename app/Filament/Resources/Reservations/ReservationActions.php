<?php

namespace App\Filament\Resources\Reservations;

use App\Filament\Support\BookingFeedback;
use App\Models\Reservation;
use App\Services\Reservations\AvailabilityService;
use App\Services\Reservations\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

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

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Elimina definitivamente')
            ->modalHeading('Eliminare definitivamente la prenotazione?')
            ->modalDescription('La prenotazione verrà cancellata per sempre dal database e non sarà più recuperabile. Se vuoi mantenerne lo storico usa invece "Annulla prenotazione".')
            ->modalSubmitActionLabel('Sì, elimina per sempre')
            ->successNotificationTitle('Prenotazione eliminata definitivamente')
            ->using(function (Reservation $record): bool {
                app(ReservationService::class)->delete($record);

                return true;
            });
    }

    public static function deleteSelected(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label('Elimina definitivamente')
            ->modalHeading('Eliminare definitivamente le prenotazioni selezionate?')
            ->modalDescription('Le prenotazioni verranno cancellate per sempre dal database e non saranno più recuperabili.')
            ->modalSubmitActionLabel('Sì, elimina per sempre')
            ->using(function (Collection $records): void {
                $reservations = app(ReservationService::class);

                $records->each(fn (Reservation $record) => $reservations->delete($record));
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
