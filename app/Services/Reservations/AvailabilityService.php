<?php

namespace App\Services\Reservations;

use App\Enums\BlockScope;
use App\Enums\BookingError;
use App\Enums\CalendarDayStatus;
use App\Exceptions\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\TableSchedule;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Computes which tables are free and which start times are bookable.
 *
 * Slots are generated on the fly from each table's weekly schedule; every table is
 * evaluated separately because duration, interval and turnaround differ per table.
 * A table is free at a start time when its occupancy [start, start + duration + turnaround)
 * overlaps neither an active block nor a confirmed reservation on the same table.
 */
class AvailabilityService
{
    /**
     * Local "H:i" start times at which at least one compatible table is free,
     * honoring the public booking window (lead time and advance limit).
     *
     * @return list<string>
     */
    public function publicSlotsFor(CarbonImmutable $localDate, int $partySize, BookingSettings $settings): array
    {
        $this->ensureDateIsPubliclyBookable($localDate, $settings);

        $tables = $this->compatibleTables($partySize);

        if ($tables->isEmpty()) {
            return [];
        }

        $occupancy = $this->loadOccupancy($tables, $localDate, $localDate);
        $earliestStart = $this->earliestPublicStart($settings);
        $slots = [];

        foreach ($tables as $table) {
            foreach ($this->startTimesFor($table, $localDate) as $startsAt) {
                if ($startsAt->lt($earliestStart)) {
                    continue;
                }

                if ($this->isFree($table, $startsAt, $occupancy)) {
                    $slots[BookingTime::toLocal($startsAt)->format('H:i')] = true;
                }
            }
        }

        $slots = array_keys($slots);
        sort($slots);

        return $slots;
    }

    /**
     * Compatible tables that can host the party at the given instant, in best-fit order.
     * The start time must be one of the table's scheduled start times.
     *
     * @return Collection<int, RestaurantTable>
     */
    public function freeTablesAt(CarbonImmutable $startsAt, int $partySize, ?Reservation $ignoring = null): Collection
    {
        $localDate = BookingTime::toLocal($startsAt)->startOfDay();

        $tables = $this->compatibleTables($partySize)->filter(
            fn (RestaurantTable $table) => $this->isScheduledStart($table, $localDate, $startsAt)
        );

        if ($tables->isEmpty()) {
            return $tables;
        }

        $occupancy = $this->loadOccupancy($tables, $localDate, $localDate, $ignoring);

        return $this->sortByBestFit(
            $tables->filter(fn (RestaurantTable $table) => $this->isFree($table, $startsAt, $occupancy))
        );
    }

    public function isPartySizeBookable(int $partySize): bool
    {
        return RestaurantTable::query()->active()->acceptingPartySize($partySize)->exists();
    }

    /**
     * Distinct party sizes accepted by at least one active table, ascending.
     *
     * @return list<int>
     */
    public function bookablePartySizes(): array
    {
        return RestaurantTable::query()
            ->active()
            ->join('table_allowed_party_sizes', 'table_allowed_party_sizes.restaurant_table_id', '=', 'restaurant_tables.id')
            ->distinct()
            ->orderBy('table_allowed_party_sizes.party_size')
            ->pluck('table_allowed_party_sizes.party_size')
            ->map(fn ($size) => (int) $size)
            ->values()
            ->all();
    }

    /**
     * Public status of every day of a month. Uses the same rules as publicSlotsFor():
     * a day is "available" exactly when publicSlotsFor() returns at least one slot.
     * All tables, blocks and reservations of the month are loaded with a fixed number of queries.
     *
     * @return array<string, CalendarDayStatus> keyed by local "Y-m-d"
     *
     * @throws BookingException
     */
    public function publicCalendarFor(CarbonImmutable $firstDayOfMonth, int $partySize, BookingSettings $settings): array
    {
        $this->ensureMonthIsPubliclyViewable($firstDayOfMonth);

        $dates = [];

        for ($date = $firstDayOfMonth->startOfMonth(); $date->month === $firstDayOfMonth->month; $date = $date->addDay()) {
            $dates[] = $date;
        }

        $bookableDates = array_values(array_filter($dates, fn (CarbonImmutable $date) => $this->isDateWithinPublicWindow($date, $settings)));
        $tables = $bookableDates === [] ? new Collection : $this->compatibleTables($partySize);
        $occupancy = $tables->isEmpty()
            ? ['blocks' => [], 'reservations' => collect()]
            : $this->loadOccupancy($tables, $bookableDates[0], $bookableDates[array_key_last($bookableDates)]);
        $earliestStart = $this->earliestPublicStart($settings);

        $calendar = [];

        foreach ($dates as $date) {
            $calendar[$date->toDateString()] = $this->isDateWithinPublicWindow($date, $settings)
                ? $this->dayStatus($tables, $date, $occupancy, $earliestStart)
                : CalendarDayStatus::NotBookable;
        }

        return $calendar;
    }

