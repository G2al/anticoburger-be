<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Conversions between the restaurant's local wall clock (config booking.timezone)
 * and the UTC instants stored in the database.
 */
final class BookingTime
{
    public static function timezone(): string
    {
        return config('booking.timezone');
    }

    /**
     * Current local date in the restaurant timezone.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    /**
     * Parses a "Y-m-d" local calendar date.
     */
    public static function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());
    }

    /**
     * UTC instant of a local wall clock time ("H:i" or "H:i:s") on a local date.
     */
    public static function at(CarbonInterface $localDate, string $time): CarbonImmutable
    {
        return self::atMinute($localDate, self::minuteOfDay($time));
    }

    /**
     * UTC instant of the given minute after local midnight on a local date.
     */
    public static function atMinute(CarbonInterface $localDate, int $minuteOfDay): CarbonImmutable
    {
        return CarbonImmutable::create(
            $localDate->year,
            $localDate->month,
            $localDate->day,
            intdiv($minuteOfDay, 60),
            $minuteOfDay % 60,
            0,
            self::timezone(),
        )->utc();
    }

    /**
     * UTC instant of local midnight at the start of the given local date.
     */
    public static function localDayStart(CarbonInterface $localDate): CarbonImmutable
    {
        return self::atMinute($localDate, 0);
    }

    public static function toLocal(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::timezone());
    }

    public static function minuteOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
