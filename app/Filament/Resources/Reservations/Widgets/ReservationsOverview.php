<?php

namespace App\Filament\Resources\Reservations\Widgets;

use App\Enums\ReservationPeriod;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Confirmed reservations and covers per period. Each stat opens the matching list tab.
 */
class ReservationsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int|array|null
    {
        return ['default' => 2, 'lg' => 3, 'xl' => 5];
    }

    protected function getStats(): array
    {
        return [
            $this->nextServiceStat(),
            $this->periodStat(ReservationPeriod::Today, Heroicon::OutlinedCalendar),
            $this->periodStat(ReservationPeriod::Tomorrow, Heroicon::OutlinedArrowRightCircle),
            $this->periodStat(ReservationPeriod::ThisWeek, Heroicon::OutlinedCalendarDays),
            $this->periodStat(ReservationPeriod::ThisMonth, Heroicon::OutlinedChartBar),
        ];
    }

    private function periodStat(ReservationPeriod $period, Heroicon $icon): Stat
    {
        $totals = $period
            ->apply(Reservation::query()->confirmed())
            ->toBase()
            ->selectRaw('count(*) as reservations, coalesce(sum(party_size), 0) as covers')
            ->first();

        return Stat::make($period->label(), (int) $totals->reservations)
            ->description(self::covers((int) $totals->covers))
            ->descriptionIcon(Heroicon::OutlinedUsers)
            ->icon($icon)
            ->url(ReservationResource::getUrl('index', ['tab' => $period->value]));
    }

    /**
     * The first day (from now on) with confirmed reservations: useful when today is a closing day.
     */
    private function nextServiceStat(): Stat
    {
        $next = ReservationPeriod::Upcoming->apply(Reservation::query()->confirmed())->orderBy('starts_at')->first();

        if ($next === null) {
            return Stat::make('Prossimo servizio', '—')
                ->description('Nessuna prenotazione in programma')
                ->icon(Heroicon::OutlinedClock);
        }

        $day = $next->localStartsAt()->startOfDay();
        $sameDay = ReservationPeriod::Upcoming
            ->apply(Reservation::query()->confirmed())
            ->startingOnLocalDate($day)
            ->toBase()
            ->selectRaw('count(*) as reservations, coalesce(sum(party_size), 0) as covers')
            ->first();

        return Stat::make('Prossimo servizio · '.$day->locale('it')->translatedFormat('D j M'), (int) $sameDay->reservations)
            ->description(self::covers((int) $sameDay->covers).' · dalle '.$next->localStartsAt()->format('H:i'))
            ->descriptionIcon(Heroicon::OutlinedUsers)
            ->icon(Heroicon::OutlinedClock)
            ->color('primary')
            ->url(ReservationResource::getUrl('index', ['tab' => ReservationPeriod::Upcoming->value]));
    }

    private static function covers(int $covers): string
    {
        return $covers === 1 ? '1 coperto' : "{$covers} coperti";
    }
}
