<?php

namespace App\Models;

use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Support\BookingTime;
use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Instants are stored in UTC. The occupancy interval used for conflicts is
 * [starts_at, occupied_until), where occupied_until includes the table turnaround.
 *
 * @property int $id
 * @property string $public_reference
 * @property int $restaurant_table_id
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $phone_normalized
 * @property string|null $email
 * @property string|null $notes
 * @property int $party_size
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $occupied_until
 * @property ReservationStatus $status
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_token_hash
 * @property ReservationChannel $created_via
 * @property CarbonImmutable $created_at
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_table_id',
        'first_name',
        'last_name',
        'phone',
        'phone_normalized',
        'email',
        'notes',
        'party_size',
        'starts_at',
        'ends_at',
        'occupied_until',
        'status',
        'created_via',
    ];

    protected $hidden = [
        'cancellation_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'restaurant_table_id' => 'integer',
            'party_size' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'occupied_until' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'status' => ReservationStatus::class,
            'created_via' => ReservationChannel::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            $reservation->public_reference ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    #[Scope]
    protected function confirmed(Builder $query): void
    {
        $query->where('status', ReservationStatus::Confirmed);
    }

    /**
     * Reservations whose occupancy [starts_at, occupied_until) overlaps [$from, $until).
     */
    #[Scope]
    protected function occupying(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $query->where('starts_at', '<', $until->utc())
            ->where('occupied_until', '>', $from->utc());
    }

    /**
     * Reservations starting on the given local calendar date.
     */
    #[Scope]
    protected function startingOnLocalDate(Builder $query, CarbonImmutable $localDate): void
    {
        $query->where('starts_at', '>=', BookingTime::localDayStart($localDate))
            ->where('starts_at', '<', BookingTime::localDayStart($localDate->addDay()));
    }

    public function isConfirmed(): bool
    {
        return $this->status === ReservationStatus::Confirmed;
    }

    public function isCancelled(): bool
    {
        return $this->status === ReservationStatus::Cancelled;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function localStartsAt(): CarbonImmutable
    {
        return BookingTime::toLocal($this->starts_at);
    }

    public function localEndsAt(): CarbonImmutable
    {
        return BookingTime::toLocal($this->ends_at);
    }

    public function overlaps(CarbonImmutable $from, CarbonImmutable $until): bool
    {
        return $this->starts_at->lt($until) && $this->occupied_until->gt($from);
    }

    /**
     * Stores the hash of a new random cancellation token and returns the plain token,
     * which is never persisted.
     */
    public function issueCancellationToken(): string
    {
        $token = Str::random(48);

        $this->cancellation_token_hash = self::hashToken($token);

        return $token;
    }

    public function matchesCancellationToken(?string $token): bool
    {
        if ($this->cancellation_token_hash === null || $token === null || $token === '') {
            return false;
        }

        return hash_equals($this->cancellation_token_hash, self::hashToken($token));
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
