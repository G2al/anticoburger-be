<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationChannel: string implements HasLabel
{
    case Public = 'public';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Online',
            self::Admin => 'Gestionale',
        };
    }
}
