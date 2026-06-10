<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
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

    public function __construct(
        private readonly TaxConfigService $taxConfig,
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
     * Composed taxable-income view: employment income + dividend income +
     * estimated savings interest. Acts as a best-effort proxy for HMRC
     * taxable income above the Personal Allowance.
     */
    public function taxableIncomeFor(User $user): float
    {
        $key = (int) $user->id;
        if (! isset($this->taxableIncomeCache[$key])) {
            $employment = (float) ($user->annual_employment_income ?? 0);
            $dividends = (float) ($user->annual_dividend_income ?? 0);
            $interest = $this->estimateAnnualInterest($user);
            $this->taxableIncomeCache[$key] = $employment + $dividends + $interest;
        }

        return $this->taxableIncomeCache[$key];
    }

    /**
     * Remaining Pension Annual Allowance for the current tax year, after the
     * user's existing contributions and any in-flight slider override.
     * Floored at 0; does not currently account for tapered AA (Phase 5) or
     * carry-forward (Phase 4 — see PensionAACarryForwardStrategy).
     */
    public function availableAnnualAllowance(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $aa = (float) ($pension['annual_allowance'] ?? 60000);
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
        $captured = $allIsas
            ->where('isa_subscription_year', $currentTaxYear)
            ->filter(fn ($a) => $a->isa_subscription_amount !== null)
            ->sum('isa_subscription_amount');

        if ((float) $captured > 0) {
            return (float) $captured;
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
        return (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + $this->estimateAnnualInterest($user)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);
    }

    /**
     * Adjusted income for tapered AA — threshold income plus employer
     * pension contributions added back. Used as the £260k gate for the
     * tapered Annual Allowance.
     */
    public function adjustedIncomeFor(User $user): float
    {
        return $this->thresholdIncomeFor($user) + $this->employerPensionContributionsFor($user);
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
}
