<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
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

    public function bandFromIncome(float $income): string
    {
        $thresholds = $this->bandThresholds();

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
        return $this->bandRateForBand($this->bandFromIncome($this->taxableIncomeFor($user)));
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
     * Total taxable income from every captured source. When the user has no
     * explicit annual-interest figure, use the interest implied by captured
     * savings balances and rates rather than silently treating it as zero.
     */
    public function taxableIncomeFor(User $user): float
    {
        $key = (int) $user->id;
        if (! isset($this->taxableIncomeCache[$key])) {
            $definitions = $this->incomeDefinitionsFor($user);
            $this->taxableIncomeCache[$key] = max(
                0.0,
                (float) ($definitions['total_income'] ?? 0) + $this->interestAdjustment($user, $definitions),
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
        // forUser() is joint-aware; the Collection-level where('user_id')
        // post-filter preserves the original single-owner sum.
        return (float) app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->sum(function ($acc) {
                // interest_rate convention is mixed across the codebase
                // (factory writes decimals 0.04, seeders + onboarding write
                // percent 4.0). Normalise: anything > 1 is treated as percent.
                $rate = (float) $acc->interest_rate;
                if ($rate > 1) {
                    $rate /= 100;
                }

                return (float) $acc->current_balance * $rate;
            });
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

        $userIncome = (float) ($user->annual_employment_income ?? 0);

        // Sum each pension's input separately. monthly_contribution_amount
        // takes precedence when set; otherwise fall back to the captured
        // employee+employer percentages applied to the pension's
        // annual_salary (the user's earnings at that employer) — the
        // SaveTax onboarding writes %s, not a £ monthly figure.
        return (float) app(PensionStore::class)
            ->forUserByType($user, 'dc')
            ->sum(function ($pension) use ($userIncome) {
                $monthly = (float) ($pension->monthly_contribution_amount ?? 0);
                if ($monthly > 0) {
                    return $monthly * 12;
                }

                $salary = (float) ($pension->annual_salary ?? 0) ?: $userIncome;
                $employee = (float) ($pension->employee_contribution_percent ?? 0);
                $employer = (float) ($pension->employer_contribution_percent ?? 0);

                return $salary * (($employee + $employer) / 100);
            });
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
     * Total annual employer pension contributions across all DC pensions,
     * estimated as (annual_salary ?? user employment income) × employer_pct.
     * Pensions with null employer_contribution_percent contribute 0.
     */
    public function employerPensionContributionsFor(User $user): float
    {
        $userIncome = (float) ($user->annual_employment_income ?? 0);

        return (float) app(PensionStore::class)
            ->forUserByType($user, 'dc')
            ->whereNotNull('employer_contribution_percent')
            ->sum(function ($p) use ($userIncome) {
                $base = (float) ($p->annual_salary ?? 0) > 0
                    ? (float) $p->annual_salary
                    : $userIncome;

                return $base * ((float) $p->employer_contribution_percent / 100);
            });
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
