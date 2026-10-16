<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationActions;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\BookingFeedback;
use App\Models\Reservation;
use App\Services\Reservations\ReservationService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Reservation $record
 */
class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ReservationActions::moveToTable(),
            ReservationActions::cancel(),
            ReservationActions::delete(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ReservationForm::fillFromRecord($this->record, $data);
    }

    /**
     * Fields of a cancelled reservation's occupancy are disabled and therefore absent
     * from $data: the stored values are used so that only customer data can change.
     *
     * @param  Reservation  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data = [
            ...ReservationForm::fillFromRecord($record, [
                'party_size' => $record->party_size,
                'restaurant_table_id' => $record->restaurant_table_id,
            ]),
            ...$data,
        ];

        return BookingFeedback::run(fn () => app(ReservationService::class)->updateByAdmin($record, $data), 'data');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
