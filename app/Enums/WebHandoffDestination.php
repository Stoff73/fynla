<?php

declare(strict_types=1);

namespace App\Enums;

enum WebHandoffDestination: string
{
    case ADMIN = 'admin';
    case SUBSCRIPTION = 'subscription';
    case SETTINGS = 'settings';
    case PRIVACY = 'privacy';
    case NOTIFICATIONS = 'notifications';
    case ESTATE_WILL = 'estate_will';
    // W-0469. `/m`'s estate screen is an honest summary, not a second breakdown
    // (CSJ decision, 2026-08-23): rather than render a subset of the allowance
    // table it hands off to the web screen that has all of it.
    case ESTATE_IHT = 'estate_iht';

    public function path(): string
    {
        return match ($this) {
            self::ADMIN => '/admin',
            self::SUBSCRIPTION => '/settings/subscription?openPricing=1',
            self::SETTINGS => '/settings',
            self::PRIVACY => '/settings/privacy',
            self::NOTIFICATIONS => '/settings/notifications',
            self::ESTATE_WILL => '/estate/will-builder',
            self::ESTATE_IHT => '/estate/inheritance-tax',
        };
    }
}
