<?php

namespace App\Services\Reservations;

use App\Models\RestaurantTable;
use App\Models\TableSchedule;
use App\Support\BookingTime;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates tables together with their allowed party sizes and weekly schedule.
 *
 * Expected input keys: name, duration_minutes, slot_interval_minutes, turnaround_minutes,
 * assignment_priority, is_active, party_sizes (list of ints) and schedules
 * (list of {day_of_week, first_booking_time, last_booking_time}).
 */
class RestaurantTableService
{
    public const MIN_DURATION_MINUTES = 10;

    public const MAX_DURATION_MINUTES = 720;

    public const MIN_SLOT_INTERVAL_MINUTES = 5;

    public const MAX_TURNAROUND_MINUTES = 240;

    public const MAX_ASSIGNMENT_PRIORITY = 1000;

    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    public function __construct(private readonly BookingLock $bookingLock) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): RestaurantTable
    {
        $validated = $this->validate($data);

        return $this->bookingLock->run(function () use ($validated) {
            $table = RestaurantTable::query()->create($this->tableAttributes($validated));
            $this->syncPartySizes($table, $validated['party_sizes']);
            $this->syncSchedules($table, $validated['schedules']);

            return $table;
        });
    }

    /**
     * Existing reservations keep their stored end times: changing duration or
     * turnaround only affects new bookings.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(RestaurantTable $table, array $data): RestaurantTable
    {
        $validated = $this->validate($data, $table);

        return $this->bookingLock->run(function () use ($table, $validated) {
            $table->update($this->tableAttributes($validated));
            $this->syncPartySizes($table, $validated['party_sizes']);
            $this->syncSchedules($table, $validated['schedules']);

            return $table;
        });
    }

    /**
     * @throws ValidationException
     */
    public function setActive(RestaurantTable $table, bool $isActive): RestaurantTable
    {
        return $this->bookingLock->run(function () use ($table, $isActive) {
            if ($isActive && ! $table->allowedPartySizes()->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => 'Per attivare il tavolo configura almeno una capienza ammessa.',
                ]);
            }

            $table->update(['is_active' => $isActive]);

            return $table;
        });
    }

    /**
     * Tables with reservation history cannot be deleted: deactivate them instead.
     *
     * @throws ValidationException
     */
    public function delete(RestaurantTable $table): void
    {
        $this->bookingLock->run(function () use ($table) {
            if ($table->hasReservationHistory()) {
                throw ValidationException::withMessages([
                    'table' => 'Il tavolo ha uno storico prenotazioni e non può essere eliminato: disattivalo.',
                ]);
            }

            $table->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $data, ?RestaurantTable $table = null): array
    {
        $data['party_sizes'] = array_values(array_map('intval', $data['party_sizes'] ?? []));
        $data['schedules'] = array_values($data['schedules'] ?? []);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:100', Rule::unique('restaurant_tables', 'name')->ignore($table?->id)],
            'duration_minutes' => ['required', 'integer', 'between:'.self::MIN_DURATION_MINUTES.','.self::MAX_DURATION_MINUTES],
            'slot_interval_minutes' => ['required', 'integer', 'between:'.self::MIN_SLOT_INTERVAL_MINUTES.','.self::MAX_DURATION_MINUTES],
            'turnaround_minutes' => ['required', 'integer', 'between:0,'.self::MAX_TURNAROUND_MINUTES],
            'assignment_priority' => ['required', 'integer', 'between:0,'.self::MAX_ASSIGNMENT_PRIORITY],
            'is_active' => ['required', 'boolean'],
            'party_sizes' => [Rule::requiredIf((bool) ($data['is_active'] ?? false)), 'array'],
            'party_sizes.*' => ['integer', 'distinct', 'between:1,'.config('booking.max_party_size')],
            'schedules' => ['array'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.first_booking_time' => ['required', 'regex:'.self::TIME_PATTERN],
            'schedules.*.last_booking_time' => ['required', 'regex:'.self::TIME_PATTERN],
        ], [
            'party_sizes.required' => 'Un tavolo attivo deve avere almeno una capienza ammessa.',
        ], [
            'name' => 'nome',
            'duration_minutes' => 'durata',
            'slot_interval_minutes' => 'intervallo slot',
            'turnaround_minutes' => 'tempo di riassetto',
            'assignment_priority' => 'priorità di assegnazione',
            'party_sizes' => 'capienze ammesse',
            'schedules.*.day_of_week' => 'giorno',
            'schedules.*.first_booking_time' => 'primo orario',
            'schedules.*.last_booking_time' => 'ultimo orario',
        ])->validate();

        $validated['schedules'] = $this->validatedSchedules($validated['schedules'] ?? []);

        return $validated;
    }

    /**
     * Each range must start before or at its end and must not overlap other ranges
     * of the same day (both ends are inclusive start times).
     *
     * @param  list<array<string, mixed>>  $schedules
     * @return list<array{day_of_week: int, first_booking_time: string, last_booking_time: string}>
     *
     * @throws ValidationException
     */
    private function validatedSchedules(array $schedules): array
    {
        $normalized = [];

        foreach ($schedules as $index => $schedule) {
            $first = BookingTime::minuteOfDay($schedule['first_booking_time']);
            $last = BookingTime::minuteOfDay($schedule['last_booking_time']);

            if ($first > $last) {
                throw ValidationException::withMessages([
                    "schedules.$index.last_booking_time" => "L'ultimo orario deve essere uguale o successivo al primo orario.",
                ]);
            }

            $normalized[] = [
                'day_of_week' => (int) $schedule['day_of_week'],
                'first_booking_time' => sprintf('%02d:%02d:00', intdiv($first, 60), $first % 60),
                'last_booking_time' => sprintf('%02d:%02d:00', intdiv($last, 60), $last % 60),
            ];
        }

        foreach ($normalized as $index => $schedule) {
            foreach ($normalized as $otherIndex => $other) {
                $overlaps = $otherIndex > $index
                    && $schedule['day_of_week'] === $other['day_of_week']
                    && $schedule['first_booking_time'] <= $other['last_booking_time']
                    && $other['first_booking_time'] <= $schedule['last_booking_time'];

                if ($overlaps) {
                    throw ValidationException::withMessages([
                        "schedules.$otherIndex.first_booking_time" => 'Le fasce di '.TableSchedule::DAY_NAMES[$schedule['day_of_week']].' si sovrappongono.',
                    ]);
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function tableAttributes(array $validated): array
    {
        return [
            'name' => trim($validated['name']),
            'duration_minutes' => (int) $validated['duration_minutes'],
            'slot_interval_minutes' => (int) $validated['slot_interval_minutes'],
            'turnaround_minutes' => (int) $validated['turnaround_minutes'],
            'assignment_priority' => (int) $validated['assignment_priority'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    /**
     * @param  list<int>  $partySizes
     */
    private function syncPartySizes(RestaurantTable $table, array $partySizes): void
    {
        $table->allowedPartySizes()->whereNotIn('party_size', $partySizes)->delete();

        $existing = $table->allowedPartySizes()->pluck('party_size')->all();

        foreach (array_diff($partySizes, $existing) as $partySize) {
            $table->allowedPartySizes()->create(['party_size' => $partySize]);
        }

        $table->unsetRelation('allowedPartySizes');
    }

    /**
     * @param  list<array{day_of_week: int, first_booking_time: string, last_booking_time: string}>  $schedules
     */
    private function syncSchedules(RestaurantTable $table, array $schedules): void
    {
        $table->schedules()->delete();
        $table->schedules()->createMany($schedules);
        $table->unsetRelation('schedules');
    }
}
