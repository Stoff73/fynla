<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\Settings\AssumptionsService;
use App\Services\UKTaxCalculator;
use App\Traits\ResolvesIncome;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Goals Projection Service
 *
 * Generates year-by-year projections combining current net worth,
 * goals (as future expenditure), and life events (income/expense).
 *
 * Supports three chart views:
 * - Net Worth: Total net worth over time
 * - Cash Flow: Income vs Expenditure
 * - Asset Breakdown: Stacked asset categories
 */
class GoalsProjectionService
{
    use ResolvesIncome;

    private const DEFAULT_RETIREMENT_AGE = 68;

    private const DEFAULT_PROJECTION_END_AGE = 90;

    private const CACHE_TTL = 86400; // 24 hours — invalidated on data change

    public function __construct(
        private readonly NetWorthService $netWorthService,
        private readonly LifeEventService $lifeEventService,
        private readonly AssumptionsService $assumptionsService,
        private readonly UKTaxCalculator $taxCalculator
    ) {}

    /**
     * Generate year-by-year projection with events.
     */
    public function generateProjection(int $userId, bool $household = false): array
    {
        $cacheKey = "goals_projection_{$userId}_".($household ? 'household' : 'individual');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $household) {
            $user = User::with(['goals', 'spouse', 'spouse.mortgages', 'investmentAccounts.holdings', 'mortgages'])->findOrFail($userId);

            // Check household permission
            if ($household && ! $user->hasAcceptedSpousePermission()) {
                $household = false;
            }

            $currentAge = $this->getCurrentAge($user);
            $retirementAge = $this->getRetirementAge($user);
            $projectionEndAge = $currentAge <= 35
                ? max(self::DEFAULT_RETIREMENT_AGE, $retirementAge)
                : self::DEFAULT_PROJECTION_END_AGE;

            // Get assumptions
            $assumptions = $this->getProjectionAssumptions($user);

            // Get current net worth breakdown
            $netWorth = $this->netWorthService->calculateNetWorth($user);

            // Get goals and life events
            $goals = $this->getGoalsForProjection($user, $household);
            $lifeEvents = $this->lifeEventService->getActiveEventsForProjection($userId, $household);

            // Generate year-by-year projections
            $yearlyData = $this->generateYearlyData(
                $user,
                $netWorth,
                $currentAge,
                $retirementAge,
                $projectionEndAge,
                $assumptions,
                $goals,
                $lifeEvents,
                $household
            );

            // Build events array for chart icons
            $events = $this->buildEventsArray($user, $goals, $lifeEvents);

            return [
                'current_age' => $currentAge,
                'retirement_age' => $retirementAge,
                'projection_end_age' => $projectionEndAge,
                'yearly_data' => $yearlyData,
                'events' => $events,
                'assumptions' => $assumptions,
                'summary' => $this->buildSummary($yearlyData, $events, $retirementAge),
                'is_household' => $household,
            ];
        });
    }

    /**
     * Generate year-by-year data using simple FV calculation.
     *
     * FV = PV * (1 + real_rate)^n
     * real_rate = investment_growth - inflation_rate
     *
     * Each asset class grows at its own real rate. Annual expenditure
     * is deducted from cash each year. Life events and goals are
     * applied as one-off impacts.
     */
    private function generateYearlyData(
        User $user,
        array $netWorth,
        int $currentAge,
        int $retirementAge,
        int $endAge,
        array $assumptions,
        Collection $goals,
        Collection $lifeEvents,
        bool $household
    ): array {
        $yearlyData = [];
        $currentYear = (int) date('Y');

        // Start with current values
        $cash = $netWorth['breakdown']['cash'] ?? 0;
        $investments = ($netWorth['breakdown']['investments'] ?? 0)
            + ($netWorth['breakdown']['business'] ?? 0)
            + ($netWorth['breakdown']['chattels'] ?? 0);
        $property = $netWorth['breakdown']['property'] ?? 0;
        $pensions = $netWorth['breakdown']['pensions'] ?? 0;
        $mortgage = $netWorth['liabilities_breakdown']['mortgages'] ?? 0;

        // Annual income and expenditure
        $annualNetIncome = $this->getAnnualNetIncome($user, $household);
        $annualExpenditure = $this->getAnnualExpenditure($user, $household);

        // Real growth rates (nominal - inflation)
        $inflationRate = ($assumptions['inflation_rate'] ?? 2.0) / 100;
        $investmentGrowth = ($assumptions['investment_growth'] ?? 5.0) / 100;
        $propertyGrowth = ($assumptions['property_growth'] ?? 3.0) / 100;
        $cashGrowthRate = ($assumptions['cash_growth_rate'] ?? max(0, ($assumptions['inflation_rate'] ?? 2.0) - 0.5)) / 100;

        $realInvestmentRate = $investmentGrowth - $inflationRate;
        $realPropertyRate = $propertyGrowth - $inflationRate;
        $realCashRate = $cashGrowthRate - $inflationRate;

        // Get mortgage parameters for amortisation
        $mortgageParams = $this->getMortgageParameters($user, $household);

        // Index goals and life events by age
        $goalsByYear = $this->indexEventsByYear($goals, $user, 'target_date');
        $lifeEventsByYear = $this->indexEventsByYear($lifeEvents, $user, 'expected_date');

        // Non-mortgage liabilities (credit cards, loans, etc.)
        $otherLiabilities = ($netWorth['liabilities_breakdown']['credit_cards'] ?? 0)
            + ($netWorth['liabilities_breakdown']['loans'] ?? 0)
            + ($netWorth['liabilities_breakdown']['other'] ?? 0);

        for ($age = $currentAge; $age <= $endAge; $age++) {
            $year = $currentYear + ($age - $currentAge);
            $phase = $age >= $retirementAge ? 'retirement' : 'accumulation';
            $yearsElapsed = $age - $currentAge;

            // First year shows actual current snapshot — no income/expense adjustments
            if ($age > $currentAge) {
                // Apply annual surplus (income minus expenditure) to cash
                $annualSurplus = $annualNetIncome - $annualExpenditure;
                $cash += $annualSurplus;
                if ($cash < 0) {
                    $investments = max(0, $investments + $cash);
                    $cash = 0;
                }
            }

            // Apply life event impacts for this year
            $eventImpact = 0;
            if (isset($lifeEventsByYear[$age])) {
                foreach ($lifeEventsByYear[$age] as $event) {
                    if ($event['impact_type'] === 'income') {
                        $eventImpact += $event['amount'];
                    } else {
                        $eventImpact -= $event['amount'];
                    }
                }
            }

            // Apply goal completions as expenses
            if (isset($goalsByYear[$age])) {
                foreach ($goalsByYear[$age] as $goal) {
                    $eventImpact -= $goal['target_amount'];
                }
            }

            // Apply event impacts to cash (most liquid asset)
            if ($eventImpact != 0) {
                $cash += $eventImpact;
                if ($cash < 0) {
                    $investments = max(0, $investments + $cash);
                    $cash = 0;
                }
            }

            // Calculate mortgage balance using amortisation
            $mortgage = $this->calculateMortgageBalance(
                $mortgageParams,
                $yearsElapsed
            );

            // Record this year's data before applying growth
            $totalAssets = $cash + $investments + $property + $pensions;
            $totalLiabilities = $mortgage + $otherLiabilities;
            $netWorthValue = $totalAssets - $totalLiabilities;

            // Reduce other liabilities year-on-year (assume paid from surplus)
            if ($age > $currentAge && $otherLiabilities > 0) {
                $otherLiabilities = max(0, $otherLiabilities * 0.5);
            }

            $yearlyData[] = [
                'age' => $age,
                'year' => $year,
                'phase' => $phase,
                'net_worth' => round($netWorthValue, 0),
                'income' => round($annualNetIncome, 0),
                'expenditure' => round($annualExpenditure, 0),
                'surplus' => round($annualNetIncome - $annualExpenditure, 0),
                'assets' => [
                    'cash' => round(max(0, $cash), 0),
                    'investments' => round(max(0, $investments), 0),
                    'property' => round(max(0, $property), 0),
                    'pensions' => round(max(0, $pensions), 0),
                ],
                'liabilities' => [
                    'mortgage' => round($mortgage, 0),
                ],
                'has_events' => isset($goalsByYear[$age]) || isset($lifeEventsByYear[$age]),
            ];

            // Apply real growth for next year
            $investments *= (1 + $realInvestmentRate);
            $pensions *= (1 + $realInvestmentRate);
            $property *= (1 + $realPropertyRate);
            if ($cash > 0) {
                $cash *= (1 + $realCashRate);
            }
        }

        return $yearlyData;
    }

    /**
     * Get mortgage parameters from the user's actual mortgage records.
     *
     * @return array{original_balance: float, annual_rate: float, remaining_years: int, mortgage_type: string}
     */
    private function getMortgageParameters(User $user, bool $household): array
    {
        $mortgages = $user->mortgages ?? collect();

        if ($household && $user->spouse) {
            $spouseMortgages = $user->spouse->mortgages ?? collect();
            $mortgages = $mortgages->merge($spouseMortgages);
        }

        if ($mortgages->isEmpty()) {
            return [
                'original_balance' => 0,
                'annual_rate' => 0.04,
                'remaining_years' => 0,
                'mortgage_type' => 'repayment',
            ];
        }

        // Aggregate all mortgages into a weighted average
        $totalBalance = 0.0;
        $weightedRate = 0.0;
        $maxRemainingMonths = 0;
        $primaryType = 'repayment';

        foreach ($mortgages as $mortgage) {
            $balance = (float) ($mortgage->outstanding_balance ?? 0);
            if ($balance <= 0) {
                continue;
            }

            $totalBalance += $balance;
            $rate = (float) ($mortgage->interest_rate ?? 4.0);
            $weightedRate += $balance * ($rate / 100);
            $remainingMonths = (int) ($mortgage->remaining_term_months ?? 300);
            $maxRemainingMonths = max($maxRemainingMonths, $remainingMonths);
            $primaryType = $mortgage->mortgage_type ?? 'repayment';
        }

        $annualRate = $totalBalance > 0 ? $weightedRate / $totalBalance : 0.04;

        return [
            'original_balance' => $totalBalance,
            'annual_rate' => $annualRate,
            'remaining_years' => max(0, (int) ceil($maxRemainingMonths / 12)),
            'mortgage_type' => $primaryType,
        ];
    }

    /**
     * Calculate mortgage balance using proper amortisation.
     *
     * For repayment mortgages: B(t) = P * ((1+r)^n - (1+r)^t) / ((1+r)^n - 1)
     * For interest-only mortgages: balance stays constant until term end.
     */
    private function calculateMortgageBalance(array $params, int $yearsElapsed): float
    {
        $originalBalance = $params['original_balance'];
        $remainingYears = $params['remaining_years'];
        $annualRate = $params['annual_rate'];
        $mortgageType = $params['mortgage_type'];

        if ($originalBalance <= 0 || $remainingYears <= 0) {
            return 0.0;
        }

        // Past the end of the mortgage term
        if ($yearsElapsed >= $remainingYears) {
            return 0.0;
        }

        // Interest-only: balance stays constant until term end
        if ($mortgageType === 'interest_only') {
            return $originalBalance;
        }

        // Repayment (or mixed — treat as repayment for projection purposes)
        if ($annualRate <= 0) {
            // Zero rate: simple linear reduction
            return max(0, $originalBalance * (1 - $yearsElapsed / $remainingYears));
        }

        $r = $annualRate;
        $n = $remainingYears;
        $t = $yearsElapsed;

        $factorN = pow(1 + $r, $n);
        $factorT = pow(1 + $r, $t);
        $balance = $originalBalance * ($factorN - $factorT) / ($factorN - 1);

        return max(0, $balance);
    }

    /**
     * Get annual net income using the UK tax calculator.
     */
    private function getAnnualNetIncome(User $user, bool $household): float
    {
        $netIncome = $this->resolveNetAnnualIncome($user);

        if ($household && $user->spouse) {
            $netIncome += $this->resolveNetAnnualIncome($user->spouse);
        }

        return $netIncome;
    }

    /**
     * Get annual expenditure.
     */
    private function getAnnualExpenditure(User $user, bool $household): float
    {
        $expenditure = $user->annual_expenditure ?? ($user->monthly_expenditure ?? 0) * 12;

        if ($household && $user->spouse) {
            $expenditure += $user->spouse->annual_expenditure ?? ($user->spouse->monthly_expenditure ?? 0) * 12;
        }

        return (float) $expenditure;
    }

    /**
     * Build events array for chart icons.
     */
    private function buildEventsArray(User $user, Collection $goals, Collection $lifeEvents): array
    {
        $events = [];
        $currentAge = $this->getCurrentAge($user);

        // Add goals
        foreach ($goals as $goal) {
            $age = $this->getAgeAtDate($user, $goal->target_date);
            if ($age === null) {
                continue;
            }

            $events[] = [
                'id' => $goal->id,
                'age' => $age,
                'year' => $goal->target_date->year,
                'type' => 'goal',
                'category' => $goal->goal_type,
                'name' => $goal->goal_name,
                'amount' => (float) $goal->target_amount,
                'impact' => 'expense',
                'icon' => $this->getGoalIcon($goal->goal_type),
                'color' => $this->getGoalColor($goal->goal_type),
                'is_completed' => $goal->status === 'completed' || $age < $currentAge,
            ];
        }

        // Add life events
        foreach ($lifeEvents as $event) {
            $age = $event->getAgeAtEvent($user);
            if ($age === null) {
                continue;
            }

            $events[] = [
                'id' => $event->id,
                'age' => $age,
                'year' => $event->expected_date->year,
                'type' => 'life_event',
                'category' => $event->event_type,
                'name' => $event->event_name,
                'amount' => (float) $event->amount,
                'impact' => $event->impact_type,
                'icon' => $event->icon ?? $this->getLifeEventIcon($event->event_type),
                'color' => $this->getLifeEventColor($event->event_type, $event->impact_type),
                'certainty' => $event->certainty,
                'is_completed' => $event->status === 'completed' || $age < $currentAge,
            ];
        }

        // Sort by age
        usort($events, fn ($a, $b) => $a['age'] <=> $b['age']);

        return $events;
    }

    /**
     * Build summary statistics.
     */
    private function buildSummary(array $yearlyData, array $events, int $retirementAge): array
    {
        if (empty($yearlyData)) {
            return [
                'starting_net_worth' => 0,
                'ending_net_worth' => 0,
                'retirement_net_worth' => 0,
                'retirement_age' => $retirementAge,
                'peak_net_worth' => 0,
                'peak_age' => 0,
                'total_income_events' => 0,
                'total_expense_events' => 0,
                'income_event_count' => 0,
                'expense_event_count' => 0,
                'goal_count' => 0,
                'life_event_count' => 0,
            ];
        }

        $netWorths = array_column($yearlyData, 'net_worth');
        $peakIndex = array_search(max($netWorths), $netWorths);

        // Find net worth at retirement age
        $retirementNetWorth = 0;
        foreach ($yearlyData as $yearData) {
            if ($yearData['age'] === $retirementAge) {
                $retirementNetWorth = $yearData['net_worth'];
                break;
            }
        }

        $incomeEvents = array_filter($events, fn ($e) => $e['impact'] === 'income');
        $expenseEvents = array_filter($events, fn ($e) => $e['impact'] === 'expense');
        $goalEvents = array_filter($events, fn ($e) => $e['type'] === 'goal');
        $lifeEventList = array_filter($events, fn ($e) => $e['type'] === 'life_event');

        return [
            'starting_net_worth' => $yearlyData[0]['net_worth'],
            'ending_net_worth' => end($yearlyData)['net_worth'],
            'retirement_net_worth' => $retirementNetWorth,
            'retirement_age' => $retirementAge,
            'peak_net_worth' => max($netWorths),
            'peak_age' => $yearlyData[$peakIndex]['age'],
            'total_income_events' => array_sum(array_column($incomeEvents, 'amount')),
            'total_expense_events' => array_sum(array_column($expenseEvents, 'amount')),
            'income_event_count' => count($incomeEvents),
            'expense_event_count' => count($expenseEvents),
            'goal_count' => count($goalEvents),
            'life_event_count' => count($lifeEventList),
        ];
    }

    /**
     * Get goals for projection.
     */
    private function getGoalsForProjection(User $user, bool $household): Collection
    {
        $query = Goal::forUserOrJoint($user->id);

        if ($household && $user->spouse_user_id) {
            $query->orWhere(function ($q) use ($user) {
                $q->where('user_id', $user->spouse_user_id)
                    ->where('show_in_household_view', true);
            });
        }

        return $query->where('status', 'active')
            ->where('show_in_projection', true)
            ->whereNotNull('target_date')
            ->where('target_date', '>', now())
            ->orderBy('target_date')
            ->get();
    }

    /**
     * Index events by the age they occur.
     */
    private function indexEventsByYear(Collection $items, User $user, string $dateField): array
    {
        $indexed = [];

        foreach ($items as $item) {
            $date = $item->{$dateField};
            if (! $date) {
                continue;
            }

            $age = $this->getAgeAtDate($user, $date);
            if ($age === null) {
                continue;
            }

            if (! isset($indexed[$age])) {
                $indexed[$age] = [];
            }

            $indexed[$age][] = $item->toArray();
        }

        return $indexed;
    }

    /**
     * Get current age.
     */
    private function getCurrentAge(User $user): int
    {
        if (! $user->date_of_birth) {
            return self::DEFAULT_RETIREMENT_AGE; // Use retirement age as fallback when DOB missing
        }

        return Carbon::parse($user->date_of_birth)->age;
    }

    /**
     * Get retirement age.
     */
    private function getRetirementAge(User $user): int
    {
        return $user->target_retirement_age
            ?? $user->retirementProfile?->target_retirement_age
            ?? self::DEFAULT_RETIREMENT_AGE;
    }

    /**
     * Get age at a specific date.
     */
    private function getAgeAtDate(User $user, $date): ?int
    {
        if (! $user->date_of_birth || ! $date) {
            return null;
        }

        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return (int) $user->date_of_birth->diffInYears($date);
    }

    /**
     * Get projection assumptions.
     */
    private function getProjectionAssumptions(User $user): array
    {
        $estateAssumptions = $this->assumptionsService->getEstateAssumptions($user);
        $investmentAssumptions = $this->assumptionsService->getTypeAssumptions($user, 'investments');

        // Use the investment return rate from user assumptions (respects risk profile and overrides)
        $investmentGrowth = $investmentAssumptions['return_rate']
            ?? $estateAssumptions['custom_investment_rate']
            ?? 5.0;

        $inflationRate = $estateAssumptions['inflation_rate'] ?? 2.0;

        return [
            'inflation_rate' => $inflationRate,
            'investment_growth' => $investmentGrowth,
            'property_growth' => $estateAssumptions['property_growth_rate'] ?? 3.0,
            'cash_growth_rate' => max(0, $inflationRate - 0.5),
        ];
    }

    /**
     * Get icon for goal type.
     */
    private function getGoalIcon(string $goalType): string
    {
        return match ($goalType) {
            'emergency_fund' => 'ShieldCheckIcon',
            'property_purchase', 'home_deposit' => 'HomeIcon',
            'holiday' => 'GlobeAltIcon',
            'car_purchase' => 'TruckIcon',
            'wedding' => 'HeartIcon',
            'education' => 'AcademicCapIcon',
            'retirement' => 'SunIcon',
            'wealth_accumulation' => 'ChartBarIcon',
            default => 'FlagIcon',
        };
    }

    /**
     * Get color for goal type.
     */
    private function getGoalColor(string $goalType): string
    {
        return match ($goalType) {
            'emergency_fund' => '#15803D',
            'property_purchase', 'home_deposit' => '#1257A0',
            'holiday' => '#14B8A6',
            'car_purchase' => '#64748B',
            'wedding' => '#EC4899',
            'education' => '#7C3AED',
            'retirement' => '#F59E0B',
            default => '#3B82F6',
        };
    }

    /**
     * Get icon for life event type.
     */
    private function getLifeEventIcon(string $eventType): string
    {
        return match ($eventType) {
            'inheritance' => 'GiftIcon',
            'gift_received' => 'GiftTopIcon',
            'bonus' => 'BanknotesIcon',
            'redundancy_payment' => 'DocumentTextIcon',
            'property_sale' => 'BuildingOfficeIcon',
            'business_sale' => 'BriefcaseIcon',
            'pension_lump_sum' => 'CurrencyPoundIcon',
            'lottery_windfall' => 'SparklesIcon',
            'large_purchase' => 'ShoppingCartIcon',
            'home_improvement' => 'WrenchScrewdriverIcon',
            'wedding' => 'HeartIcon',
            'education_fees' => 'AcademicCapIcon',
            'gift_given' => 'GiftIcon',
            'medical_expense' => 'HeartIcon',
            default => 'CalendarIcon',
        };
    }

    /**
     * Get color for life event type.
     */
    private function getLifeEventColor(string $eventType, string $impactType): string
    {
        // Income events get green-ish colors, expense events get red-ish
        if ($impactType === 'expense') {
            return match ($eventType) {
                'large_purchase' => '#EF4444',
                'home_improvement' => '#64748B',
                'wedding' => '#EC4899',
                'education_fees' => '#7C3AED',
                'gift_given' => '#EC4899',
                'medical_expense' => '#EF4444',
                default => '#EF4444',
            };
        }

        return match ($eventType) {
            'inheritance' => '#7C3AED',
            'gift_received' => '#EC4899',
            'bonus' => '#15803D',
            'redundancy_payment' => '#F59E0B',
            'property_sale' => '#1257A0',
            'business_sale' => '#0EA5E9',
            'pension_lump_sum' => '#F59E0B',
            'lottery_windfall' => '#EC4899',
            default => '#15803D',
        };
    }

    /**
     * Clear cached projection data.
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("goals_projection_{$userId}_individual");
        Cache::forget("goals_projection_{$userId}_household");
    }
}
