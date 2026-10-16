<?php

namespace Tests\Concerns;

use App\Models\BookingSettings;
use App\Models\RestaurantTable;
use Carbon\CarbonImmutable;

/**
 * Shared fixtures for booking tests. The clock is frozen on Monday 2026-10-12 at
 * 10:00 Europe/Rome; 2026-10-14 is a Wednesday.
 */
trait BuildsBookingScenarios
{
    protected const TODAY = '2026-10-12';

    protected const WEDNESDAY = '2026-10-14';

    protected const THURSDAY = '2026-10-15';

    protected function freezeBookingClock(string $localDateTime = '2026-10-12 10:00'): void
    {
        $this->travelTo(CarbonImmutable::parse($localDateTime, 'Europe/Rome'));
    }

    /**
     * Active table open every day with one booking range.
     *
     * @param  list<int>  $partySizes
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $days
     */
    protected function createTable(
        array $partySizes = [2, 3, 4],
        array $attributes = [],
        string $firstBookingTime = '19:00',
        string $lastBookingTime = '23:00',
        array $days = [1, 2, 3, 4, 5, 6, 7],
    ): RestaurantTable {
        return RestaurantTable::factory()
            ->withPartySizes($partySizes)
            ->withSchedule($firstBookingTime.':00', $lastBookingTime.':00', $days)
            ->create($attributes)
            ->load(['allowedPartySizes', 'schedules']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function updateSettings(array $values): BookingSettings
    {
        $settings = BookingSettings::current();
        $settings->update($values);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function reservationData(array $overrides = []): array
    {
        return [
            'date' => self::WEDNESDAY,
            'time' => '20:00',
            'party_size' => 2,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'phone' => '333 123 4567',
            'email' => 'mario@example.com',
            'notes' => null,
            ...$overrides,
        ];
    }
}
