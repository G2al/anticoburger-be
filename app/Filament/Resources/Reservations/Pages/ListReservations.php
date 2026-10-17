<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationPeriod;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Widgets\ReservationsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuova prenotazione'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReservationsOverview::class,
        ];
    }

    /**
     * "Prossime" is the default tab: the list always opens on what is still to come,
     * even when today is a closing day.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (ReservationPeriod::cases() as $period) {
            $tabs[$period->value] = Tab::make($period->label())
                ->excludeQueryWhenResolvingRecord()
                ->modifyQueryUsing(fn (Builder $query) => $period === ReservationPeriod::Past
                    ? $period->apply($query)->reorder()->orderByDesc('starts_at')
                    : $period->apply($query));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ReservationPeriod::Upcoming->value;
    }
}
