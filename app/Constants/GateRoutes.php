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

    /** The sharing/link panel: web /settings/family, `/m` /spouse-sharing (CSJ 2026-09-16). */
    public const SPOUSE_SHARING = 'spouse_sharing';

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

    // Pages a user can ask Fyn to open (T6), beyond the gate destinations.
    public const ACTIONS = 'actions';

    public const SECURITY = 'security';

    public const PLANNING_ASSUMPTIONS = 'planning_assumptions';

    public const LETTER_TO_SPOUSE = 'letter_to_spouse';

    public const WILL_BUILDER = 'will_builder';

    public const POWER_OF_ATTORNEY = 'power_of_attorney';

    public const TRUSTS = 'trusts';

    public const LIFE_EVENTS = 'life_events';

    public const PLANS = 'plans';

    public const INVESTMENT_PLAN = 'investment_plan';

    public const RETIREMENT_PLAN = 'retirement_plan';

    public const PROTECTION_PLAN = 'protection_plan';

    public const ESTATE_PLAN = 'estate_plan';

    public const PLANNING_JOURNEYS = 'planning_journeys';

    public const WHAT_IF = 'what_if';

    /** @var array<string, array{label: string, web: string, mobile: ?string}> */
    public const MAP = [
        self::PERSONAL_DETAILS => ['label' => 'Personal Details', 'web' => '/settings/personal', 'mobile' => '/personal-information'],
        self::FAMILY_DETAILS => ['label' => 'Family Details', 'web' => '/settings/family', 'mobile' => '/personal-information'],
        self::SPOUSE_SHARING => ['label' => 'Spouse Data Sharing', 'web' => '/settings/family', 'mobile' => '/spouse-sharing'],
        self::INCOME => ['label' => 'Income', 'web' => '/valuable-info?section=income', 'mobile' => '/income'],
        self::EXPENDITURE => ['label' => 'Expenditure', 'web' => '/valuable-info?section=expenditure', 'mobile' => '/expenditure'],
        self::PROTECTION => ['label' => 'Protection', 'web' => '/protection', 'mobile' => '/protection'],
        // The web Bank Accounts page is /net-worth/cash (the menu's link); /savings
        // is the savings dashboard. /m's Bank Accounts screen is /savings.
        self::SAVINGS => ['label' => 'Bank Accounts', 'web' => '/net-worth/cash', 'mobile' => '/savings'],
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
        self::ACTIONS => ['label' => 'Actions', 'web' => '/actions', 'mobile' => '/actions'],
        self::SECURITY => ['label' => 'Security Settings', 'web' => '/settings/security', 'mobile' => null],
        self::PLANNING_ASSUMPTIONS => ['label' => 'Planning Assumptions', 'web' => '/settings/assumptions', 'mobile' => null],
        self::LETTER_TO_SPOUSE => ['label' => 'Letter to Spouse', 'web' => '/valuable-info?section=letter', 'mobile' => null],
        self::WILL_BUILDER => ['label' => 'Will Builder', 'web' => '/estate/will-builder', 'mobile' => null],
        self::POWER_OF_ATTORNEY => ['label' => 'Power of Attorney', 'web' => '/estate/power-of-attorney', 'mobile' => null],
        self::TRUSTS => ['label' => 'Trusts', 'web' => '/trusts', 'mobile' => null],
        self::LIFE_EVENTS => ['label' => 'Life Events', 'web' => '/goals?tab=events', 'mobile' => null],
        self::PLANS => ['label' => 'Plans', 'web' => '/plans', 'mobile' => null],
        self::INVESTMENT_PLAN => ['label' => 'Investment Plan', 'web' => '/plans/investment', 'mobile' => null],
        self::RETIREMENT_PLAN => ['label' => 'Retirement Plan', 'web' => '/plans/retirement', 'mobile' => null],
        self::PROTECTION_PLAN => ['label' => 'Protection Plan', 'web' => '/plans/protection', 'mobile' => null],
        self::ESTATE_PLAN => ['label' => 'Estate Plan', 'web' => '/plans/estate', 'mobile' => null],
        self::PLANNING_JOURNEYS => ['label' => 'Planning Journeys', 'web' => '/planning/journeys', 'mobile' => null],
        self::WHAT_IF => ['label' => 'What-If Scenarios', 'web' => '/planning/what-if', 'mobile' => null],
    ];

    /**
     * The names a user can give a page when asking Fyn to open it (T6). Each
     * name must be the whole rest of the command, so words match whole. The
     * pages are the ones the web chat's old keyword matcher opened, plus Tax
     * Strategy; the routes come from MAP, the one route table.
     *
     * @var array<string, list<string>>
     */
    public const NAVIGATION_NAMES = [
        self::DASHBOARD => ['dashboard', 'home', 'home page'],
        self::PERSONAL_INFORMATION => ['profile', 'details', 'personal details', 'personal information', 'account details'],
        self::SETTINGS => ['settings', 'preferences'],
        self::SECURITY => ['security', 'security settings'],
        self::PLANNING_ASSUMPTIONS => ['assumptions', 'planning assumptions'],
        self::NET_WORTH => ['net worth', 'wealth', 'wealth summary'],
        self::SAVINGS => ['savings', 'cash', 'cash savings', 'bank accounts', 'bank account', 'savings accounts'],
        self::INVESTMENT => ['investments', 'investment', 'portfolio'],
        self::RETIREMENT => ['retirement', 'pensions', 'pension'],
        self::PROPERTY => ['property', 'properties'],
        self::BUSINESS => ['business', 'businesses', 'business interests'],
        self::CHATTELS => ['valuables', 'personal valuables', 'chattels'],
        self::LIABILITIES => ['liabilities', 'debts', 'loans'],
        self::INCOME => ['income'],
        self::EXPENDITURE => ['expenditure', 'spending', 'expenses', 'budget'],
        self::LETTER_TO_SPOUSE => ['letter to spouse', 'letter to my spouse', 'spouse letter'],
        self::PROTECTION => ['protection', 'insurance', 'life insurance'],
        self::ESTATE => ['estate', 'estate planning', 'inheritance tax'],
        self::WILL_BUILDER => ['will', 'will builder'],
        self::POWER_OF_ATTORNEY => ['power of attorney', 'lasting power of attorney'],
        self::TRUSTS => ['trusts', 'trust'],
        self::GOALS => ['goals'],
        self::LIFE_EVENTS => ['life events'],
        self::RISK_PROFILE => ['risk profile', 'attitude to risk'],
        self::PLANS => ['plans'],
        self::INVESTMENT_PLAN => ['investment plan'],
        self::RETIREMENT_PLAN => ['retirement plan', 'pension plan'],
        self::PROTECTION_PLAN => ['protection plan'],
        self::ESTATE_PLAN => ['estate plan'],
        self::HOLISTIC_PLAN => ['holistic plan', 'financial plan', 'holistic financial plan'],
        self::ACTIONS => ['actions', 'recommendations'],
        self::PLANNING_JOURNEYS => ['journeys', 'planning journeys'],
        self::WHAT_IF => ['what if', 'what-if', 'what if scenarios', 'what-if scenarios', 'scenarios'],
        self::TAX_STRATEGY => ['tax strategy', 'tax plan'],
        self::SUBSCRIPTION => ['subscription'],
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
     * Which page shows a grouped capture, per field group.
     *
     * The entity map above only answers for writes that produce a record with
     * an id. A capture_* handler writes columns on `users` and its satellites,
     * so it has no entity to name — and so, until now, its confirmation offered
     * the user no way to see what had just been recorded, even though every one
     * of these pages already existed.
     *
     * Lives here beside ENTITY_DESTINATIONS deliberately: one route table, per
     * the note above.
     *
     * @var array<string, string>
     */
    private const FIELD_GROUP_DESTINATIONS = [
        'personal' => self::PERSONAL_DETAILS,
        'spouse' => self::FAMILY_DETAILS,
        'dependants' => self::FAMILY_DETAILS,
        'work' => self::INCOME,
        'expenditure' => self::EXPENDITURE,
        'employer_benefits' => self::PROTECTION,
        'campaign_charitable_giving' => self::EXPENDITURE,
        'campaign_pension_history' => self::RETIREMENT,
        'campaign_retirement_goals' => self::RETIREMENT,
        'campaign_state_pension' => self::RETIREMENT,
        'campaign_spouse_household' => self::TAX_STRATEGY,
        'campaign_spouse_non_working_assets' => self::TAX_STRATEGY,
    ];

    /**
     * The page showing a grouped capture, or null when the group has none.
     *
     * @return array{label: string, web: string, mobile: ?string}|null
     */
    public static function forFieldGroup(string $fieldGroup): ?array
    {
        $destination = self::FIELD_GROUP_DESTINATIONS[$fieldGroup] ?? null;

        return $destination === null ? null : self::resolve($destination);
    }

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

    /**
     * The semantic destination for a path both clients share, or null when the
     * path names no gate destination, so each client opens its own screen for it
     * (an onboarding check step sent web users to /savings, which lists accounts
     * only for demos; regression walk 2026-10-09, R1).
     *
     * @return array{screen: string, params: object, fallback: string}|null
     */
    public static function destinationForPath(string $route): ?array
    {
        try {
            return self::destination(self::destinationForRoute($route));
        } catch (InvalidArgumentException) {
            return null;
        }
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
