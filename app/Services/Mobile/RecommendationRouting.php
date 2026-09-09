<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Constants\GateRoutes;

/**
 * Where a dashboard recommendation goes when tapped (CSJ 2026-09-09).
 *
 * A recommendation that asks the user to RECORD or UPDATE information opens
 * Fyn in a contextual capture conversation, which collects the details,
 * writes them through the usual capture checks, asks "anything else?", and
 * ticks the recommendation off when the user is done. Everything else — using
 * an allowance, moving money, reviewing figures or fees — deep-links to the
 * module page where the user can act on or read the full picture.
 *
 * The one home for that decision. Keys are the dashboard recommendation ids
 * (`<module>_<type>` from the composed plans, or the seeded definition key for
 * an engine that still emits raw recommendations). Absent id = page.
 *
 * ponytail: a PHP map rather than a seeded column; move it onto the
 * action-definition rows if product wants to edit it without a deploy.
 */
final class RecommendationRouting
{
    /**
     * Recommendation id => the contextual conversation that captures it.
     *
     * @var array<string, array{action: 'add'|'edit', resource_type: string}>
     */
    private const FYN = [
        // Savings — the "we cannot advise until you tell us" family.
        'savings_missing_date_of_birth' => ['action' => 'edit', 'resource_type' => 'personal_information'],
        'savings_missing_income' => ['action' => 'add', 'resource_type' => 'income'],
        'savings_missing_expenditure' => ['action' => 'add', 'resource_type' => 'expenditure'],
        'savings_missing_employment_status' => ['action' => 'edit', 'resource_type' => 'personal_information'],
        'savings_emergency_fund_no_data' => ['action' => 'add', 'resource_type' => 'expenditure'],
        'savings_emergency_fund_no_designated' => ['action' => 'edit', 'resource_type' => 'savings'],
        'savings_goal_no_linked_account' => ['action' => 'edit', 'resource_type' => 'goals'],
        'savings_child_no_savings' => ['action' => 'add', 'resource_type' => 'savings'],
        'savings_create_emergency_fund_goal' => ['action' => 'add', 'resource_type' => 'goals'],

        // Retirement — a forecast, a cost or an age the user supplies. The
        // adapter derives the type from the rule's category ("State Pension",
        // "Care Costs", "Retirement Planning"), so these are category slugs.
        // Care costs have no capture tool, so that rule stays on the page.
        'retirement_state_pension' => ['action' => 'add', 'resource_type' => 'retirement'],
        'retirement_plan_retirement_income' => ['action' => 'edit', 'resource_type' => 'retirement'],

        // Protection — the adapter collapses rules to their category: the
        // three cover-gap families ("add or increase cover" — the app's part
        // is recording the policy once it exists), the profile and employer-
        // benefit set-up rules, the no-policies warning, and the trust flag
        // (an edit to the policy record). Policy reviews stay on the page.
        'protection_setup' => ['action' => 'edit', 'resource_type' => 'protection'],
        'protection_employer_benefits' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_general' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_life_cover_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_critical_illness_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_income_protection_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_policy_in_trust' => ['action' => 'edit', 'resource_type' => 'protection'],

        // Investment — holdings are inputs Fyn can create. Investment
        // preferences (the risk profile) have no capture tool, so that rule
        // opens the investment page where the profile is set (live check,
        // 2026-09-09: Fyn only acknowledged the answers and wrote nothing).
        'investment_no_holdings' => ['action' => 'add', 'resource_type' => 'investment'],

        // Estate — an LPA is created through Fyn; a trust flag is an edit to
        // the policy record. A will stays on the page (the Will Builder).
        'estate_no_lpa' => ['action' => 'add', 'resource_type' => 'estate'],
        'estate_no_lpa_health' => ['action' => 'add', 'resource_type' => 'estate'],
        'estate_policy_not_in_trust' => ['action' => 'edit', 'resource_type' => 'protection'],
    ];

    /**
     * Tax strategies open the page of the product they concern, not the tax
     * strategy summary (CSJ 2026-09-09): a cash ISA lives with bank accounts,
     * a Stocks & Shares move with investments, pension contributions with
     * retirement. Gift Aid is reclaimed through Self Assessment and has no
     * product page, so it stays on the tax strategy screen.
     *
     * @var array<string, string> strategy type => GateRoutes screen
     */
    private const TAX_PAGES = [
        'bed_and_isa' => GateRoutes::INVESTMENT,
        'dividend_allowance_harvest' => GateRoutes::INVESTMENT,
        'gift_aid_higher_rate_relief' => GateRoutes::TAX_STRATEGY,
        'isa_topup_vs_psa' => GateRoutes::SAVINGS,
        'joint_savings_psa_split' => GateRoutes::SAVINGS,
        'junior_isa' => GateRoutes::SAVINGS,
        'lifetime_isa' => GateRoutes::SAVINGS,
        'junior_pension' => GateRoutes::RETIREMENT,
        'non_earner_spouse_pension' => GateRoutes::RETIREMENT,
        'pa_taper_rescue' => GateRoutes::RETIREMENT,
        'additional_rate_avoidance' => GateRoutes::RETIREMENT,
        'pension_aa_carry_forward' => GateRoutes::RETIREMENT,
        'salary_sacrifice_ni' => GateRoutes::RETIREMENT,
        'tapered_annual_allowance' => GateRoutes::RETIREMENT,
    ];

