<?php

namespace Tests\Unit;

use App\Support\BookingTime;
use Tests\TestCase;

class BookingTimeTest extends TestCase
{
    public function test_local_times_are_converted_to_utc_in_summer_and_winter(): void
    {
        $this->assertSame('2026-10-24 18:00:00', BookingTime::at(BookingTime::date('2026-10-24'), '20:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 19:00:00', BookingTime::at(BookingTime::date('2026-10-25'), '20:00')->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', BookingTime::at(BookingTime::date('2026-10-25'), '20:00')->getTimezone()->getName());
    }

    public function test_local_day_on_dst_change_lasts_twenty_five_hours(): void
    {
        $dayStart = BookingTime::localDayStart(BookingTime::date('2026-10-25'));
        $nextDayStart = BookingTime::localDayStart(BookingTime::date('2026-10-26'));

        $this->assertSame(25 * 60, (int) $dayStart->diffInMinutes($nextDayStart));
    }

    public function test_instants_are_shown_in_the_restaurant_timezone(): void
    {
        $instant = BookingTime::at(BookingTime::date('2026-10-14'), '23:30');

        $this->assertSame('2026-10-14T23:30:00+02:00', BookingTime::toLocal($instant)->toIso8601String());
    }
}
