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

    public function path(): string
    {
        return match ($this) {
            self::ADMIN => '/admin',
            self::SUBSCRIPTION => '/settings/subscription?openPricing=1',
            self::SETTINGS => '/settings',
            self::PRIVACY => '/settings/privacy',
            self::NOTIFICATIONS => '/settings/notifications',
            self::ESTATE_WILL => '/estate/will-builder',
        };
    }
}
