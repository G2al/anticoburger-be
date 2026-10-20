<?php

namespace App\Filament;

/**
 * Sidebar groups of the admin panel; ALL defines their display order.
 */
final class NavigationGroup
{
    public const RESERVATIONS = 'Prenotazioni';

    public const RESTAURANT = 'Sala';

    public const MENU = 'Menu';

    public const ALL = [self::RESERVATIONS, self::RESTAURANT, self::MENU];
}
