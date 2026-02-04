<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\Settings\AssumptionsService;
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
    private const DEFAULT_RETIREMENT_AGE = 68;

    private const DEFAULT_PROJECTION_END_AGE = 90;

    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private NetWorthService $netWorthService,
        private LifeEventService $lifeEventService,
        private AssumptionsService $assumptionsService
    ) {}

    /**
     * Generate year-by-year projection with events.
     */
    public function generateProjection(int $userId, bool $household = false): array
    {
        $cacheKey = "goals_projection_{$userId}_" . ($household ? 'household' : 'individual');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $household) {
            $user = User::with(['goals', 'spouse'])->findOrFail($userId);

            // Check household permission
            if ($household && ! $user->hasAcceptedSpousePermission()) {
                $household = false;
            }

            $currentAge = $this->getCurrentAge($user);
            $retirementAge = $this->getRetirementAge($user);
            $projectionEndAge = $this->getProjectionEndAge($user);

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
                'summary' => $this->buildSummary($yearlyData, $events),
                'is_household' => $household,
            ];
        });
    }

    /**
     * Generate year-by-year data.
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
        $investments = $netWorth['breakdown']['investments'] ?? 0;
        $property = $netWorth['breakdown']['property'] ?? 0;
        $pensions = $netWorth['breakdown']['pensions'] ?? 0;
        $mortgage = $netWorth['liabilities_breakdown']['mortgages'] ?? 0;

        // Get annual income/expenditure from user profile
        $annualIncome = $this->getAnnualIncome($user, $household);
        $annualExpenditure = $this->getAnnualExpenditure($user, $household);
        $retirementIncome = $this->getRetirementIncome($user, $household);
        $retirementExpenditure = $this->getRetirementExpenditure($user, $household);

        // Growth rates
        $inflationRate = ($assumptions['inflation_rate'] ?? 2.5) / 100;
        $investmentGrowth = ($assumptions['investment_growth'] ?? 4.7) / 100;
        $propertyGrowth = ($assumptions['property_growth'] ?? 3.0) / 100;

        // Index goals and life events by year
        $goalsByYear = $this->indexEventsByYear($goals, $user, 'target_date');
        $lifeEventsByYear = $this->indexEventsByYear($lifeEvents, $user, 'expected_date');

        for ($age = $currentAge; $age <= $endAge; $age++) {
            $year = $currentYear + ($age - $currentAge);
            $isRetirement = $age >= $retirementAge;
            $phase = $isRetirement ? 'retirement' : 'accumulation';

            // Determine income and expenditure for this year
            $yearIncome = $isRetirement ? $retirementIncome : $annualIncome;
            $yearExpenditure = $isRetirement ? $retirementExpenditure : $annualExpenditure;

            // Apply life events for this year
            $yearIncomeEvents = 0;
            $yearExpenseEvents = 0;

            if (isset($lifeEventsByYear[$age])) {
                foreach ($lifeEventsByYear[$age] as $event) {
                    if ($event['impact_type'] === 'income') {
                        $yearIncomeEvents += $event['amount'];
                    } else {
                        $yearExpenseEvents += $event['amount'];
                    }
                }
            }

            // Apply goal completions as expenses
            if (isset($goalsByYear[$age])) {
                foreach ($goalsByYear[$age] as $goal) {
                    $yearExpenseEvents += $goal['target_amount'];
                }
            }

            // Calculate this year's cash flow
            $totalIncome = $yearIncome + $yearIncomeEvents;
            $totalExpenditure = $yearExpenditure + $yearExpenseEvents;
            $surplus = $totalIncome - $totalExpenditure;

            // Update cash (surplus goes to cash, deficit drawn from cash)
            $cash += $surplus;

            // If cash goes negative, draw from investments
            if ($cash < 0) {
                $deficit = abs($cash);
                $investments = max(0, $investments - $deficit);
                $cash = 0;
            }

            // Apply growth to investments and pensions
            if (! $isRetirement) {
                // Pre-retirement: assets grow
                $investments *= (1 + $investmentGrowth);
                $property *= (1 + $propertyGrowth);
                $pensions *= (1 + $investmentGrowth);
            } else {
                // During retirement: realistic asset depletion

                // Property: minimal growth in retirement (maintenance costs offset gains, or people downsize)
                // Typically property is illiquid and can't be spent
                $property *= (1 + ($propertyGrowth * 0.3)); // Reduced to ~1% effective growth

                // DC pensions are drawn down to fund retirement
                // The 4% sustainable withdrawal rate is a starting point but the pot depletes
                $dcDrawdown = $pensions * 0.04;
                $pensions = max(0, $pensions - $dcDrawdown);

                // Investments also get drawn if there's a shortfall
                // First, apply modest growth to remaining investments
                $investments *= (1 + ($investmentGrowth * 0.5)); // ~2.35% real return in retirement

                // If expenditure exceeds income, draw from liquid assets
                if ($surplus < 0) {
                    $shortfall = abs($surplus);

                    // Draw from investments first (most liquid after cash)
                    if ($shortfall > 0 && $investments > 0) {
                        $investmentWithdrawal = min($shortfall, $investments);
                        $investments = max(0, $investments - $investmentWithdrawal);
                        $shortfall -= $investmentWithdrawal;
                    }

                    // Then from pensions if investments exhausted
                    if ($shortfall > 0 && $pensions > 0) {
                        $pensionWithdrawal = min($shortfall, $pensions);
                        $pensions = max(0, $pensions - $pensionWithdrawal);
                        $shortfall -= $pensionWithdrawal;
                    }

                    $cash = max(0, $cash);
                } else {
                    // Even with surplus, retirees typically spend more as they age
                    // Healthcare costs, care needs, etc. - apply gradual depletion
                    $yearsInRetirement = $age - $retirementAge;
                    if ($yearsInRetirement > 10) {
                        // Later retirement years: increased spending
                        $additionalSpend = min($investments * 0.02, $investments);
                        $investments = max(0, $investments - $additionalSpend);
                    }
                }
            }

            // Reduce mortgage (simplified amortisation)
            if ($mortgage > 0 && $age < $retirementAge) {
                $mortgageYearsRemaining = $retirementAge - $age;
                if ($mortgageYearsRemaining > 0) {
                    $annualRepayment = $mortgage / $mortgageYearsRemaining;
                    $mortgage = max(0, $mortgage - $annualRepayment);
                }
            } elseif ($age >= $retirementAge) {
                $mortgage = 0; // Assume mortgage paid off at retirement
            }

            // Calculate totals
            $totalAssets = $cash + $investments + $property + $pensions;
            $totalLiabilities = $mortgage;
            $netWorthValue = $totalAssets - $totalLiabilities;

            $yearlyData[] = [
                'age' => $age,
                'year' => $year,
                'phase' => $phase,
                // Net Worth view
                'net_worth' => round($netWorthValue, 0),
                // Cash Flow view
                'income' => round($totalIncome, 0),
                'expenditure' => round($totalExpenditure, 0),
                'surplus' => round($surplus, 0),
                // Asset Breakdown view
                'assets' => [
                    'cash' => round($cash, 0),
                    'investments' => round($investments, 0),
                    'property' => round($property, 0),
                    'pensions' => round($pensions, 0),
                ],
                'liabilities' => [
                    'mortgage' => round($mortgage, 0),
                ],
                // Events this year
                'has_events' => isset($goalsByYear[$age]) || isset($lifeEventsByYear[$age]),
            ];

            // Inflate expenditure for next year
            $annualExpenditure *= (1 + $inflationRate);
            $retirementExpenditure *= (1 + $inflationRate);
        }

        return $yearlyData;
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
    private function buildSummary(array $yearlyData, array $events): array
    {
        if (empty($yearlyData)) {
            return [
                'starting_net_worth' => 0,
                'ending_net_worth' => 0,
                'peak_net_worth' => 0,
                'peak_age' => 0,
                'total_income_events' => 0,
                'total_expense_events' => 0,
                'goal_count' => 0,
                'life_event_count' => 0,
            ];
        }

        $netWorths = array_column($yearlyData, 'net_worth');
        $peakIndex = array_search(max($netWorths), $netWorths);

        $incomeEvents = array_filter($events, fn ($e) => $e['impact'] === 'income');
        $expenseEvents = array_filter($events, fn ($e) => $e['impact'] === 'expense');
        $goalEvents = array_filter($events, fn ($e) => $e['type'] === 'goal');
        $lifeEventList = array_filter($events, fn ($e) => $e['type'] === 'life_event');

        return [
            'starting_net_worth' => $yearlyData[0]['net_worth'],
            'ending_net_worth' => end($yearlyData)['net_worth'],
            'peak_net_worth' => max($netWorths),
            'peak_age' => $yearlyData[$peakIndex]['age'],
            'total_income_events' => array_sum(array_column($incomeEvents, 'amount')),
            'total_expense_events' => array_sum(array_column($expenseEvents, 'amount')),
            'goal_count' => count($goalEvents),
            'life_event_count' => count($lifeEventList),
        ];
    }

    /**
     * Get goals for projection.
     */
    private function getGoalsForProjection(User $user, bool $household): Collection
    {
        $query = Goal::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id);

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
            return 45; // Default
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
     * Get projection end age (life expectancy or default).
     */
    private function getProjectionEndAge(User $user): int
    {
        // Could use actuarial tables, but for simplicity use fixed age
        return self::DEFAULT_PROJECTION_END_AGE;
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

        return [
            'inflation_rate' => $estateAssumptions['inflation_rate'] ?? 2.5,
            'investment_growth' => 4.7, // Default real return
            'property_growth' => $estateAssumptions['property_growth_rate'] ?? 3.0,
        ];
    }

    /**
     * Get annual income.
     */
    private function getAnnualIncome(User $user, bool $household): float
    {
        // Use annual_employment_income (seeded from persona annual_income)
        $income = $user->annual_employment_income ?? $user->annual_income ?? 0;

        if ($household && $user->spouse) {
            $income += $user->spouse->annual_employment_income ?? $user->spouse->annual_income ?? 0;
        }

        return (float) $income;
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
     * Get retirement income.
     */
    private function getRetirementIncome(User $user, bool $household): float
    {
        // Simplified - use state pension + estimated private pension income
        $statePension = 11502; // Full new state pension 2025/26 approx

        if ($household && $user->spouse) {
            $statePension *= 2;
        }

        // Add DC pension drawdown estimate (4% sustainable withdrawal)
        $dcPensionValue = $user->dcPensions()->sum('current_fund_value');
        $dcDrawdown = $dcPensionValue * 0.04;

        // Add DB pension income
        $dbIncome = $user->dbPensions()->sum('accrued_annual_pension');

        return $statePension + $dcDrawdown + $dbIncome;
    }

    /**
     * Get retirement expenditure.
     */
    private function getRetirementExpenditure(User $user, bool $household): float
    {
        // Typically 70-80% of pre-retirement expenditure
        return $this->getAnnualExpenditure($user, $household) * 0.75;
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
