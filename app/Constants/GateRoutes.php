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

    public const NET_WORTH = 'net_worth';

    public const PERSONAL_INFORMATION = 'personal_information';

    public const SUBSCRIPTION = 'subscription';

    public const SETTINGS = 'settings';

    public const ACHIEVEMENTS = 'achievements';

    public const CONVERSATION_HISTORY = 'conversation_history';

    public const ADMIN = 'admin';

    public const DASHBOARD = 'dashboard';

    public const CHATTELS = 'chattels';

    public const BUSINESS = 'business';

    /** @var array<string, array{label: string, web: string, mobile: ?string}> */
    public const MAP = [
        self::PERSONAL_DETAILS => ['label' => 'Personal Details', 'web' => '/settings/personal', 'mobile' => null],
        self::FAMILY_DETAILS => ['label' => 'Family Details', 'web' => '/settings/family', 'mobile' => null],
        self::INCOME => ['label' => 'Income', 'web' => '/valuable-info?section=income', 'mobile' => '/income'],
        self::EXPENDITURE => ['label' => 'Expenditure', 'web' => '/valuable-info?section=expenditure', 'mobile' => '/expenditure'],
        self::PROTECTION => ['label' => 'Protection', 'web' => '/protection', 'mobile' => '/protection'],
        self::SAVINGS => ['label' => 'Bank Accounts', 'web' => '/savings', 'mobile' => '/savings'],
        self::LIABILITIES => ['label' => 'Liabilities', 'web' => '/net-worth/liabilities', 'mobile' => '/net-worth/liabilities'],
        self::RETIREMENT => ['label' => 'Retirement', 'web' => '/retirement', 'mobile' => '/retirement'],
        self::INVESTMENT => ['label' => 'Investments', 'web' => '/investment', 'mobile' => '/investment'],
        self::RISK_PROFILE => ['label' => 'Risk Profile', 'web' => '/risk-profile', 'mobile' => null],
        self::ESTATE => ['label' => 'Estate Planning', 'web' => '/estate', 'mobile' => '/estate'],
        self::GOALS => ['label' => 'Goals', 'web' => '/goals', 'mobile' => '/goals'],
        self::PROPERTY => ['label' => 'Property', 'web' => '/net-worth/property', 'mobile' => '/net-worth/property'],
        self::TAX_STRATEGY => ['label' => 'Tax Strategy', 'web' => '/tax-strategy', 'mobile' => '/tax-strategy'],
        self::HOLISTIC_PLAN => ['label' => 'Holistic Financial Plan', 'web' => '/holistic-plan', 'mobile' => '/holistic-plan'],
        self::NET_WORTH => ['label' => 'Net Worth', 'web' => '/net-worth', 'mobile' => '/net-worth'],
        self::PERSONAL_INFORMATION => ['label' => 'Personal Information', 'web' => '/settings/personal', 'mobile' => '/personal-information'],
        self::SUBSCRIPTION => ['label' => 'Subscription', 'web' => '/settings/subscription', 'mobile' => '/subscription'],
        self::SETTINGS => ['label' => 'Settings', 'web' => '/settings', 'mobile' => '/settings'],
        self::ACHIEVEMENTS => ['label' => 'Achievements', 'web' => '/dashboard', 'mobile' => '/achievements'],
        self::CONVERSATION_HISTORY => ['label' => 'Conversation History', 'web' => '/dashboard', 'mobile' => '/conversation-history'],
        self::ADMIN => ['label' => 'Admin Panel', 'web' => '/admin', 'mobile' => null],
        self::DASHBOARD => ['label' => 'Dashboard', 'web' => '/dashboard', 'mobile' => '/dashboard'],
        // Valuables and business interests have their own pages under net
        // worth; linking to the module root made the user hunt for what they
        // had just added.
        self::CHATTELS => ['label' => 'Valuables', 'web' => '/net-worth/chattels', 'mobile' => '/net-worth/chattels'],
        self::BUSINESS => ['label' => 'Business Interests', 'web' => '/net-worth/business', 'mobile' => '/net-worth/business'],
    ];

    /**
     * Which page shows a record Fyn just wrote, per entity type.
     *
     * SPEC-crud-handler-contract §5.4 and §7.3 (CSJ): the link points at the
     * module page listing the record, with no per-record deep link. It lives
     * here rather than in a new resolver because this class already holds the
     * one web/mobile route table — a second one is the Rule 20 failure.
     *
     * An entity absent from this map gets no link, not a guessed one.
     *
     * @var array<string, string>
     */
    private const ENTITY_DESTINATIONS = [
        'dc_pension' => self::RETIREMENT,
        'db_pension' => self::RETIREMENT,
        'savings_account' => self::SAVINGS,
        'investment_account' => self::INVESTMENT,
        'investment_holding' => self::INVESTMENT,
        'property' => self::PROPERTY,
        'mortgage' => self::LIABILITIES,
        'life_insurance_policy' => self::PROTECTION,
        'critical_illness_policy' => self::PROTECTION,
        'income_protection_policy' => self::PROTECTION,
        'goal' => self::GOALS,
        'life_event' => self::GOALS,
        'estate_asset' => self::ESTATE,
        'estate_liability' => self::LIABILITIES,
        'estate_gift' => self::ESTATE,
        'will' => self::ESTATE,
        'lasting_power_of_attorney' => self::ESTATE,
        'trust' => self::ESTATE,
        'family_member' => self::FAMILY_DETAILS,
        'business_interest' => self::BUSINESS,
        'chattel' => self::CHATTELS,
    ];

    /**
     * The page showing this entity type, or null when it has none.
     *
     * `mobile` is null for destinations `/m` does not implement, in which case
     * that surface shows the confirmation without a link rather than sending
     * the user somewhere that does not exist.
     *
     * @return array{label: string, web: string, mobile: ?string}|null
     */
    public static function forEntityType(string $entityType): ?array
    {
        $destination = self::ENTITY_DESTINATIONS[$entityType] ?? null;

        return $destination === null ? null : self::resolve($destination);
    }

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
        '/net-worth' => self::NET_WORTH,
        '/personal-information' => self::PERSONAL_INFORMATION,
        '/subscription' => self::SUBSCRIPTION,
        '/settings' => self::SETTINGS,
        '/achievements' => self::ACHIEVEMENTS,
        '/admin' => self::ADMIN,
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

    /**
     * Build the platform-neutral navigation intent carried beside legacy paths.
     *
     * Values are identifiers or enum-like route parameters only. Financial
     * values remain server-owned presentation data and never belong here.
     *
     * @param  array<string, int|string>  $params
     * @return array{screen: string, params: array<string, int|string>|object, fallback: string}
     */
    public static function destination(
        string $screen,
        array $params = [],
        ?string $fallback = null,
    ): array {
        self::resolve($screen);
        $resolvedFallback = $fallback ?? self::DASHBOARD;
        self::resolve($resolvedFallback);

        foreach ($params as $key => $value) {
            if (! is_string($key) || (! is_int($value) && ! is_string($value))) {
                throw new InvalidArgumentException('Semantic destination parameters must be named scalar identifiers.');
            }
        }

        return [
            'screen' => $screen,
            // PHP's empty array serializes as `[]`, but semantic parameters are
            // a named JSON map. Preserve that contract even when the map is empty.
            'params' => $params === [] ? (object) [] : $params,
            'fallback' => $resolvedFallback,
        ];
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
