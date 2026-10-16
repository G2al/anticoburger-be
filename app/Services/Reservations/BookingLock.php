<?php

namespace App\Services\Reservations;

use App\Models\BookingSettings;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Serializes every operation that reads or changes table occupancy.
 *
 * The callback runs inside a transaction after acquiring an exclusive row lock
 * (SELECT ... FOR UPDATE) on the booking_settings singleton. The locking read is the
 * first statement of the transaction, so with InnoDB REPEATABLE READ every following
 * read sees the data committed by the previous lock holder. Deadlocks and lost
 * connections are retried by Laravel's transaction helper.
 */
class BookingLock
{
    private const TRANSACTION_ATTEMPTS = 3;

    /**
     * @template TResult
     *
     * @param  Closure(BookingSettings): TResult  $callback
     * @return TResult
     */
    public function run(Closure $callback): mixed
    {
        return DB::transaction(
            fn () => $callback(BookingSettings::lockForBooking()),
            self::TRANSACTION_ATTEMPTS,
        );
    }
}
