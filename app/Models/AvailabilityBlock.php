<?php

namespace App\Models;

use App\Enums\BlockScope;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Database\Factories\AvailabilityBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Closes the whole restaurant or a single table on one or more consecutive local dates,
 * either for the whole day or for the same time range on every included date.
 *
 * @property int $id
 * @property string $title
 * @property BlockScope $scope
 * @property int|null $restaurant_table_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_all_day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property bool $is_active
 */
class AvailabilityBlock extends Model
{
    /** @use HasFactory<AvailabilityBlockFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'scope',
        'restaurant_table_id',
        'starts_on',
        'ends_on',
        'is_all_day',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $attributes = [
        'is_all_day' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'scope' => BlockScope::class,
            'restaurant_table_id' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_all_day' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function coveringDates(Builder $query, CarbonImmutable $fromDate, CarbonImmutable $toDate): void
    {
        $query->whereDate('starts_on', '<=', $toDate->toDateString())
            ->whereDate('ends_on', '>=', $fromDate->toDateString());
    }

    public function appliesToTable(int $restaurantTableId): bool
    {
        return $this->scope === BlockScope::Restaurant
            || $this->restaurant_table_id === $restaurantTableId;
    }

    /**
     * The real time intervals [start, end) in UTC closed by this block on the local
     * dates between $fromDate and $toDate (inclusive).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function intervalsBetween(CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $firstDate = max($this->starts_on->toDateString(), $fromDate->toDateString());
        $lastDate = min($this->ends_on->toDateString(), $toDate->toDateString());

        $intervals = [];

        for ($date = $firstDate; $date <= $lastDate; $date = BookingTime::date($date)->addDay()->toDateString()) {
            $localDate = BookingTime::date($date);

            $intervals[] = $this->is_all_day
                ? [BookingTime::localDayStart($localDate), BookingTime::localDayStart($localDate->addDay())]
                : [BookingTime::at($localDate, $this->start_time), BookingTime::at($localDate, $this->end_time)];
        }

        return $intervals;
    }

    /**
     * Real time intervals across the whole block.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function intervals(): array
    {
        return $this->intervalsBetween($this->starts_on, $this->ends_on);
    }

    public function describePeriod(): string
    {
        $dates = $this->starts_on->equalTo($this->ends_on)
            ? $this->starts_on->format('d/m/Y')
            : $this->starts_on->format('d/m/Y').' – '.$this->ends_on->format('d/m/Y');

        if ($this->is_all_day) {
            return $dates.', intera giornata';
        }

        return $dates.', '.substr((string) $this->start_time, 0, 5).' – '.substr((string) $this->end_time, 0, 5);
    }
}
