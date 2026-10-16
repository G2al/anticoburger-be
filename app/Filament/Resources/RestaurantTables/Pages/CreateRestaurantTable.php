<?php

namespace App\Filament\Resources\RestaurantTables\Pages;

use App\Filament\Resources\RestaurantTables\RestaurantTableResource;
use App\Filament\Resources\RestaurantTables\Schemas\RestaurantTableForm;
use App\Filament\Support\BookingFeedback;
use App\Services\Reservations\RestaurantTableService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRestaurantTable extends CreateRecord
{
    protected static string $resource = RestaurantTableResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BookingFeedback::run(
            fn () => app(RestaurantTableService::class)->create(RestaurantTableForm::toServiceData($data)),
            'data',
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
