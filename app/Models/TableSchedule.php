<?php

namespace App\Models;

use App\Support\BookingTime;
use Database\Factories\TableScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A range of allowed booking START times on a day of the week (1 = Monday ... 7 = Sunday).
 * Both ends are inclusive: last_booking_time is the last start time customers can pick.
 *
 * @property int $day_of_week
 * @property string $first_booking_time
 * @property string $last_booking_time
 */
class TableSchedule extends Model
{
    /** @use HasFactory<TableScheduleFactory> */
    use HasFactory;

    public const DAY_NAMES = [
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
        7 => 'Domenica',
    ];

    protected $fillable = [
        'day_of_week',
        'first_booking_time',
        'last_booking_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function firstBookingMinute(): int
    {
        return BookingTime::minuteOfDay($this->first_booking_time);
    }

    public function lastBookingMinute(): int
    {
        return BookingTime::minuteOfDay($this->last_booking_time);
    }
}
