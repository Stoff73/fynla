<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\Investment\InvestmentAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Retirement\PensionContributionRule;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;

/**
 * Stateless math/lookup helpers shared across every TaxStrategy class.
 *
 * Every public method is deterministic given (User, ?Overrides, TaxConfig).
 * Methods that hit the database (estimateAnnualInterest,
 * estimateIsaSubscriptionsThisYear, estimatePensionContributionThisYear)
 * issue a single query each — keep an eye on N+1 if a strategy class calls
 * them inside a loop.
 */
final class TaxStrategyMath
{
    use CalculatesOwnershipShare;

    /**
     * Per-instance memo keyed by user id for taxableIncomeFor(), which fires
     * a SavingsAccount query via estimateAnnualInterest. Strategies that call
     * the helper repeatedly (or via composed paths after M11) would otherwise
     * issue one query each — benchmarked to flake the 50ms calculator budget.
     *
     * @var array<int, float>
     */
    private array $taxableIncomeCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $incomeDefinitionsCache = [];

    /** @var array<int, bool> */
    private array $mpaaAppliesCache = [];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly IncomeDefinitionsService $incomeDefinitions,
    ) {}

    /**
     * Personal Savings Allowance amount for a given band, sourced from
     * TaxConfigService['income_tax']['personal_savings_allowance'].
     */
    public function psaForBand(string $band): float
    {
        $psa = $this->taxConfig->getIncomeTax()['personal_savings_allowance'] ?? [];

        return (float) ($psa[$band] ?? 0);
    }

    /**
     * Tax-band thresholds sourced from TaxConfigService — basic/higher/additional
     * boundaries. Returns the lower bounds: basic = 0, higher band lower_limit,
     * additional band lower_limit.
     *
     * @return array{higher: float, additional: float}
     */
    public function bandThresholds(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $higher = 0.0;
        $additional = 0.0;
        foreach ($bands as $band) {
            $name = strtolower((string) ($band['name'] ?? ''));
            if (str_contains($name, 'higher')) {
                $higher = (float) ($band['lower_limit'] ?? 0);
            }
            if (str_contains($name, 'additional')) {
                $additional = (float) ($band['lower_limit'] ?? 0);
            }
        }

        return ['higher' => $higher, 'additional' => $additional];
    }

    /**
     * The band limits as they apply to THIS user: extended by the grossed-up Gift
     * Aid, the same extension `UKTaxCalculator` applies (ITA 2007 s414); the
     * Personal Allowance taper is not modelled here because it only bites above
     * £100,000 where both limits are already exceeded. Without this the strategy
     * engine valued a slice at 45% that the calculator taxed at 40% (2026-09-17).
     *
     * @return array{higher: float, additional: float}
     */
    public function bandThresholdsFor(User $user): array
    {
        // Gift Aid (ITA 2007 s414) and relief-at-source pension contributions
        // (FA 2004 s192(4)) both raise the basic and higher rate limits.
        $deductions = $this->incomeDefinitionsFor($user)['deductions'] ?? [];
        $extension = (float) ($deductions['gift_aid_gross'] ?? 0) + (float) ($deductions['relief_at_source_gross'] ?? 0);
        $raw = $this->bandThresholds();

        return [
            'higher' => $raw['higher'] > 0 ? $raw['higher'] + $extension : 0.0,
            'additional' => $raw['additional'] > 0 ? $raw['additional'] + $extension : 0.0,
        ];
    }

    /**
     * Raw (non-Gift-Aid-aware) band lookup for an arbitrary income figure.
     * Stays raw deliberately for `QuerySchemas` and for `CoordinatingAgent`'s two
     * spouse-income calls — none of those callers have a `User` model in hand to
     * look up Gift Aid for, so `bandFromIncomeFor()` is not available to them.
     * Every caller that DOES hold a `User` should use `bandFromIncomeFor()` instead.
     */
    public function bandFromIncome(float $income): string
    {
        $thresholds = $this->bandThresholds();

        return match (true) {
            $income >= $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income >= $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }

    public function bandFromIncomeFor(User $user, float $income): string
    {
        $thresholds = $this->bandThresholdsFor($user);

        return match (true) {
            $income >= $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income >= $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }

    /**
     * Marginal income-tax rate for the user, derived from their HMRC band on
     * TOTAL taxable income (employment + dividends + savings interest), not
     * employment alone. This is the right basis for the marginal rate on
     * savings interest, AA charges, and pension tax relief — all of which
     * stack on top of the user's other income at HMRC.
     */
    public function bandRateFor(User $user): float
    {
        return $this->bandRateForBand($this->bandFromIncomeFor($user, $this->taxableIncomeFor($user)));
    }

    /**
     * Marginal income-tax rate for an arbitrary gross income figure, without
     * needing a User model. Used by HouseholdFinancialContext to price a
     * dual-earner spouse's rate from the household input's spouse_annual_income.
     */
    public function bandRateFromIncome(float $income): float
    {
        return $this->bandRateForBand($this->bandFromIncome($income));
    }

    /**
     * Marginal income-tax rate for a given band ('basic' / 'higher' /
     * 'additional'), sourced from TaxConfigService['income_tax']['bands'].
     * Falls back to HMRC 2025/26 defaults only if the band can't be matched
     * (defensive — config seeder always populates all three bands).
     */
    public function bandRateForBand(string $band): float
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $needle = strtolower($band);

        foreach ($bands as $row) {
            $name = strtolower((string) ($row['name'] ?? ''));
            if (str_contains($name, $needle)) {
                return (float) ($row['rate'] ?? 0);
            }
        }

        return match ($needle) {
            'basic' => 0.20,
            'higher' => 0.40,
            'additional' => 0.45,
            default => 0.20,
        };
    }

    public function personalSavingsAllowanceFor(float $income): float
    {
        return $this->psaForBand($this->bandFromIncome($income));
    }

    /**
     * Personal Savings Allowance for THIS user, banded on their Gift-Aid-extended
     * thresholds via `bandFromIncomeFor()`. Use this over `personalSavingsAllowanceFor()`
     * whenever a `User` is in hand; the float-only variant stays for the spouse grids,
     * which price off a household income figure rather than a `User` model.
     */
    public function personalSavingsAllowanceForUser(User $user): float
    {
        return $this->psaForBand($this->bandFromIncomeFor($user, $this->taxableIncomeFor($user)));
    }

    /**
     * Net income (ITA 2007 s23 Step 2): every captured source, less net-pay
     * pension contributions taken from pay (FA 2004 s193(2)). This is the
     * income the tax bands are applied to; relief-at-source contributions do
     * not reduce it and instead extend the bands (bandThresholdsFor). When the
     * user has no explicit annual-interest figure, use the interest implied by
     * captured savings balances and rates rather than silently treating it as
     * zero.
     */
    public function taxableIncomeFor(User $user): float
    {
        $key = (int) $user->id;
        if (! isset($this->taxableIncomeCache[$key])) {
            $definitions = $this->incomeDefinitionsFor($user);
            $this->taxableIncomeCache[$key] = max(
                0.0,
                (float) ($definitions['net_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
            );
        }

        return $this->taxableIncomeCache[$key];
    }

    public function adjustedNetIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);

        return max(
            0.0,
            (float) ($definitions['adjusted_net_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
        );
    }

    public function nonSavingsIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];

        return max(
            0.0,
            $this->taxableIncomeFor($user)
                - $this->resolvedInterest($user, $definitions)
                - (float) ($components['dividend'] ?? 0),
        );
    }

    public function personalAllowanceFor(User $user): float
    {
        return $this->personalAllowanceForIncome($this->adjustedNetIncomeFor($user));
    }

    public function personalAllowanceForIncome(float $adjustedNetIncome): float
    {
        $income = $this->taxConfig->getIncomeTax();
        $full = (float) ($income['personal_allowance'] ?? 12570);
        $threshold = (float) ($income['personal_allowance_taper_threshold'] ?? 100000);

        if ($adjustedNetIncome <= $threshold) {
            return $full;
        }

        return max(0.0, $full - floor(($adjustedNetIncome - $threshold) / 2));
    }

    public function moneyPurchaseAnnualAllowanceApplies(User $user): bool
    {
        $key = (int) $user->id;

        return $this->mpaaAppliesCache[$key] ??= app(PensionStore::class)
            ->forUserByType($user, 'dc')
            ->contains(fn ($pension) => (bool) $pension->has_flexibly_accessed);
    }

    public function effectiveAnnualAllowanceFor(User $user): float
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $allowance = (float) ($pension['annual_allowance'] ?? 60000);
        $taper = $pension['tapered_annual_allowance'] ?? [];
        $thresholdLimit = (float) ($taper['threshold_income'] ?? 200000);
        $adjustedLimit = (float) ($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $minimum = (float) ($taper['minimum_allowance'] ?? 10000);
        $rate = (float) ($taper['taper_rate'] ?? 0.5);
        $thresholdIncome = $this->thresholdIncomeFor($user);
        $adjustedIncome = $this->adjustedIncomeFor($user);
        if ($thresholdIncome > $thresholdLimit && $adjustedIncome > $adjustedLimit) {
            $allowance = max(
                $minimum,
                $allowance - floor(($adjustedIncome - $adjustedLimit) * $rate),
            );
        }

        if ($this->moneyPurchaseAnnualAllowanceApplies($user)) {
            $allowance = min(
                $allowance,
                (float) ($pension['money_purchase_annual_allowance'] ?? $pension['mpaa'] ?? 0),
            );
        }

        return max(0.0, $allowance);
    }

    /**
     * Remaining Pension Annual Allowance for the current tax year, after the
     * user's existing contributions and any in-flight slider override.
     * Floored at 0 and constrained by the tapered Annual Allowance or Money
     * Purchase Annual Allowance where either applies. Carry-forward is handled
     * separately by PensionAACarryForwardStrategy.
     */
    public function availableAnnualAllowance(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        $aa = $this->effectiveAnnualAllowanceFor($user);
        $used = $this->estimatePensionContributionThisYear($user, $overrides);

        return max(0, $aa - $used);
    }

    public function estimateAnnualInterest(User $user): float
    {
        // forUser() is joint-aware (primary or joint owner). HMRC splits
        // joint-account interest by beneficial share (50/50 default between
        // spouses), so each account contributes the user's ownership share —
        // never the full balance. Issue log 2026-07-23 #21; mirrors the rule
        // net worth already applies via CalculatesOwnershipShare.
        return (float) app(SavingsStore::class)->forUser($user)
            ->where('is_isa', false)
            ->sum(fn ($acc) => $this->calculateUserShare($acc, $user->id) * $this->normalisedInterestRate($acc));
    }

    /**
     * The other owner's share of the user's shared non-ISA accounts — the
     * interest HMRC attributes to the spouse. The SaveTax campaign stores the
     * spouse as household input (no User row, joint_owner_id null), so the
     * spouse grids can only derive this from the primary user's records.
     */
    public function estimateSpouseJointInterest(User $user): float
    {
        return (float) app(SavingsStore::class)->forUser($user)
            ->where('is_isa', false)
            ->filter(fn ($acc) => $this->isSharedOwnership($acc))
            ->sum(function ($acc) use ($user) {
                $fullInterest = (float) $acc->current_balance * $this->normalisedInterestRate($acc);

                return $fullInterest - ($this->calculateUserShare($acc, $user->id) * $this->normalisedInterestRate($acc));
            });
    }

    /**
     * interest_rate convention is mixed across the codebase (factory writes
     * decimals 0.04, seeders + onboarding write percent 4.0). Normalise:
     * anything > 1 is treated as percent.
     */
    private function normalisedInterestRate(object $acc): float
    {
        $rate = (float) $acc->interest_rate;

        return $rate > 1 ? $rate / 100 : $rate;
    }

    public function estimateIsaSubscriptionsThisYear(User $user): float
    {
        // P0.6 / Task 4 — Prefer explicit per-account subscription amounts captured
        // during onboarding ("how much have you put in this tax year?"). The
        // isa_subscription_year field stores the tax-year label in 'YYYY/YY' format
        // (e.g. '2026/27'), matching TaxConfigService::getTaxYear().
        //
        // If ANY account has a captured amount for the current tax year, sum those
        // amounts and return early — they are direct user input and strictly more
        // accurate than the proxy.
        //
        // Fallback (no captured amounts): P0.6 proxy — sum balances of ISAs OPENED
        // in the current tax year. This is conservative (under-estimates top-ups to
        // older accounts) but better than over-estimating against the £20k cap.
        // The strategy layer caps suggestions at the allowance regardless.
        $currentTaxYear = $this->taxConfig->getTaxYear(); // e.g. '2026/27'

        // forUser() is joint-aware; the Collection-level where('user_id')
        // post-filter preserves the original single-owner sum.
        $allIsas = app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', true);

        // Prefer captured per-account subscription amounts for the current tax year.
        $capturedCash = $allIsas
            ->where('isa_subscription_year', $currentTaxYear)
            ->filter(fn ($a) => $a->isa_subscription_amount !== null)
            ->sum('isa_subscription_amount');

        // Stocks & shares / investment ISAs subscribe against the SAME £20k
        // allowance but live on investment_accounts (account_type 'isa') under
        // isa_subscription_current_year — NOT savings_accounts. Without this the
        // allowance is over-stated for anyone with an S&S ISA, so ISA top-up
        // strategies recommend wrapping more than the user can still subscribe.
        // Mirrors the household allowance accounting in HouseholdPlanningService.
        // (ISAs are never jointly owned, so primary-owner scope is exhaustive.)
        $capturedInvestment = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_current_year');

        $captured = (float) $capturedCash + (float) $capturedInvestment;

        if ($captured > 0) {
            return $captured;
        }

        // Fallback: created-this-tax-year proxy (P0.6 original logic, unchanged).
        $taxYearStart = $this->taxConfig->getEffectiveFrom();

        $accounts = $allIsas;

        if ($taxYearStart !== '') {
            // created_at is a Carbon cast; Collection::where string comparison
            // is unreliable, so filter explicitly against a parsed boundary.
            $boundary = Carbon::parse($taxYearStart);
            $accounts = $accounts->filter(fn ($a) => $a->created_at >= $boundary);
        }

        return (float) $accounts->sum('current_balance');
    }

    public function estimatePensionContributionThisYear(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        if ($overrides?->pensionContributionPercent !== null) {
            return (float) ($user->annual_employment_income ?? 0) * ($overrides->pensionContributionPercent / 100);
        }

        // The pension input amount (FA 2004 s233(1)), published once by the
        // income definitions.
        return (float) $this->incomeDefinitionsFor($user)['pension_input_amount'];
    }

    /**
     * Threshold income for tapered AA — sum of all taxable income fields on
     * the User row, with no pension-contribution deduction. V1 simplification:
     * does not handle salary-sacrifice anti-forestalling addback (HMRC rule
     * for sacrifices on/after 9 July 2015). Acceptable today; revisit if a
     * persona-driven false-negative appears.
     */
    public function thresholdIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);

        return max(
            0.0,
            (float) ($definitions['threshold_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
        );
    }

    /**
     * Adjusted income for tapered AA — threshold income plus employer
     * pension contributions added back. Used as the £260k gate for the
     * tapered Annual Allowance.
     */
    public function adjustedIncomeFor(User $user): float
    {
        $definitions = $this->incomeDefinitionsFor($user);

        return max(
            0.0,
            (float) ($definitions['adjusted_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
        );
    }

    /**
     * The user's own pension contributions this year as the gross amount that
     * earns relief: workplace (net pay) contributions as paid, personal pension
     * and SIPP payments grossed up at the basic rate (relief at source).
     * Salary-sacrificed pensions are excluded: that pay never reaches them.
     */
    public function grossEmployeePensionContributions(User $user): float
    {
        $salary = (float) ($user->annual_employment_income ?? 0);
        $basicRelief = (float) ($this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'] ?? 0);

        return (float) app(PensionStore::class)->forUserByType($user, 'dc')
            ->reject(fn ($p) => ! empty($p->salary_sacrifice))
            ->sum(function ($p) use ($salary, $basicRelief) {
                $paid = PensionContributionRule::monthlyEmployee($p, $salary) * 12;

                return PensionContributionRule::isWorkplace($p) || $basicRelief >= 1
                    ? $paid
                    : $paid / (1 - $basicRelief);
            });
    }

    /** Tax treats spouses and civil partners alike; unmarried partners get neither transfer. */
    public function isMarriedOrCivilPartner(User $user): bool
    {
        return in_array((string) ($user->marital_status ?? ''), ['married', 'civil_partnership'], true);
    }

    public function marriageAllowanceAmount(): float
    {
        return (float) ($this->taxConfig->getIncomeTax()['marriage_allowance']['amount'] ?? 0);
    }

    /**
     * The Marriage Allowance position for a couple, in either direction, from
     * ITA 2007 Part 3 Chapter 3A
     * (https://www.legislation.gov.uk/ukpga/2007/3/part/3/chapter/3A):
     * - s55C(1)(a): the couple must be married or civil partners.
     * - s55C(2): the transferor's net income must be LESS THAN the Personal
     *   Allowance.
     * - s55B(2)(b),(ba): the recipient may be liable only at the basic,
     *   savings, dividend-ordinary and nil rates, i.e. their total income
     *   stays inside the basic-rate band.
     * - s55B(1),(3): the reduction is the basic rate × the transferable
     *   amount (income_tax.marriage_allowance.amount).
     * - s23 Step 6 and s26: the reduction comes off the tax calculated at
     *   Step 5, so it can never exceed the recipient's tax.
     * - s55B(6): the transferor's Personal Allowance falls by the transferable
     *   amount, so any extra tax they then pay comes off the household saving.
     *
     * The spouse's income must be known: a non-earner (single_earner_couple)
     * or captured income (dual_earner). Returns null when nothing is saved.
     *
     * @return array{saving: float, direction: 'to_user'|'to_spouse'}|null
     */
    public function marriageAllowance(User $user, string $mode, ?TaxStrategyHouseholdInput $household): ?array
    {
        if (! $this->isMarriedOrCivilPartner($user)) {
            return null;
        }

        $spouse = match ($mode) {
            'single_earner_couple' => ['non_savings' => 0.0, 'dividends' => 0.0],
            'dual_earner' => $household?->spouse_annual_income === null ? null : [
                'non_savings' => (float) $household->spouse_annual_income,
                'dividends' => (float) ($household->spouse_annual_dividends ?? 0),
            ],
            default => null,
        };
        if ($spouse === null) {
            return null;
        }
        $spouse['interest'] = $this->estimateSpouseJointInterest($user);
        $spouse['net_pay'] = 0.0;
        $spouse['trust'] = 0.0;

        $user_ = $this->incomePartsFor($user);
        $personalAllowance = (float) ($this->taxConfig->getIncomeTax()['personal_allowance'] ?? 0);
        $amount = $this->marriageAllowanceAmount();
        $maxReduction = $amount * $this->bandRateForBand('basic');
        $userNet = $user_['non_savings'] + $user_['interest'] + $user_['dividends'] + $user_['trust'] - $user_['net_pay'];
        $spouseNet = $spouse['non_savings'] + $spouse['interest'] + $spouse['dividends'];

        $options = [];
        if ($spouseNet < $personalAllowance
            && $this->bandFromIncomeFor($user, $this->taxableIncomeFor($user)) === 'basic') {
            $options['to_user'] = min($maxReduction, $this->incomeTaxOn($user_))
                - $this->extraTaxFromLosingAllowance($spouse, $amount);
        }
        if ($mode === 'dual_earner' && $userNet < $personalAllowance
            && $this->bandFromIncome($spouseNet) === 'basic') {
            $options['to_spouse'] = min($maxReduction, $this->incomeTaxOn($spouse))
                - $this->extraTaxFromLosingAllowance($user_, $amount);
        }

        $options = array_filter($options, fn (float $saving): bool => $saving >= 0.01);
        if ($options === []) {
            return null;
        }
        arsort($options);

        return ['saving' => round((float) reset($options), 2), 'direction' => (string) key($options)];
    }

    /**
     * The transferable amount when the user RECEIVES a Marriage Allowance that
     * saves tax, else 0. The spouse's Personal Allowance is then reduced by it
     * (s55B(6)) and cannot also shelter gifted interest.
     */
    public function marriageAllowanceTransfer(User $user, string $mode, ?TaxStrategyHouseholdInput $household): float
    {
        return ($this->marriageAllowance($user, $mode, $household)['direction'] ?? null) === 'to_user'
            ? $this->marriageAllowanceAmount()
            : 0.0;
    }

    /**
     * The user's income split the way the tax engine stacks it (ITA 2007 s16).
     * The total comes from IncomeDefinitionsService, so salary sacrifice is
     * already resolved. Net-pay contributions are the workplace contributions
     * taken from pay.
     *
     * @return array{non_savings: float, interest: float, dividends: float, trust: float, net_pay: float}
     */
    public function incomePartsFor(User $user): array
    {
        $definitions = $this->incomeDefinitionsFor($user);
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];
        $interest = (float) ($components['interest'] ?? 0);
        $dividends = (float) ($components['dividend'] ?? 0);
        $trust = (float) ($components['trust'] ?? 0);
        $salary = (float) ($user->annual_employment_income ?? 0);

        $netPay = (float) app(PensionStore::class)->forUserByType($user, 'dc')
            ->filter(fn ($p) => empty($p->salary_sacrifice) && PensionContributionRule::isWorkplace($p))
            ->sum(fn ($p) => PensionContributionRule::monthlyEmployee($p, $salary) * 12);

        return [
            'non_savings' => max(0.0, (float) ($definitions['total_income'] ?? 0) - $interest - $dividends - $trust),
            'interest' => $this->resolvedInterest($user, $definitions),
            'dividends' => $dividends,
            'trust' => $trust,
            'net_pay' => $netPay,
        ];
    }

    /**
     * Income tax at ITA 2007 s23 Step 5 (before tax reductions), from the
     * app's one tax engine, UKTaxCalculator. Non-savings income goes in the
     * employment slot: only the income tax total is read, and non-savings
     * income is stacked first whatever its source (ITA 2007 s16).
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    public function incomeTaxOn(array $parts): float
    {
        $result = app(UKTaxCalculator::class)->calculateDetailedNetIncome(
            employmentIncome: $parts['non_savings'],
            trustIncome: (float) ($parts['trust'] ?? 0),
            interestIncome: $parts['interest'],
            dividendIncome: $parts['dividends'],
            pensionContributions: (float) ($parts['net_pay'] ?? 0),
        );

        return (float) $result['summary']['total_income_tax_before_credits'];
    }

    /**
     * Extra tax a transferor pays once their Personal Allowance falls by
     * $amount (s55B(6)). A smaller allowance taxes exactly the income that
     * $amount of extra non-savings income would, because non-savings income
     * is stacked first (ITA 2007 s16) and the transferor is far below the
     * taper threshold.
     *
     * @param  array{non_savings: float, interest: float, dividends: float, trust?: float, net_pay?: float}  $parts
     */
    private function extraTaxFromLosingAllowance(array $parts, float $amount): float
    {
        $reduced = $parts;
        $reduced['non_savings'] += $amount;

        return max(0.0, $this->incomeTaxOn($reduced) - $this->incomeTaxOn($parts));
    }

    /**
     * Relief-at-source figures for a contribution by or for someone with no
     * relevant earnings. Gross is the configured limit, relief is basic-rate
     * relief on it, and net is what the payer actually hands over.
     *
     * @return array{gross: float, net: float, relief: float}
     */
    public function nonEarnerPensionContribution(): array
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $gross = (float) ($pension['relevant_earnings_minimum'] ?? 0);
        $relief = round($gross * (float) ($pension['tax_relief']['basic_rate'] ?? 0), 2);

        return ['gross' => $gross, 'net' => round($gross - $relief, 2), 'relief' => $relief];
    }

    /**
     * Share of a net Gift Aid donation a higher- or additional-rate taxpayer
     * reclaims through Self Assessment: the grossed-up gift (net ÷ (1 − basic))
     * times the gap between their rate and the basic rate. 0 at basic rate.
     */
    public function giftAidReclaimFactor(string $band): float
    {
        if (! in_array($band, ['higher', 'additional'], true)) {
            return 0.0;
        }

        $basic = $this->bandRateForBand('basic');

        return $basic < 1 ? round(($this->bandRateForBand($band) - $basic) / (1 - $basic), 4) : 0.0;
    }

    /**
     * Dividend tax rate for a given band, sourced from
     * TaxConfigService['dividend_tax']. Centralises the match block previously
     * duplicated across DividendAllowanceHarvestStrategy, AssetShiftingBundle-
     * Strategy, and CrossSpouseBundleStrategy.
     */
    public function dividendRateForBand(string $band): float
    {
        $div = $this->taxConfig->getDividendTax();

        return match (strtolower($band)) {
            'higher' => (float) ($div['higher_rate'] ?? 0.3375),
            'additional' => (float) ($div['additional_rate'] ?? 0.3935),
            default => (float) ($div['basic_rate'] ?? 0.0875),
        };
    }

    /**
     * Age in whole years from a date_of_birth, or null when DOB is unknown.
     * Mirrors FamilyMember::getAgeAttribute.
     */
    public function ageOf(mixed $dateOfBirth): ?int
    {
        if ($dateOfBirth === null) {
            return null;
        }

        $dob = $dateOfBirth instanceof \DateTimeInterface
            ? Carbon::instance($dateOfBirth)
            : Carbon::parse((string) $dateOfBirth);

        return (int) $dob->diffInYears(now());
    }

    /** @return array<string, mixed> */
    private function incomeDefinitionsFor(User $user): array
    {
        $key = (int) $user->id;
        if (! isset($this->incomeDefinitionsCache[$key])) {
            $this->incomeDefinitionsCache[$key] = $this->incomeDefinitions->calculate($key);
        }

        return $this->incomeDefinitionsCache[$key];
    }

    /** @param array<string, mixed> $definitions */
    private function interestAdjustment(User $user, array $definitions): float
    {
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];

        return $this->resolvedInterest($user, $definitions) - (float) ($components['interest'] ?? 0);
    }

    /** @param array<string, mixed> $definitions */
    private function resolvedInterest(User $user, array $definitions): float
    {
        $components = is_array($definitions['components'] ?? null) ? $definitions['components'] : [];
        $captured = (float) ($components['interest'] ?? 0);

        return $captured > 0 ? $captured : $this->estimateAnnualInterest($user);
    }
}