    /**
     * @throws BookingException
     */
    public function ensureDateIsPubliclyBookable(CarbonImmutable $localDate, BookingSettings $settings): void
    {
        if (! $this->isDateWithinPublicWindow($localDate, $settings)) {
            throw BookingException::because(BookingError::DateOutOfRange);
        }
    }

    /**
     * From the current month up to config booking.calendar_max_months_ahead months later.
     *
     * @throws BookingException
     */
    public function ensureMonthIsPubliclyViewable(CarbonImmutable $firstDayOfMonth): void
    {
        $month = $firstDayOfMonth->format('Y-m');
        $currentMonth = BookingTime::today()->startOfMonth();
        $lastViewableMonth = $currentMonth->addMonthsNoOverflow((int) config('booking.calendar_max_months_ahead'));

        if ($month < $currentMonth->format('Y-m') || $month > $lastViewableMonth->format('Y-m')) {
            throw BookingException::because(BookingError::MonthOutOfRange);
        }
    }

    public function isDateWithinPublicWindow(CarbonImmutable $localDate, BookingSettings $settings): bool
    {
        $today = BookingTime::today();
        $date = $localDate->toDateString();

        if ($date < $today->toDateString()) {
            return false;
        }

        return $settings->max_advance_days === 0
            || $date <= $today->addDays($settings->max_advance_days)->toDateString();
    }

    /**
     * @throws BookingException
     */
    public function ensureStartIsPubliclyBookable(CarbonImmutable $startsAt, BookingSettings $settings): void
    {
        $this->ensureDateIsPubliclyBookable(BookingTime::toLocal($startsAt)->startOfDay(), $settings);

        if ($startsAt->lt($this->earliestPublicStart($settings))) {
            throw BookingException::because(BookingError::BookingTooSoon);
        }
    }

    /**
     * Scheduled start instants (UTC) of a table on a local date, deduplicated and sorted.
     *
     * @return list<CarbonImmutable>
     */
    public function startTimesFor(RestaurantTable $table, CarbonImmutable $localDate): array
    {
        $startTimes = [];

        $schedules = $table->schedules->where('day_of_week', $localDate->dayOfWeekIso);

        foreach ($schedules as $schedule) {
            /** @var TableSchedule $schedule */
            for ($minute = $schedule->firstBookingMinute(); $minute <= $schedule->lastBookingMinute(); $minute += $table->slot_interval_minutes) {
                $startsAt = BookingTime::atMinute($localDate, $minute);
                $startTimes[$startsAt->getTimestamp()] = $startsAt;
            }
        }

        ksort($startTimes);

        return array_values($startTimes);
    }

    public function occupiedUntil(RestaurantTable $table, CarbonImmutable $startsAt): CarbonImmutable
    {
        return $startsAt->addMinutes($table->duration_minutes + $table->turnaround_minutes);
    }

    /**
     * @return Collection<int, RestaurantTable>
     */
    private function compatibleTables(int $partySize): Collection
    {
        return RestaurantTable::query()
            ->active()
            ->acceptingPartySize($partySize)
            ->with(['allowedPartySizes', 'schedules'])
            ->get();
    }

