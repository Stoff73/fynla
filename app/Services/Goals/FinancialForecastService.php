<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\User;
use App\Services\UKTaxCalculator;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use Carbon\Carbon;

/**
 * Financial Forecast Service
 *
 * Projects income and expenditure forward in time, incorporating one-off life
 * events to produce a net cash flow forecast. Used by the Income & Expenditure
 * view and the cash flow warning system.
 */
class FinancialForecastService
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly LifeEventService $lifeEventService,
        private readonly UKTaxCalculator $taxCalculator
    ) {}

    /**
     * Get month-by-month forecast of regular income, expenditure, and life event impacts.
     *
     * @return array{months: array, summary: array}
     */
    public function getMonthlyForecast(int $userId, int $months = 12): array
    {
        $user = User::findOrFail($userId);
        $events = $this->lifeEventService->getActiveEventsForProjection($userId);

        $monthlyIncome = $this->getMonthlyIncome($user);
        $monthlyExpenditure = $this->resolveMonthlyExpenditure($user)['amount'];

        $forecast = [];
        $cumulativeSurplus = 0;
        $totalEventIncome = 0;
        $totalEventExpense = 0;

        for ($i = 0; $i < $months; $i++) {
            $monthStart = Carbon::now()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            // Find life events in this month
            $monthEvents = [];
            $monthEventIncome = 0;
            $monthEventExpense = 0;

            foreach ($events as $event) {
                if ($event->expected_date->between($monthStart, $monthEnd)) {
                    $rawAmount = (float) $event->amount;

                    $monthEvents[] = [
                        'id' => $event->id,
                        'event_name' => $event->event_name,
                        'event_type' => $event->event_type,
                        'amount' => $rawAmount,
                        'impact_type' => $event->impact_type,
                        'certainty' => $event->certainty,
                    ];

                    if ($event->impact_type === 'income') {
                        $monthEventIncome += $rawAmount;
                    } else {
                        $monthEventExpense += $rawAmount;
                    }
                }
            }

            $totalIncome = $monthlyIncome + $monthEventIncome;
            $totalExpense = $monthlyExpenditure + $monthEventExpense;
            $netCashFlow = $totalIncome - $totalExpense;
            $cumulativeSurplus += $netCashFlow;
            $totalEventIncome += $monthEventIncome;
            $totalEventExpense += $monthEventExpense;

            $forecast[] = [
                'month' => $monthStart->format('Y-m'),
                'month_label' => $monthStart->format('M Y'),
                'regular_income' => round($monthlyIncome, 2),
                'regular_expenditure' => round($monthlyExpenditure, 2),
                'event_income' => round($monthEventIncome, 2),
                'event_expense' => round($monthEventExpense, 2),
                'total_income' => round($totalIncome, 2),
                'total_expenditure' => round($totalExpense, 2),
                'net_cash_flow' => round($netCashFlow, 2),
                'cumulative_surplus' => round($cumulativeSurplus, 2),
                'has_events' => count($monthEvents) > 0,
                'events' => $monthEvents,
                'is_deficit' => $netCashFlow < 0,
            ];
        }

        return [
            'months' => $forecast,
            'summary' => [
                'monthly_regular_income' => round($monthlyIncome, 2),
                'monthly_regular_expenditure' => round($monthlyExpenditure, 2),
                'monthly_regular_surplus' => round($monthlyIncome - $monthlyExpenditure, 2),
                'total_event_income' => round($totalEventIncome, 2),
                'total_event_expense' => round($totalEventExpense, 2),
                'net_event_impact' => round($totalEventIncome - $totalEventExpense, 2),
                'forecast_months' => $months,
                'deficit_months' => collect($forecast)->where('is_deficit', true)->count(),
            ],
        ];
    }

    /**
     * Get annual forecast with life event overlays.
     *
     * @return array{years: array, summary: array}
     */
    public function getAnnualForecast(int $userId, int $years = 5): array
    {
        $user = User::findOrFail($userId);
        $events = $this->lifeEventService->getActiveEventsForProjection($userId);

        $annualIncome = $this->resolveNetAnnualIncome($user);
        $annualExpenditure = $this->resolveMonthlyExpenditure($user)['amount'] * 12;

        $forecast = [];
        $cumulativeSurplus = 0;

        for ($i = 0; $i < $years; $i++) {
            $yearStart = Carbon::now()->addYears($i)->startOfYear();
            $yearEnd = $yearStart->copy()->endOfYear();

            // For the current year, adjust from today
            if ($i === 0) {
                $yearStart = Carbon::now();
            }

            $yearEventIncome = 0;
            $yearEventExpense = 0;
            $yearEvents = [];

            foreach ($events as $event) {
                if ($event->expected_date->between($yearStart, $yearEnd)) {
                    $rawAmount = (float) $event->amount;

                    $yearEvents[] = [
                        'event_name' => $event->event_name,
                        'event_type' => $event->event_type,
                        'amount' => $rawAmount,
                        'impact_type' => $event->impact_type,
                        'certainty' => $event->certainty,
                        'expected_date' => $event->expected_date->toDateString(),
                    ];

                    if ($event->impact_type === 'income') {
                        $yearEventIncome += $rawAmount;
                    } else {
                        $yearEventExpense += $rawAmount;
                    }
                }
            }

            $totalIncome = $annualIncome + $yearEventIncome;
            $totalExpense = $annualExpenditure + $yearEventExpense;
            $netCashFlow = $totalIncome - $totalExpense;
            $cumulativeSurplus += $netCashFlow;

            $forecast[] = [
                'year' => $yearStart->format('Y'),
                'regular_income' => round($annualIncome, 2),
                'regular_expenditure' => round($annualExpenditure, 2),
                'event_income' => round($yearEventIncome, 2),
                'event_expense' => round($yearEventExpense, 2),
                'total_income' => round($totalIncome, 2),
                'total_expenditure' => round($totalExpense, 2),
                'net_cash_flow' => round($netCashFlow, 2),
                'cumulative_surplus' => round($cumulativeSurplus, 2),
                'has_events' => count($yearEvents) > 0,
                'events' => $yearEvents,
                'is_deficit' => $netCashFlow < 0,
            ];
        }

        return [
            'years' => $forecast,
            'summary' => [
                'annual_regular_income' => round($annualIncome, 2),
                'annual_regular_expenditure' => round($annualExpenditure, 2),
                'annual_regular_surplus' => round($annualIncome - $annualExpenditure, 2),
                'forecast_years' => $years,
                'deficit_years' => collect($forecast)->where('is_deficit', true)->count(),
            ],
        ];
    }

    /**
     * Get upcoming life event impacts in the next N months.
     *
     * Returns only the life event cash flow impacts, suitable for
     * warnings about future cash flow pressure.
     *
     * @return array{impacts: array, warnings: array}
     */
    public function getUpcomingImpacts(int $userId, int $months = 6): array
    {
        $user = User::findOrFail($userId);
        $events = $this->lifeEventService->getActiveEventsForProjection($userId);

        $cutoffDate = Carbon::now()->addMonths($months);
        $monthlyIncome = $this->getMonthlyIncome($user);
        $monthlyExpenditure = $this->resolveMonthlyExpenditure($user)['amount'];
        $monthlySurplus = max(0, $monthlyIncome - $monthlyExpenditure);

        $impacts = [];
        $warnings = [];

        foreach ($events as $event) {
            if ($event->expected_date->gt($cutoffDate) || $event->expected_date->lt(Carbon::now())) {
                continue;
            }

            $impact = [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_type' => $event->event_type,
                'amount' => (float) $event->amount,
                'impact_type' => $event->impact_type,
                'expected_date' => $event->expected_date->toDateString(),
                'certainty' => $event->certainty,
                'months_away' => (int) Carbon::now()->diffInMonths($event->expected_date),
            ];

            $impacts[] = $impact;

            // Generate warning if a large expense could push into deficit
            if ($event->impact_type === 'expense' && (float) $event->amount > $monthlySurplus * 3) {
                $monthsToSave = $monthlySurplus > 0
                    ? ceil((float) $event->amount / $monthlySurplus)
                    : null;

                $warnings[] = [
                    'event_name' => $event->event_name,
                    'amount' => (float) $event->amount,
                    'months_away' => $impact['months_away'],
                    'severity' => (float) $event->amount > $monthlySurplus * 6 ? 'high' : 'medium',
                    'message' => 'Planned expense of £'.number_format((float) $event->amount)
                        .' in '.$impact['months_away'].' months.'
                        .($monthsToSave !== null
                            ? ' At your current savings rate, you would need '.$monthsToSave.' months to save for this.'
                            : ' You may need to draw from savings or investments.'),
                ];
            }
        }

        return [
            'impacts' => $impacts,
            'warnings' => $warnings,
            'monthly_surplus' => round($monthlySurplus, 2),
            'period_months' => $months,
        ];
    }

    /**
     * Get monthly net income from user profile.
     */
    private function getMonthlyIncome(User $user): float
    {
        return $this->resolveNetAnnualIncome($user) / 12;
    }
}
