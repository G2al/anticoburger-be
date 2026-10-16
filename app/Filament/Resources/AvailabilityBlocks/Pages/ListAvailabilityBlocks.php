<?php

namespace App\Filament\Resources\AvailabilityBlocks\Pages;

use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvailabilityBlocks extends ListRecords
{
    protected static string $resource = AvailabilityBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuovo blocco'),
        ];
    }
}
