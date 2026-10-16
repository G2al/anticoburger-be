<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableAllowedPartySize extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'party_size',
    ];

    protected function casts(): array
    {
        return [
            'party_size' => 'integer',
        ];
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }
}