    private function isScheduledStart(RestaurantTable $table, CarbonImmutable $localDate, CarbonImmutable $startsAt): bool
    {
        foreach ($this->startTimesFor($table, $localDate) as $scheduledStart) {
            if ($scheduledStart->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Status of one bookable day, consistent with publicSlotsFor():
     * no scheduled start → closed; every start before the lead time → not bookable;
     * every remaining start blocked → closed; every unblocked start reserved → fully booked.
     *
     * @param  Collection<int, RestaurantTable>  $tables
     * @param  array{blocks: list<array{table_id: int|null, from: CarbonImmutable, until: CarbonImmutable}>, reservations: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Reservation>>}  $occupancy
     */
    private function dayStatus(Collection $tables, CarbonImmutable $localDate, array $occupancy, CarbonImmutable $earliestStart): CalendarDayStatus
    {
        $hasScheduledStart = false;
        $hasStartAfterLeadTime = false;
        $hasUnblockedStart = false;

        foreach ($tables as $table) {
            foreach ($this->startTimesFor($table, $localDate) as $startsAt) {
                $hasScheduledStart = true;

                if ($startsAt->lt($earliestStart)) {
                    continue;
                }

                $hasStartAfterLeadTime = true;

                if ($this->isBlocked($table, $startsAt, $occupancy)) {
                    continue;
                }

                $hasUnblockedStart = true;

                if (! $this->isReserved($table, $startsAt, $occupancy)) {
                    return CalendarDayStatus::Available;
                }
            }
        }

        return match (true) {
            ! $hasScheduledStart, $hasStartAfterLeadTime && ! $hasUnblockedStart => CalendarDayStatus::Closed,
            ! $hasStartAfterLeadTime => CalendarDayStatus::NotBookable,
            default => CalendarDayStatus::FullyBooked,
        };
    }

    /**
     * Loads blocks and confirmed reservations that may overlap occupancies starting
     * between $fromDate and $toDate. Occupancies can run past midnight, so the day after
     * $toDate is included.
     *
     * @param  Collection<int, RestaurantTable>  $tables
     * @return array{blocks: list<array{table_id: int|null, from: CarbonImmutable, until: CarbonImmutable}>, reservations: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Reservation>>}
     */
    private function loadOccupancy(Collection $tables, CarbonImmutable $fromDate, CarbonImmutable $toDate, ?Reservation $ignoring = null): array
    {
        $tableIds = $tables->modelKeys();
        $dayAfter = $toDate->addDay();

        $blocks = AvailabilityBlock::query()
            ->active()
            ->coveringDates($fromDate, $dayAfter)
            ->where(fn (Builder $query) => $query
                ->where('scope', BlockScope::Restaurant)
                ->orWhereIn('restaurant_table_id', $tableIds))
            ->get();

        $blockIntervals = [];

        foreach ($blocks as $block) {
            foreach ($block->intervalsBetween($fromDate, $dayAfter) as [$from, $until]) {
                $blockIntervals[] = [
                    'table_id' => $block->scope === BlockScope::Restaurant ? null : $block->restaurant_table_id,
                    'from' => $from,
                    'until' => $until,
                ];
            }
        }

        $reservations = Reservation::query()
            ->confirmed()
            ->whereIn('restaurant_table_id', $tableIds)
            ->occupying(BookingTime::localDayStart($fromDate), BookingTime::localDayStart($dayAfter->addDay()))
            ->when($ignoring?->exists, fn (Builder $query) => $query->whereKeyNot($ignoring->getKey()))
            ->get(['id', 'restaurant_table_id', 'starts_at', 'occupied_until'])
            ->groupBy('restaurant_table_id');

        return ['blocks' => $blockIntervals, 'reservations' => $reservations];
    }

    /**
     * @param  array{blocks: list<array{table_id: int|null, from: CarbonImmutable, until: CarbonImmutable}>, reservations: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Reservation>>}  $occupancy
     */
    private function isFree(RestaurantTable $table, CarbonImmutable $startsAt, array $occupancy): bool
    {
        return ! $this->isBlocked($table, $startsAt, $occupancy)
            && ! $this->isReserved($table, $startsAt, $occupancy);
    }

    /**
     * @param  array{blocks: list<array{table_id: int|null, from: CarbonImmutable, until: CarbonImmutable}>, reservations: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Reservation>>}  $occupancy
     */
    private function isBlocked(RestaurantTable $table, CarbonImmutable $startsAt, array $occupancy): bool
    {
        $occupiedUntil = $this->occupiedUntil($table, $startsAt);

        foreach ($occupancy['blocks'] as $block) {
            $appliesToTable = $block['table_id'] === null || $block['table_id'] === $table->id;

            if ($appliesToTable && $block['from']->lt($occupiedUntil) && $block['until']->gt($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{blocks: list<array{table_id: int|null, from: CarbonImmutable, until: CarbonImmutable}>, reservations: \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Reservation>>}  $occupancy
     */
    private function isReserved(RestaurantTable $table, CarbonImmutable $startsAt, array $occupancy): bool
    {
        $occupiedUntil = $this->occupiedUntil($table, $startsAt);

        foreach ($occupancy['reservations']->get($table->id, []) as $reservation) {
            if ($reservation->overlaps($startsAt, $occupiedUntil)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Best fit: smallest maximum capacity, then fewest supported party sizes,
     * then administrative priority (lower first), then id.
     *
     * @param  Collection<int, RestaurantTable>  $tables
     * @return Collection<int, RestaurantTable>
     */
    private function sortByBestFit(Collection $tables): Collection
    {
        return $tables
            ->sort(fn (RestaurantTable $a, RestaurantTable $b) => [
                max($a->partySizes()),
                count($a->partySizes()),
                $a->assignment_priority,
                $a->id,
            ] <=> [
                max($b->partySizes()),
                count($b->partySizes()),
                $b->assignment_priority,
                $b->id,
            ])
            ->values();
    }

    private function earliestPublicStart(BookingSettings $settings): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addMinutes($settings->min_lead_minutes);
    }
}
