<?php

namespace App\Services\Reservations;

use App\Enums\BlockScope;
use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages availability blocks. Blocks never cancel or change existing reservations:
 * they only prevent new ones. Reservations already overlapping a block can be listed
 * through affectedReservations().
 *
 * Expected input keys: title, scope, restaurant_table_id, starts_on, ends_on,
 * is_all_day, start_time, end_time, is_active.
 */
class AvailabilityBlockService
{
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    public function __construct(private readonly BookingLock $bookingLock) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): AvailabilityBlock
    {
        $attributes = $this->validate($data);

        return $this->bookingLock->run(fn () => AvailabilityBlock::query()->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(AvailabilityBlock $block, array $data): AvailabilityBlock
    {
        $attributes = $this->validate($data);

        return $this->bookingLock->run(function () use ($block, $attributes) {
            $block->update($attributes);

            return $block;
        });
    }

    public function setActive(AvailabilityBlock $block, bool $isActive): AvailabilityBlock
    {
        return $this->bookingLock->run(function () use ($block, $isActive) {
            $block->update(['is_active' => $isActive]);

            return $block;
        });
    }

    public function delete(AvailabilityBlock $block): void
    {
        $this->bookingLock->run(fn () => $block->delete());
    }

    /**
     * Confirmed reservations whose occupancy overlaps the block, ordered by start.
     *
     * @return Collection<int, Reservation>
     */
    public function affectedReservations(AvailabilityBlock $block): Collection
    {
        $intervals = $block->intervals();

        if ($intervals === []) {
            return new Collection;
        }

        return Reservation::query()
            ->confirmed()
            ->when($block->scope === BlockScope::Table, fn (Builder $query) => $query->where('restaurant_table_id', $block->restaurant_table_id))
            ->occupying($intervals[0][0], $intervals[array_key_last($intervals)][1])
            ->with('restaurantTable')
            ->orderBy('starts_at')
            ->get()
            ->filter(function (Reservation $reservation) use ($intervals) {
                foreach ($intervals as [$from, $until]) {
                    if ($reservation->overlaps($from, $until)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        if (($data['scope'] ?? null) instanceof BlockScope) {
            $data['scope'] = $data['scope']->value;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:150'],
            'scope' => ['required', Rule::enum(BlockScope::class)],
            'restaurant_table_id' => [
                Rule::requiredIf(($data['scope'] ?? null) === BlockScope::Table->value),
                Rule::prohibitedIf(($data['scope'] ?? null) === BlockScope::Restaurant->value),
                'nullable',
                'integer',
                Rule::exists('restaurant_tables', 'id'),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'is_all_day' => ['required', 'boolean'],
            'start_time' => ['exclude_if:is_all_day,true', 'exclude_if:is_all_day,1', 'required', 'regex:'.self::TIME_PATTERN],
            'end_time' => ['exclude_if:is_all_day,true', 'exclude_if:is_all_day,1', 'required', 'regex:'.self::TIME_PATTERN],
            'is_active' => ['required', 'boolean'],
        ], [], [
            'title' => 'titolo',
            'scope' => 'ambito',
            'restaurant_table_id' => 'tavolo',
            'starts_on' => 'data di inizio',
            'ends_on' => 'data di fine',
            'is_all_day' => 'intera giornata',
            'start_time' => 'ora di inizio',
            'end_time' => 'ora di fine',
        ])->validate();

        $isAllDay = (bool) $validated['is_all_day'];

        if (! $isAllDay && substr($validated['end_time'], 0, 5) <= substr($validated['start_time'], 0, 5)) {
            throw ValidationException::withMessages([
                'end_time' => "L'ora di fine deve essere successiva all'ora di inizio (le fasce non possono superare la mezzanotte).",
            ]);
        }

        return [
            'title' => trim($validated['title']),
            'scope' => $validated['scope'],
            'restaurant_table_id' => $validated['scope'] === BlockScope::Table->value ? (int) $validated['restaurant_table_id'] : null,
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'is_all_day' => $isAllDay,
            'start_time' => $isAllDay ? null : substr($validated['start_time'], 0, 5).':00',
            'end_time' => $isAllDay ? null : substr($validated['end_time'], 0, 5).':00',
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
