<?php

namespace App\Models;

use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $duration_minutes
 * @property int $slot_interval_minutes
 * @property int $turnaround_minutes
 * @property int $assignment_priority
 * @property bool $is_active
 */
class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'duration_minutes',
        'slot_interval_minutes',
        'turnaround_minutes',
        'assignment_priority',
        'is_active',
    ];

    protected $attributes = [
        'turnaround_minutes' => 0,
        'assignment_priority' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'slot_interval_minutes' => 'integer',
            'turnaround_minutes' => 'integer',
            'assignment_priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<TableAllowedPartySize, $this> */
    public function allowedPartySizes(): HasMany
    {
        return $this->hasMany(TableAllowedPartySize::class);
    }

    /** @return HasMany<TableSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(TableSchedule::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @return HasMany<AvailabilityBlock, $this> */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function acceptingPartySize(Builder $query, int $partySize): void
    {
        $query->whereHas('allowedPartySizes', fn (Builder $sizes) => $sizes->where('party_size', $partySize));
    }

    /**
     * @return list<int>
     */
    public function partySizes(): array
    {
        return $this->allowedPartySizes->pluck('party_size')->map(fn ($size) => (int) $size)->sort()->values()->all();
    }

    public function acceptsPartySize(int $partySize): bool
    {
        return in_array($partySize, $this->partySizes(), true);
    }

    public function hasReservationHistory(): bool
    {
        return $this->reservations()->exists();
    }
}
