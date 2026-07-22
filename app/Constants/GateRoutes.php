<?php

declare(strict_types=1);

namespace App\Constants;

use InvalidArgumentException;

/**
 * Canonical destinations for readiness and KYC gates.
 *
 * Route strings are UI metadata. Model-facing copy must consume only the
 * human label returned by resolve().
 */
final class GateRoutes
{
    public const PERSONAL_DETAILS = 'personal_details';

    public const FAMILY_DETAILS = 'family_details';

    public const INCOME = 'income';

    public const EXPENDITURE = 'expenditure';

    public const PROTECTION = 'protection';

    public const SAVINGS = 'savings';

    public const LIABILITIES = 'liabilities';

    public const RETIREMENT = 'retirement';

    public const INVESTMENT = 'investment';

    public const RISK_PROFILE = 'risk_profile';

    public const ESTATE = 'estate';

    public const GOALS = 'goals';

    public const PROPERTY = 'property';

    public const TAX_STRATEGY = 'tax_strategy';

    public const HOLISTIC_PLAN = 'holistic_plan';

    public const DASHBOARD = 'dashboard';

    /** @var array<string, array{label: string, web: string, mobile: ?string}> */
    public const MAP = [
        self::PERSONAL_DETAILS => ['label' => 'Personal Details', 'web' => '/settings/personal', 'mobile' => null],
        self::FAMILY_DETAILS => ['label' => 'Family Details', 'web' => '/settings/family', 'mobile' => null],
        self::INCOME => ['label' => 'Income', 'web' => '/valuable-info?section=income', 'mobile' => '/income'],
        self::EXPENDITURE => ['label' => 'Expenditure', 'web' => '/valuable-info?section=expenditure', 'mobile' => '/expenditure'],
        self::PROTECTION => ['label' => 'Protection', 'web' => '/protection', 'mobile' => '/protection'],
        self::SAVINGS => ['label' => 'Savings', 'web' => '/savings', 'mobile' => '/savings'],
        self::LIABILITIES => ['label' => 'Liabilities', 'web' => '/net-worth/liabilities', 'mobile' => '/net-worth/liabilities'],
        self::RETIREMENT => ['label' => 'Retirement', 'web' => '/retirement', 'mobile' => '/retirement'],
        self::INVESTMENT => ['label' => 'Investments', 'web' => '/investment', 'mobile' => '/investment'],
        self::RISK_PROFILE => ['label' => 'Risk Profile', 'web' => '/risk-profile', 'mobile' => null],
        self::ESTATE => ['label' => 'Estate Planning', 'web' => '/estate', 'mobile' => '/estate'],
        self::GOALS => ['label' => 'Goals', 'web' => '/goals', 'mobile' => '/goals'],
        self::PROPERTY => ['label' => 'Property', 'web' => '/net-worth/property', 'mobile' => '/net-worth/property'],
        self::TAX_STRATEGY => ['label' => 'Tax Strategy', 'web' => '/tax-strategy', 'mobile' => '/tax-strategy'],
        self::HOLISTIC_PLAN => ['label' => 'Holistic Financial Plan', 'web' => '/holistic-plan', 'mobile' => '/holistic-plan'],
        self::DASHBOARD => ['label' => 'Dashboard', 'web' => '/dashboard', 'mobile' => '/dashboard'],
    ];

    /** @var array<string, string> */
    private const LEGACY_ROUTE_DESTINATIONS = [
        '/profile' => self::PERSONAL_DETAILS,
        '/profile/personal' => self::PERSONAL_DETAILS,
        '/settings/personal' => self::PERSONAL_DETAILS,
        '/family' => self::FAMILY_DETAILS,
        '/settings/family' => self::FAMILY_DETAILS,
        '/profile/employment' => self::INCOME,
        '/valuable-info?section=income' => self::INCOME,
        '/profile/expenditure' => self::EXPENDITURE,
        '/valuable-info?section=expenditure' => self::EXPENDITURE,
        '/protection' => self::PROTECTION,
        '/protection/policies' => self::PROTECTION,
        '/protection/employer-benefits' => self::PROTECTION,
        '/profile/liabilities' => self::LIABILITIES,
        '/net-worth/liabilities' => self::LIABILITIES,
        '/savings' => self::SAVINGS,
        '/net-worth/cash' => self::SAVINGS,
        '/retirement' => self::RETIREMENT,
        '/net-worth/retirement' => self::RETIREMENT,
        '/retirement/pensions' => self::RETIREMENT,
        '/retirement/settings' => self::RETIREMENT,
        '/retirement/state-pension' => self::RETIREMENT,
        '/investment' => self::INVESTMENT,
        '/net-worth/investments' => self::INVESTMENT,
        '/investment/accounts' => self::INVESTMENT,
        '/investment/risk-profile' => self::RISK_PROFILE,
        '/risk-profile' => self::RISK_PROFILE,
        '/estate' => self::ESTATE,
        '/estate/assets' => self::ESTATE,
        '/estate/gifts' => self::ESTATE,
        '/estate/planning' => self::ESTATE,
        '/goals' => self::GOALS,
        '/goals/life-events' => self::GOALS,
        '/properties' => self::PROPERTY,
        '/net-worth/property' => self::PROPERTY,
        '/tax-strategy' => self::TAX_STRATEGY,
        '/holistic-plan' => self::HOLISTIC_PLAN,
        '/dashboard' => self::DASHBOARD,
    ];

    /** @return array{label: string, web: string, mobile: ?string} */
    public static function resolve(string $destination): array
    {
        if (! isset(self::MAP[$destination])) {
            throw new InvalidArgumentException("Unknown gate destination: {$destination}");
        }

        return self::MAP[$destination];
    }

    /** @return array<string, array{label: string, web: string, mobile: ?string}> */
    public static function destinations(): array
    {
        return self::MAP;
    }

    public static function destinationForRoute(string $route, ?string $fallback = null): string
    {
        if (isset(self::LEGACY_ROUTE_DESTINATIONS[$route])) {
            return self::LEGACY_ROUTE_DESTINATIONS[$route];
        }

        foreach (self::MAP as $destination => $metadata) {
            if ($route === $metadata['web'] || $route === $metadata['mobile']) {
                return $destination;
            }
        }

        if ($fallback !== null && isset(self::MAP[$fallback])) {
            return $fallback;
        }

        throw new InvalidArgumentException("Unknown gate route: {$route}");
    }
}
