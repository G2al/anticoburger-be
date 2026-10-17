<?php

namespace App\Enums;

use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Time windows used by the admin reservation list and its statistics.
 * Days, weeks (Monday–Sunday) and months follow the restaurant's local calendar.
 */
enum ReservationPeriod: string
{
    /** Not finished yet: in progress or in the future. */
    case Upcoming = 'prossime';
    case Today = 'oggi';
    case Tomorrow = 'domani';
    case ThisWeek = 'settimana';
    case ThisMonth = 'mese';
    case Past = 'passate';
    case All = 'tutte';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Prossime',
            self::Today => 'Oggi',
            self::Tomorrow => 'Domani',
            self::ThisWeek => 'Questa settimana',
            self::ThisMonth => 'Questo mese',
            self::Past => 'Passate',
            self::All => 'Tutte',
        };
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(Builder $query): Builder
    {
        $now = CarbonImmutable::now('UTC');

        if ($this === self::Upcoming) {
            return $query->where('ends_at', '>', $now);
        }

        if ($this === self::Past) {
            return $query->where('ends_at', '<=', $now);
        }

        if ($this === self::All) {
            return $query;
        }

        [$from, $until] = $this->startsBetween();

        return $query->where('starts_at', '>=', $from)->where('starts_at', '<', $until);
    }

    /**
     * UTC bounds [from, until) of the reservation start for calendar periods.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function startsBetween(): array
    {
        $today = BookingTime::today();

        [$firstDay, $dayAfterLast] = match ($this) {
            self::Today => [$today, $today->addDay()],
            self::Tomorrow => [$today->addDay(), $today->addDays(2)],
            self::ThisWeek => [$today->startOfWeek(CarbonImmutable::MONDAY), $today->startOfWeek(CarbonImmutable::MONDAY)->addWeek()],
            self::ThisMonth => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
        };

        return [BookingTime::localDayStart($firstDay), BookingTime::localDayStart($dayAfterLast)];
    }
}
