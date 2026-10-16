<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BlockScope: string implements HasLabel
{
    case Restaurant = 'restaurant';
    case Table = 'table';

    public function getLabel(): string
    {
        return match ($this) {
            self::Restaurant => 'Intero ristorante',
            self::Table => 'Singolo tavolo',
        };
    }
}
