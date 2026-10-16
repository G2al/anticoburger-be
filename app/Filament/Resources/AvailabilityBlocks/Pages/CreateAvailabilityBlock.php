<?php

namespace App\Filament\Resources\AvailabilityBlocks\Pages;

use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Filament\Support\BookingFeedback;
use App\Models\AvailabilityBlock;
use App\Services\Reservations\AvailabilityBlockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAvailabilityBlock extends CreateRecord
{
    protected static string $resource = AvailabilityBlockResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return BookingFeedback::run(fn () => app(AvailabilityBlockService::class)->create($data), 'data');
    }

    protected function afterCreate(): void
    {
        /** @var AvailabilityBlock $block */
        $block = $this->getRecord();

        AvailabilityBlockResource::warnAboutAffectedReservations($block);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
