<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Onboarding\SpouseJointRecords;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\PensionAffordability;
use App\Services\Tax\TaxStrategyMath;

/**
 * Household-aware data availability + marginal rates for the strategy
 * catalogue. availability() keys are the canonical required_data vocabulary
 * seeded on tax_action_definitions — a strategy whose required_data are not
 * all true is "locked": surfaced as an unlock prompt, never silently skipped.
 *
 * The 14 vocabulary keys are fixed to match the seeds:
 *   annual_income, charitable_giving, date_of_birth, dividend_income,
 *   employment_status, gia_holdings, isa_subscriptions_ytd, marital_status,
 *   pension_contributions, pension_input_history, savings_balances,
 *   spouse_income, spouse_income_amount, spouse_savings, workplace_pension
 *
 * spouse_income is "we know how the spouse stands" (a non-working spouse
 * counts). spouse_income_amount is the figure itself — a linked spouse's
 * records or an amount captured — which Marriage Allowance needs because a
 * non-working spouse can still have a pension or rent (CSJ 2026-09-28).
 * spouse_savings is the spouse's own savings interest, which decides what
 * moving savings to them saves (CSJ 2026-10-01).
 */
final class HouseholdFinancialContext
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly SavingsStore $savingsStore,
    ) {}

    /**
     * Returns a map of every catalogue data-point key to a boolean indicating
     * whether that data is available for the given user.
     *
     * @return array<string, bool|null>
     */
    public function availability(User $user): array
    {
        $hasDcPension = $this->hasDcPension($user);

        // A declaration of none answers the question as well as a record
        // does (Laura, 2026-09-18: "I have none of those" and the strategies
        // stayed locked). Fyn records them in onboarding_fyn_context —
        // declared_none holds capture form names, declared_none_keys holds
        // these vocabulary keys directly.
        $declared = self::declaredKeys($user);
        $availability = [
            'annual_income' => $this->hasAnnualIncome($user),
            'charitable_giving' => $user->annual_charitable_donations !== null,
            'date_of_birth' => $user->date_of_birth !== null,
            'dividend_income' => ((float) ($user->annual_dividend_income ?? 0)) > 0,
            'employment_status' => filled($user->employment_status),
            // Pension suggestions are capped by the money left after spending,
            // so spending must be known (CSJ 2026-09-30: "We ask for
            // expenditure"); someone with no income at all is funded from cash.
            'expenditure' => app(PensionAffordability::class)->spendingRecorded($user)
                || app(PensionAffordability::class)->fundedFromCash($user),
            'gia_holdings' => $this->hasGiaHoldings($user),
            'isa_subscriptions_ytd' => $this->hasIsaAccount($user),
            'marital_status' => filled($user->marital_status),
            'pension_contributions' => $hasDcPension,
            'pension_input_history' => collect(app(PensionStore::class)->pensionInputHistory($user))->isNotEmpty(),
            'savings_balances' => $this->hasSavingsBalance($user),
            'spouse_income' => $this->spouseIncomeKnown($user),
            'spouse_income_amount' => $this->spouseIncomeAmountKnown($user),
            'spouse_savings' => $this->spouseSavingsKnown($user),
            'workplace_pension' => $hasDcPension,
        ];
        foreach ($declared as $key) {
            if (array_key_exists($key, $availability)) {
                $availability[$key] = true;
            }
        }
        // Someone with no spouse or civil partner has no spouse data to give:
        // the spouse strategies do not apply to them at all (null), so none of
        // them waits on anything (a single user was being asked for a spouse's
        // income, then for ISA details for a spouse ISA).
        // Past pension payments only matter for carry forward.
        if (! $this->math->carryForwardCouldApply($user)) {
            $availability['pension_input_history'] = null;
        }
        if (! $this->math->isMarriedOrCivilPartner($user)) {
            $availability['spouse_income'] = null;
            $availability['spouse_income_amount'] = null;
            $availability['spouse_savings'] = null;
        }

        return $availability;
    }

    /**
     * The vocabulary keys the user has declared they have nothing for.
     *
     * @return list<string>
     */
    public static function declaredKeys(User $user): array
    {
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $keys = array_values(array_filter((array) ($context['declared_none_keys'] ?? []), 'is_string'));
        foreach ((array) ($context['declared_none'] ?? []) as $form) {
            $keys = array_merge($keys, match ($form) {
                'investment' => ['gia_holdings'],
                'savings', 'isa' => ['savings_balances', 'isa_subscriptions_ytd'],
                'pension', 'pension_personal' => ['pension_contributions', 'workplace_pension', 'pension_input_history'],
                'expenditure_tax' => ['charitable_giving'],
                default => [],
            });
        }

        return array_values(array_unique($keys));
    }

    /**
     * What of onboarding_fyn_context survives the end of onboarding: the
     * remembered joint records (the invitee usually registers after the plan)
     * and the "none" declarations, which availability() reads for the rest of
     * the user's life. Everything else is walk scratch.
     *
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    public static function outlivingOnboarding(?array $context): ?array
    {
        $kept = array_merge(
            SpouseJointRecords::carry($context) ?? [],
            array_intersect_key($context ?? [], array_flip(['declared_none', 'declared_none_keys'])),
        );

        return $kept === [] ? null : $kept;
    }

    /**
     * Human label for a required_data vocabulary key, for user-facing unlock
     * prompts ("tell me about your …"). Raw keys leak abbreviations the copy
     * rules ban — live-browser finding 2026-06-11: "gia holdings".
     */
    public static function labelFor(string $key): string
    {
        return match ($key) {
            'gia_holdings' => 'General Investment Account holdings',
            'isa_subscriptions_ytd' => 'ISA payments this tax year',
            'pension_input_history' => 'pension contributions for the last three tax years',
            'workplace_pension' => 'workplace pension',
            'pension_contributions' => 'pension contributions',
            'annual_income' => 'annual income',
            'dividend_income' => 'dividend income',
            'savings_balances' => 'savings accounts',
            'charitable_giving' => 'charitable giving',
            'spouse_income' => "spouse's income",
            'spouse_income_amount' => "spouse's total income a year, including any pension or rent (enter 0 if none)",
            'spouse_savings' => "spouse's savings and the interest they receive each year",
            'marital_status' => 'marital status',
            'employment_status' => 'employment status',
            'expenditure' => 'monthly spending',
            'date_of_birth' => 'date of birth',
            default => str_replace('_', ' ', $key),
        };
    }

    /**
     * Marginal income-tax rate for the user, derived from their full taxable
     * income (employment + dividends + estimated savings interest).
     */
    public function marginalRateFor(User $user): float
    {
        return $this->math->bandRateFor($user);
    }

    /**
     * Marginal income-tax rate for the user's spouse.
     *
     * Returns 0.0 when the household mode is single_earner_couple (the spouse
     * is a known non-earner and their income is £0 by definition).
     *
     * Returns a float derived from spouse_annual_income for dual_earner mode
     * when that field is populated on the household input row.
     *
     * Returns null when the spouse's income is genuinely unknown (single mode,
     * no household input row, or dual_earner with no income recorded yet).
     */
    public function spouseMarginalRate(User $user): ?float
    {
        $mode = $user->household_calculation_mode;

        if ($mode === 'single_earner_couple') {
            return 0.0;
        }

        if ($mode === 'dual_earner') {
            $input = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
            if ($input !== null && $input->spouse_annual_income !== null) {
                return $this->math->bandRateFromIncome((float) $input->spouse_annual_income);
            }
        }

        return null;
    }

    /**
     * What the linked spouse's own records say they earn, when this account
     * may read them — the same gate (User::financiallySharedSpouse) and the
     * same figures (IncomeDefinitionsService) GET /api/user/profile shows as
     * the spouse's income. Null when there is no financially-shared linked
     * spouse, or their records hold no earnings from work: then nothing is
     * known and the question is still asked (an invitee's partner answered
     * it when they entered their own job — production 2026-09-29).
     *
     * `earnings` is employment plus self-employment income; `total_income`
     * is every source, the figure the spouse household form calls "Their
     * annual income".
     *
     * @return array{earnings: float, total_income: float}|null
     */
    public function linkedSpouseEarnings(User $user): ?array
    {
        $spouse = $user->financiallySharedSpouse();
        if ($spouse === null) {
            return null;
        }

        $definition = app(IncomeDefinitionsService::class)->calculate($spouse->id);
        $components = $definition['components'];
        $earnings = (float) ($components['employment'] ?? 0) + (float) ($components['self_employment'] ?? 0);
        if ($earnings <= 0) {
            return null;
        }

        return [
            'earnings' => round($earnings, 2),
            'total_income' => round((float) $definition['total_income'], 2),
        ];
    }

    /**
     * The partner whose holdings are on their own account and readable here:
     * a linked spouse who shares financial data, the account
     * TaxStrategyCalculator builds their allowances from. Null otherwise, and
     * their holdings are then only what the user tells us.
     */
    public function partnerWithOwnRecords(User $user): ?User
    {
        return $user->financiallySharedSpouse();
    }

    // ---------- Private helpers ----------

    private function hasAnnualIncome(User $user): bool
    {
        return ((float) ($user->annual_employment_income ?? 0)) > 0
            || ((float) ($user->annual_self_employment_income ?? 0)) > 0;
    }

    /**
     * Non-ISA investment accounts (GIA, bonds, VCT, EIS, etc.) — mirrors the
     * query used by BedAndIsaStrategy and DividendAllowanceHarvestStrategy.
     */
    private function hasGiaHoldings(User $user): bool
    {
        return app(InvestmentAccountStore::class)->forUser($user)
            ->filter(fn ($a) => (int) $a->user_id === (int) $user->id
                && ($a->account_type === null || $a->account_type !== 'isa'))
            ->isNotEmpty();
    }

    /**
     * Any user-owned ISA, cash or stocks and shares — the subscription amount lives per
     * account; an ISA existing means the question is answerable.
     * Uses forUser() (joint-aware) then filters to user_id owned accounts,
     * mirroring IsaTopUpStrategy's pattern.
     */
    public function hasIsaAccount(User $user): bool
    {
        return $this->savingsStore->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', true)
            ->isNotEmpty()
            // A Stocks and Shares ISA answers it too: TaxStrategyMath counts
            // its isa_subscription_current_year towards the allowance used.
            || app(InvestmentAccountStore::class)->forUser($user)
                ->filter(fn ($a) => (int) $a->user_id === (int) $user->id && $a->account_type === 'isa')
                ->isNotEmpty();
    }

    /**
     * Any user-owned savings account (ISA or non-ISA counts — the balance
     * data itself is available). Same joint-aware forUser() source as
     * IsaTopUpStrategy uses for non-ISA balance.
     */
    private function hasSavingsBalance(User $user): bool
    {
        return $this->savingsStore->forUser($user)
            ->where('user_id', $user->id)
            ->isNotEmpty();
    }

    /**
     * DC pension records exist for this user — covers both workplace_pension
     * and pension_contributions keys (both need a DC pension on record).
     */
    private function hasDcPension(User $user): bool
    {
        return app(PensionStore::class)->forUserByType($user, 'dc')->isNotEmpty();
    }

    /**
     * Spouse income is known when:
     * - single_earner_couple mode: spouse income is definitionally £0
     * - its amount is known (spouseIncomeAmountKnown): a linked spouse's own
     *   records hold income, or a figure was given. A linked account's income
     *   was not read here, so the spouse strategies waited for a figure the
     *   account already held (ice-cube, 2026-09-30).
     */
    private function spouseIncomeKnown(User $user): bool
    {
        return $user->household_calculation_mode === 'single_earner_couple'
            || $this->spouseIncomeAmountKnown($user);
    }

    /**
     * Known from the linked spouse's own records only when they hold income
     * (TaxStrategyMath::linkedSpouseWithIncome, the one rule), else from the
     * figure given.
     */
    /**
     * The spouse's own savings interest is known (their records, an interest
     * figure, or savings given as none), or it cannot matter: when the user
     * pays no tax on the interest they could move, no answer would change the
     * plan, so nobody is asked (null).
     */
    private function spouseSavingsKnown(User $user): ?bool
    {
        $household = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
        $position = $this->math->partnerTaxPosition($user, (string) ($user->household_calculation_mode ?? ''), $household);
        if ($position !== null && $position['savings_known']) {
            return true;
        }
        $movable = $this->math->soleNonIsaSavings($user)['interest'];

        return floor($this->math->interestRemovalSaving($user, $movable)) >= 1 ? false : null;
    }

    private function spouseIncomeAmountKnown(User $user): bool
    {
        return $this->math->linkedSpouseWithIncome($user) !== null
            || TaxStrategyHouseholdInput::where('user_id', $user->id)->whereNotNull('spouse_annual_income')->exists();
    }
}
