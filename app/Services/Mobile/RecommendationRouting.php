<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Constants\GateRoutes;
use App\Services\Coordination\HouseholdFinancialContext;

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
        // Care costs were taken out (CSJ 2026-10-01); their card is disabled.
        'retirement_state_pension' => ['action' => 'add', 'resource_type' => 'retirement'],
        'retirement_plan_retirement_income' => ['action' => 'edit', 'resource_type' => 'retirement'],
        // Retirement cards are typed by their definition key (CSJ 2026-10-01);
        // the category slugs above remain for a rec without one.
        'retirement_state_pension_no_forecast' => ['action' => 'add', 'resource_type' => 'retirement'],
        'retirement_ni_gaps' => ['action' => 'add', 'resource_type' => 'retirement'],
        'retirement_retirement_income_position' => ['action' => 'edit', 'resource_type' => 'retirement'],

        // Protection — the adapter collapses rules to their category: the
        // three cover-gap families ("add or increase cover" — the app's part
        // is recording the policy once it exists), the profile and employer-
        // benefit set-up rules, the no-policies warning, and the trust flag
        // (an edit to the policy record). Policy reviews stay on the page.
        'protection_setup' => ['action' => 'edit', 'resource_type' => 'protection'],
        'protection_employer_benefits' => ['action' => 'edit', 'resource_type' => 'employer_benefits'],
        'protection_general' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_life_cover_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_critical_illness_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_income_protection_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_protection_policy_in_trust' => ['action' => 'edit', 'resource_type' => 'protection'],
        // Protection cards are the action definitions (CSJ 2026-09-29), typed by
        // their key; the category slugs above remain for a rec without one.
        'protection_protection_profile_missing' => ['action' => 'edit', 'resource_type' => 'protection'],
        // Opens on the employer benefits form (RecordEditForms::CONTEXTUAL_FORMS).
        'protection_no_employer_benefits_recorded' => ['action' => 'edit', 'resource_type' => 'employer_benefits'],
        'protection_no_policies_warning' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_life_insurance_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_dependants_no_life_cover' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_mortgage_no_decreasing_term' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_critical_illness_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_no_ci_with_mortgage' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_income_protection_gap' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_ip_gap_after_state_benefits' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_self_employed_no_ip' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_policy_not_in_trust' => ['action' => 'edit', 'resource_type' => 'protection'],
        'protection_life_cover_position' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_critical_illness_position' => ['action' => 'add', 'resource_type' => 'protection'],
        'protection_income_protection_position' => ['action' => 'add', 'resource_type' => 'protection'],

        // Investment — holdings are inputs Fyn can create. Investment
        // preferences (the risk profile) have no capture tool, so that rule
        // opens the investment page where the profile is set (live check,
        // 2026-09-09: Fyn only acknowledged the answers and wrote nothing).
        'investment_no_holdings' => ['action' => 'add', 'resource_type' => 'investment'],

        // Estate — an LPA is created through Fyn. A will stays on the page (the
        // Will Builder); the life policy trust card is Protection's (item 9 D2).
        'estate_no_lpa' => ['action' => 'add', 'resource_type' => 'estate'],
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
        if (in_array($module, ['retirement', 'estate'], true) && $accountId !== null) {
            // The retirement rules that name a pension (fees, consolidation,
            // salary sacrifice) evaluate workplace Defined Contribution schemes;
            // the estate one names a pension with no beneficiary (item 9 D4).
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

    /**
     * Missing items of a module's gate that Fyn has a form for: the card asks
     * for the item itself, in the same words as a locked strategy
     * (strategyUnlockPrompt), so its tap opens that form (walked 2026-10-05:
     * "Date of birth is required" sent "Help me add my pension details").
     * Keys are the readiness checks' own.
     */
    private const FORM_ITEM_KEYS = ['date_of_birth', 'marital_status', 'income', 'expenditure'];

    public static function unlockPrompt(string $module, ?string $missingKey = null): string
    {
        if (in_array($missingKey, self::FORM_ITEM_KEYS, true)) {
            return self::strategyUnlockPrompt($missingKey);
        }

        return self::UNLOCK_PROMPTS[$module] ?? 'Help me add my financial details';
    }

    /**
     * A locked tax strategy's prompt asks Fyn for the exact missing detail
     * ("pension contributions for the last three tax years"), not a generic
     * noun — the generic tax prompt drew advice instead of capture (walked
     * 2026-09-26).
     */
    public static function strategyUnlockPrompt(string $missingKey): string
    {
        // The spouse's income lives on the spouse details form. "Update"
        // opens it through the advice-side edit door; an "add" phrasing went
        // to the write-intent classifier, whose entity match ("pension" in
        // "including any pension or rent") opened the wrong capture.
        if (in_array($missingKey, ['spouse_income', 'spouse_income_amount'], true)) {
            return "Update my spouse's income";
        }
        // Their savings are on the same form. "Savings" alone could open the
        // user's own savings, so the prompt names the spouse's details.
        if ($missingKey === 'spouse_savings') {
            return "Update my spouse's details";
        }

        return 'Help me add my '.HouseholdFinancialContext::labelFor($missingKey);
    }
}