    /** @var array<string, string> module => overview screen */
    private const MODULE_PAGES = [
        'protection' => GateRoutes::PROTECTION,
        'savings' => GateRoutes::SAVINGS,
        'investment' => GateRoutes::INVESTMENT,
        'retirement' => GateRoutes::RETIREMENT,
        'estate' => GateRoutes::ESTATE,
        'goals' => GateRoutes::GOALS,
        'tax' => GateRoutes::TAX_STRATEGY,
    ];

    /**
     * The page a page-routed recommendation opens: the exact record when the
     * rule named one (a rate on THIS account, fees on THIS pension, THIS
     * goal), the product page for a tax strategy, else the module overview.
     *
     * @param  array<string, mixed>  $rec  the aggregator's rec (account_id / goal_id)
     * @return array{payload: string, destination: array{screen: string, params: array<string, int|string>|object, fallback: string}}
     */
    public static function pageFor(string $recommendationId, string $module, array $rec): array
    {
        $overview = self::MODULE_PAGES[$module] ?? GateRoutes::NET_WORTH;

        if ($module === 'tax') {
            $overview = self::TAX_PAGES[substr($recommendationId, 4)] ?? GateRoutes::TAX_STRATEGY;
        }

        $accountId = is_numeric($rec['account_id'] ?? null) ? (int) $rec['account_id'] : null;
        $goalId = is_numeric($rec['goal_id'] ?? null) ? (int) $rec['goal_id'] : null;

        // Detail screens are not gate destinations (GateRoutes::MAP), so their
        // intents are built literally; both clients resolve them by name.
        if ($module === 'savings' && $accountId !== null) {
            return self::detail('savings_account_detail', ['account_id' => $accountId], $overview, "/savings/account/{$accountId}");
        }
        if ($module === 'investment' && $accountId !== null) {
            return self::detail('investment_account_detail', ['account_id' => $accountId], $overview, "/investment/account/{$accountId}");
        }
        if ($module === 'retirement' && $accountId !== null) {
            // The retirement rules that name a pension (fees, consolidation,
            // salary sacrifice) evaluate workplace Defined Contribution schemes.
            return self::detail('pension_detail', ['pension_id' => $accountId, 'pension_type' => 'dc'], $overview, "/retirement/pension/dc/{$accountId}");
        }
        if ($goalId !== null) {
            return self::detail('goal_detail', ['goal_id' => $goalId], GateRoutes::GOALS, "/goals/{$goalId}");
        }

        $route = GateRoutes::resolve($overview);

        return [
            'payload' => (string) ($route['mobile'] ?? $route['web']),
            'destination' => GateRoutes::destination($overview),
        ];
    }

    /**
     * @param  array<string, int|string>  $params
     * @return array{payload: string, destination: array{screen: string, params: array<string, int|string>, fallback: string}}
     */
    private static function detail(string $screen, array $params, string $fallback, string $mobilePath): array
    {
        return [
            'payload' => $mobilePath,
            'destination' => ['screen' => $screen, 'params' => $params, 'fallback' => $fallback],
        ];
    }

    /**
     * Capture prompt an unlock card sends when tapped — one text per module,
     * served to every client (the clients used to carry their own copies).
     */
    private const UNLOCK_PROMPTS = [
        'protection' => 'Help me add my protection cover details',
        'savings' => 'Help me add my savings details',
        'investment' => 'Help me add my investment details',
        'retirement' => 'Help me add my pension details',
        'estate' => 'Help me add my estate planning details',
        'goals' => 'Help me set a financial goal',
        'tax' => 'Help me complete my tax strategy details',
    ];

    /**
     * The contextual conversation a Fyn-routed recommendation opens, or null
     * when the recommendation is actioned on its module page.
     *
     * @return array{action: string, resource_type: string}|null
     */
    public static function contextualFor(string $recommendationId): ?array
    {
        return self::FYN[$recommendationId] ?? null;
    }

    public static function unlockPrompt(string $module): string
    {
        return self::UNLOCK_PROMPTS[$module] ?? 'Help me add my financial details';
    }
}
