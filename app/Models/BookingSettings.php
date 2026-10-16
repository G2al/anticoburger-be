<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id = 1) with the global booking rules.
 * A value of 0 disables the corresponding limit.
 *
 * @property int $min_lead_minutes
 * @property int $max_advance_days
 * @property int $max_reservations_per_phone_per_day
 * @property bool $prevent_duplicate_bookings
 */
class BookingSettings extends Model
{
    public const SINGLETON_ID = 1;

    protected $table = 'booking_settings';

    protected $fillable = [
        'min_lead_minutes',
        'max_advance_days',
        'max_reservations_per_phone_per_day',
        'prevent_duplicate_bookings',
    ];

    protected function casts(): array
    {
        return [
            'min_lead_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'max_reservations_per_phone_per_day' => 'integer',
            'prevent_duplicate_bookings' => 'boolean',
        ];
    }

    /**
     * Returns the singleton row, creating it with the column defaults when missing.
     * The insert is idempotent, so concurrent calls never create a second row.
     */
    public static function current(): self
    {
        $settings = static::query()->find(self::SINGLETON_ID);

        if ($settings !== null) {
            return $settings;
        }

        static::query()->insertOrIgnore([
            'id' => self::SINGLETON_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return static::query()->findOrFail(self::SINGLETON_ID);
    }

    /**
     * Locks the singleton row until the end of the current transaction.
     * Every operation that changes table occupancy is serialized on this lock.
     * The locking read must be the first statement of the transaction (see BookingLock).
     */
    public static function lockForBooking(): self
    {
        $lockedSettings = static::query()->whereKey(self::SINGLETON_ID)->lockForUpdate()->first();

        if ($lockedSettings !== null) {
            return $lockedSettings;
        }

        static::current();

        return static::query()->whereKey(self::SINGLETON_ID)->lockForUpdate()->firstOrFail();
    }
}
