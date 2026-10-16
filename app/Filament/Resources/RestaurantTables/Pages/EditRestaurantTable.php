<?php

namespace App\Filament\Resources\RestaurantTables\Pages;

use App\Filament\Resources\RestaurantTables\RestaurantTableResource;
use App\Filament\Resources\RestaurantTables\Schemas\RestaurantTableForm;
use App\Filament\Resources\RestaurantTables\Tables\RestaurantTablesTable;
use App\Filament\Support\BookingFeedback;
use App\Models\RestaurantTable;
use App\Services\Reservations\RestaurantTableService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property RestaurantTable $record
 */
class EditRestaurantTable extends EditRecord
{
    protected static string $resource = RestaurantTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (RestaurantTable $record) => RestaurantTablesTable::delete($record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RestaurantTableForm::fillFromRecord($this->record, $data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BookingFeedback::run(
            fn () => app(RestaurantTableService::class)->update($record, RestaurantTableForm::toServiceData($data)),
            'data',
        );
    }
}
