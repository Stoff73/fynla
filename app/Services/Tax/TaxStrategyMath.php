<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
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

    public function bandRateFor(User $user): float
    {
        return $this->bandRateForBand(
            $this->bandFromIncome((float) ($user->annual_employment_income ?? 0))
        );
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
        $employment = (float) ($user->annual_employment_income ?? 0);
        $dividends = (float) ($user->annual_dividend_income ?? 0);
        $interest = $this->estimateAnnualInterest($user);

        return $employment + $dividends + $interest;
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
        return (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->get()
            ->sum(fn ($acc) => (float) $acc->current_balance * (float) $acc->interest_rate);
    }

    public function estimateIsaSubscriptionsThisYear(User $user): float
    {
        // V1: use ISA balances as a proxy for current-year subscriptions when
        // no per-subscription record exists. Conservative approximation.
        return (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', true)
            ->sum('current_balance');
    }

    public function estimatePensionContributionThisYear(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        if ($overrides?->pensionContributionPercent !== null) {
            return (float) ($user->annual_employment_income ?? 0) * ($overrides->pensionContributionPercent / 100);
        }

        $monthlyTotal = (float) DCPension::where('user_id', $user->id)->sum('monthly_contribution_amount');

        return $monthlyTotal * 12;
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
