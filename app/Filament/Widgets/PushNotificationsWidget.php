<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Lets the admin enable, disable and test Web Push notifications on the current device.
 * All the work happens in the browser (window.anticoBurgerPush, see filament/pwa-head).
 */
class PushNotificationsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.push-notifications';

    public static function canView(): bool
    {
        return auth()->user()?->can('receive-push-notifications') ?? false;
    }
}
