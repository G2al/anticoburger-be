<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = BookingTime::today();

        $todayCounts = Reservation::query()
            ->startingOnLocalDate($today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            Stat::make('Confermate oggi', (int) ($todayCounts[ReservationStatus::Confirmed->value] ?? 0))
                ->color('success'),
            Stat::make('Annullate oggi', (int) ($todayCounts[ReservationStatus::Cancelled->value] ?? 0))
                ->color('danger'),
            Stat::make('Confermate future', Reservation::query()
                ->confirmed()
                ->where('starts_at', '>=', CarbonImmutable::now('UTC'))
                ->count()),
            Stat::make('Tavoli attivi', RestaurantTable::query()->active()->count()),
        ];
    }
}
