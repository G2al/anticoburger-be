<?php

namespace App\Filament\Resources\AvailabilityBlocks\Pages;

use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Filament\Resources\AvailabilityBlocks\Tables\AvailabilityBlocksTable;
use App\Filament\Support\BookingFeedback;
use App\Models\AvailabilityBlock;
use App\Services\Reservations\AvailabilityBlockService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property AvailabilityBlock $record
 */
class EditAvailabilityBlock extends EditRecord
{
    protected static string $resource = AvailabilityBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (AvailabilityBlock $record) => AvailabilityBlocksTable::delete($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return BookingFeedback::run(fn () => app(AvailabilityBlockService::class)->update($record, $data), 'data');
    }

    protected function afterSave(): void
    {
        AvailabilityBlockResource::warnAboutAffectedReservations($this->record);
    }
}
