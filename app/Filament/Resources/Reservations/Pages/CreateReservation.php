<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Support\BookingFeedback;
use App\Services\Reservations\ReservationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BookingFeedback::run(fn () => app(ReservationService::class)->createByAdmin($data), 'data');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
