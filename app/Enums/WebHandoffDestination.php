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
    // W-0279. `/m` printed the risk engine's CONCLUSION — "Attitude to risk:
    // Balanced" — with no route to the nine factors behind it, no way to see which
    // figure produced it, and no way to correct one that is wrong. There is no `/m`
    // risk route to send anyone to, so it hands off to the web screen that holds the
    // breakdown, the same shape as ESTATE_IHT directly above.
    case RISK_PROFILE = 'risk_profile';
    // W-0110. Fyn writes a Lasting Power of Attorney from every surface
    // (`create_power_of_attorney` is in both tool catalogues), and only web can read
    // one back. Rather than leave a write with no read, `/m` hands off to the web
    // screen that holds the instrument — the same shape as ESTATE_IHT above.
    case ESTATE_LPA = 'estate_lpa';

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
            self::RISK_PROFILE => '/risk-profile',
            self::ESTATE_LPA => '/estate/power-of-attorney',
        };
    }
}
