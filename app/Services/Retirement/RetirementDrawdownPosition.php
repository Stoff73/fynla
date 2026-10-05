<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\Estate\FutureValueCalculator;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Shared\MonteCarloEngine;
use App\Services\TaxConfigService;
use App\Services\UserProfile\UserProfileService;
use Carbon\Carbon;

/**
 * The Retirement page for someone drawing their pension (TODO item 6; CSJ
 * 2026-10-01: "Income + how long it lasts", for "anyone drawing from a
 * pension"). Worked out once here and served in the projections response, so
 * web and /m show the same figures (Rule 20).
 * Spec: docs/superpowers/specs/2026-10-01-retirement-drawing-view-design.md
 */
class RetirementDrawdownPosition
{
    /** The yearly income that lasts is rounded down to this. */
    private const INCOME_ROUNDING = 100;

    public function __construct(
        private readonly UserProfileService $userProfile,
        private readonly StatePensionAgeResolver $statePensionAge,
        private readonly FutureValueCalculator $lifeExpectancy,
        private readonly RetirementProjectionService $projection,
        private readonly RiskPreferenceService $riskService,
        private readonly MonteCarloSimulator $simulator,
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * Retired, past a recorded retirement date, or drawing from a pension
     * (even while still working).
     */
    public function isDrawing(User $user): bool
    {
        if ($user->employment_status === 'retired') {
            return true;
        }
        if ($user->retirement_date !== null && Carbon::parse($user->retirement_date)->lte(Carbon::today())) {
            return true;
        }

        return $user->dcPensions->contains(
            fn ($pension): bool => (float) ($pension->annual_drawdown_income ?? 0) > 0 || (bool) $pension->has_flexibly_accessed
        );
    }

    /** The drawing view's figures, or null for someone not drawing. */
    public function for(User $user): ?array
    {
        $user->loadMissing(['dcPensions', 'dbPensions', 'statePension', 'retirementProfile']);
        if (! $this->isDrawing($user)) {
            return null;
        }

        return [
            'retired_since' => $this->retiredSince($user),
            'income' => $this->income($user),
            'pot' => $this->pot($user),
        ];
    }

    /** @return array{date: string, age: int|null}|null */
    private function retiredSince(User $user): ?array
    {
        if ($user->retirement_date === null) {
            return null;
        }
        $date = Carbon::parse($user->retirement_date);
        if ($date->gt(Carbon::today())) {
            return null;
        }

        return [
            'date' => $date->toDateString(),
            'age' => $user->date_of_birth ? (int) Carbon::parse($user->date_of_birth)->diffInYears($date) : null,
        ];
    }

    /**
     * This year's income: the Income page's parts (IncomeDefinitionsService),
     * with what is in payment named pension by pension, and the Income tab's
     * Income Tax, National Insurance and take-home
     * (UserProfileService::incomeAndTaxFor; CSJ 2026-10-02: "retirement
     * income is just one line in the possible incomes"). Nothing is added up
     * or taxed here, so this box and the Income tab cannot disagree.
     */
    private function income(User $user): array
    {
        $incomeTab = $this->userProfile->incomeAndTaxFor($user);
        $parts = $incomeTab['income_parts'];
        $age = $user->date_of_birth ? (int) Carbon::parse($user->date_of_birth)->age : null;

        $lines = [];
        $add = function (string $key, string $label, float $amount) use (&$lines): void {
            if ($amount > 0) {
                $lines[] = ['key' => $key, 'label' => $label, 'amount' => round($amount, 2)];
            }
        };

        $add('employment', 'Pay from work', (float) $parts['employment']);
        // Pay given up under salary sacrifice: the gross above less this is the
        // pay taxed, as on the Income tab.
        if ((float) $incomeTab['annual_salary_sacrificed'] > 0) {
            $lines[] = ['key' => 'salary_sacrifice', 'label' => 'Salary sacrifice', 'amount' => round(-(float) $incomeTab['annual_salary_sacrificed'], 2)];
        }
        $add('self_employment', 'Self-employment profit', (float) $parts['self_employment']);
        // The pension part (ResolvesIncome::resolvePensionIncomeInPayment),
        // one line per pension in payment; these add up to it.
        foreach ($user->dcPensions as $pension) {
            $add('drawdown_'.$pension->id, 'Drawdown from '.($pension->scheme_name ?: 'your pension'), (float) ($pension->annual_drawdown_income ?? 0));
        }
        if ($user->statePension?->already_receiving) {
            $add('state_pension', 'State Pension', (float) ($user->statePension->state_pension_forecast_annual ?? 0));
        }
        foreach ($user->dbPensions as $pension) {
            if ($pension->isInPayment($age)) {
                $add('db_'.$pension->id, $pension->scheme_name ?: 'Final salary pension', (float) ($pension->accrued_annual_pension ?? 0));
            }
        }
        $add('rental', 'Rental profit', (float) $parts['rental']);
        $add('vesting', 'Shares vesting this year', (float) $parts['vesting']);
        $add('interest', 'Savings interest', (float) $parts['interest']);
        $add('dividend', 'Dividends', (float) $parts['dividend']);
        $add('trust', 'Trust income', (float) $parts['trust']);
        $add('other', 'Other income', (float) $parts['other']);

        return [
            'lines' => $lines,
            // Past State Pension age, the card says where the State Pension
            // stands rather than leaving it out silently: 'paid' (a line above),
            // 'no_amount' (paid, amount not recorded), 'missing' (nothing
            // recorded) or 'not_paid' (recorded, not marked as
            // being paid; the column is NOT NULL DEFAULT 0, so "never asked" and
            // "put off" read the same, and the card's wording holds for both).
            // It can be deferred, so it is never assumed from age
            // (https://www.gov.uk/deferring-state-pension). Null before then.
            'state_pension_status' => $this->statePensionStatus($user, $age),
            'interest_basis' => $incomeTab['interest_basis'],
            'total' => round((float) $incomeTab['gross_income'], 2),
            'income_tax' => round((float) $incomeTab['income_tax'], 2),
            'national_insurance' => round((float) $incomeTab['national_insurance'], 2),
            'take_home' => round((float) $incomeTab['net_income'], 2),
        ];
    }

    private function statePensionStatus(User $user, ?int $age): ?string
    {
        $reached = $this->statePensionAge->dateForUser($user);
        if ($age === null || $reached === null || $reached->gt(Carbon::today())) {
            return null;
        }
        $statePension = $user->statePension;

        return match (true) {
            $statePension === null => 'missing',
            ! (bool) $statePension->already_receiving => 'not_paid',
            // Marked as paid with no amount: it cannot be counted, so the card
            // asks for the amount rather than leaving it out silently.
            (float) ($statePension->state_pension_forecast_annual ?? 0) <= 0 => 'no_amount',
            default => 'paid',
        };
    }

    /**
     * The Defined Contribution pensions drawn down from today at the user's
     * risk level, the age each outcome runs out, life expectancy, and the
     * level income at which the lower outcome lasts to it.
     */
    private function pot(User $user): ?array
    {
        $value = (float) $user->dcPensions->sum(fn ($p): float => (float) ($p->current_fund_value ?? 0));
        if ($value <= 0 || ! $user->date_of_birth) {
            return null;
        }
        $drawing = (float) $user->dcPensions->sum(fn ($p): float => (float) ($p->annual_drawdown_income ?? 0));
        $age = (int) Carbon::parse($user->date_of_birth)->age;
        $endAge = (int) $this->taxConfig->get('retirement.projection_end_age', 100);
        $years = max(1, $endAge - $age);

        $risk = $this->projection->getUserRiskLevelWithSource($user);
        $params = $this->riskService->getReturnParameters($risk['level']);
        $return = (float) $params['expected_return_typical'] / 100;
        $volatility = (float) $params['volatility'] / 100;

        $bands = $this->simulator->extractProbabilityBands(
            $this->run($user, $value, $drawing, $return, $volatility, $years)
        );

        $life = $this->lifeExpectancy->getLifeExpectancy($user);
        $lifeAge = (int) $life['death_age'];

        return [
            'value' => round($value, 2),
            'drawing_per_year' => round($drawing, 2),
            'risk_level' => $risk['level'],
            // One label for both surfaces ("Lower-Medium"), from the risk level's own config.
            'risk_level_label' => $this->riskLabel($risk['level']),
            'expected_return' => $params['expected_return_typical'],
            'current_age' => $age,
            'end_age' => $endAge,
            'lasts_to_age' => [
                'middle' => $middle = $this->runsOutAt($bands, 50, $age),
                'lower' => $lower = $this->runsOutAt($bands, 20, $age),
            ],
            // The words every surface prints (CSJ 2026-10-01: web, /m and the
            // dashboard card each built these).
            'lasts_labels' => [
                'middle' => $this->lastsLabel($middle, $endAge),
                'lower' => $this->lastsLabel($lower, $endAge),
            ],
            'life_expectancy' => [
                'age' => $lifeAge,
                'source' => $life['source'] ?? 'ons',
            ],
            'income_to_last_to_life_expectancy' => $lifeAge > $age
                ? $this->incomeLastingTo($user, $value, $return, $volatility, $lifeAge - $age)
                : null,
            'year_by_year' => $bands,
        ];
    }

    /** "runs out by about age 76", or "lasts beyond 100" when it does not run out. */
    private function lastsLabel(?int $age, int $endAge): string
    {
        return $age === null ? 'lasts beyond '.$endAge : 'runs out by about age '.$age;
    }

    private function riskLabel(string $level): string
    {
        try {
            return (string) $this->riskService->getRiskLevelConfig($level)['display_name'];
        } catch (\InvalidArgumentException) {
            return ucfirst(str_replace('_', ' ', $level));
        }
    }

    private function run(User $user, float $value, float $drawing, float $return, float $volatility, int $years): array
    {
        return $this->simulator->simulate(
            $value,
            -$drawing / 12,
            $return,
            $volatility,
            $years,
            (int) $this->taxConfig->get('retirement.monte_carlo_iterations', 1000),
            "user_{$user->id}_drawdown_position_{$years}y",
            [],
            MonteCarloEngine::BAND_PERCENTILES,
        );
    }

    /** The age at which that outcome's pot first reaches £0, or null if it lasts the horizon. */
    private function runsOutAt(array $bands, int $percentile, int $age): ?int
    {
        foreach ($bands as $row) {
            if ((int) $row['year_number'] > 0 && (float) ($row["percentile_{$percentile}"] ?? 1) <= 0.0) {
                return $age + (int) $row['year_number'];
            }
        }

        return null;
    }

    /**
     * The most a year that can be drawn, level, with the lower outcome (4 in 5
     * do better) still above £0 at life expectancy. Bisection on the same
     * simulation, each run seeded from its inputs so the answer is stable.
     */
    private function incomeLastingTo(User $user, float $value, float $return, float $volatility, int $years): float
    {
        $lowerAtEnd = function (float $income) use ($user, $value, $return, $volatility, $years): float {
            $bands = $this->simulator->extractProbabilityBands($this->run($user, $value, $income, $return, $volatility, $years));
            $last = end($bands);

            return (float) ($last['percentile_20'] ?? 0);
        };

        $low = 0.0;
        $high = $value;
        // To the rounding the figure is shown at: about 11 runs for a £200,000 pot.
        while ($high - $low > self::INCOME_ROUNDING) {
            $mid = ($low + $high) / 2;
            if ($lowerAtEnd($mid) > 0) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return floor($low / self::INCOME_ROUNDING) * self::INCOME_ROUNDING;
    }
}
