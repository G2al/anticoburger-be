<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasLabel
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Confermata',
            self::Cancelled => 'Annullata',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
