<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Liability;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

class CashFlowProjector
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Create personal profit & loss statement for a tax year
     */
    public function createPersonalPL(int $userId, string $taxYear): array
    {
        $user = User::findOrFail($userId);

        // Parse tax year format (e.g., "2024" or "2024/25")
        // Extract the first year and format as "YYYY/YY"
        $yearParts = explode('/', $taxYear);
        $startYear = (int) $yearParts[0];
        $formattedTaxYear = $startYear.'/'.str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);

        // Get tax year dates (6 April to 5 April next year)
        $taxYearStart = Carbon::createFromDate($startYear, 4, 6);
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();

        // In a full implementation, you would have income and expenditure tables
        // For now, we'll create a structure that can be populated

        $income = $this->calculateIncome($userId, $taxYearStart, $taxYearEnd);
        $expenditure = $this->calculateExpenditure($userId, $taxYearStart, $taxYearEnd);

        $totalIncome = array_sum(array_column($income, 'amount'));
        $totalExpenditure = array_sum(array_column($expenditure, 'amount'));
        $netSurplusDeficit = $totalIncome - $totalExpenditure;

        // Structure to match both test expectations and frontend needs
        return [
            'tax_year' => $formattedTaxYear,
            'period_start' => $taxYearStart->format('Y-m-d'),
            'period_end' => $taxYearEnd->format('Y-m-d'),
            // Nested structure for tests and detailed breakdown
            'income' => [
                'items' => $income,
                'total' => round($totalIncome, 2),
            ],
            'expenditure' => [
                'items' => $expenditure,
                'total' => round($totalExpenditure, 2),
            ],
            // Flattened income structure for frontend convenience
            'employment_income' => $income[0]['amount'] ?? 0,
            'dividend_income' => $income[1]['amount'] ?? 0,
            'interest_income' => $income[2]['amount'] ?? 0,
            'rental_income' => $income[3]['amount'] ?? 0,
            'other_income' => $income[4]['amount'] ?? 0,
            // Flattened expenditure structure for frontend convenience
            'essential_expenses' => array_sum(array_map(
                fn ($item) => $item['amount'],
                array_filter($expenditure, fn ($item) => $item['category'] === 'Essential')
            )),
            'lifestyle_expenses' => array_sum(array_map(
                fn ($item) => $item['amount'],
                array_filter($expenditure, fn ($item) => $item['category'] === 'Lifestyle')
            )),
            'debt_servicing' => array_sum(array_map(
                fn ($item) => $item['amount'],
                array_filter($expenditure, fn ($item) => $item['category'] === 'Debt Servicing')
            )),
            'taxes' => 0, // Placeholder for tax calculation
            // Summary values
            'total_income' => round($totalIncome, 2),
            'total_expenditure' => round($totalExpenditure, 2),
            'net_surplus_deficit' => round($netSurplusDeficit, 2),
            'status' => $netSurplusDeficit >= 0 ? 'Surplus' : 'Deficit',
        ];
    }

    /**
     * Calculate income sources
     * In production, this would query income tables
     */
    private function calculateIncome(int $userId, Carbon $start, Carbon $end): array
    {
        // Placeholder structure - in production, query actual income records
        // This would come from an income_sources table

        return [
            [
                'category' => 'Employment Income',
                'description' => 'Salary',
                'amount' => 0, // To be populated from income table
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Investment Income',
                'description' => 'Dividends',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Investment Income',
                'description' => 'Interest',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Rental Income',
                'description' => 'Property rental',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Pension Income',
                'description' => 'State/Private pensions',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
        ];
    }

    /**
     * Calculate expenditure
     * In production, this would query expenditure tables
     */
    private function calculateExpenditure(int $userId, Carbon $start, Carbon $end): array
    {
        // Get debt servicing costs from liabilities
        $liabilities = Liability::where('user_id', $userId)->get();
        $annualDebtServicing = $liabilities->sum('monthly_payment') * 12;

        // Placeholder structure - in production, query actual expenditure records
        return [
            [
                'category' => 'Essential',
                'description' => 'Housing (mortgage/rent)',
                'amount' => 0, // To be populated from expenditure table
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Essential',
                'description' => 'Utilities & Council Tax',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Essential',
                'description' => 'Food & Groceries',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Essential',
                'description' => 'Transport',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Lifestyle',
                'description' => 'Entertainment & Leisure',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Lifestyle',
                'description' => 'Holidays',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Debt Servicing',
                'description' => 'Loan/Credit Card payments',
                'amount' => round($annualDebtServicing, 2),
                'frequency' => 'Annual',
            ],
            [
                'category' => 'Savings & Investments',
                'description' => 'Regular savings contributions',
                'amount' => 0,
                'frequency' => 'Annual',
            ],
        ];
    }

    /**
     * Project cash flow over multiple years
     */
    public function projectCashFlow(int $userId, int $years = 5): array
    {
        $currentYear = (int) date('Y');
        $projections = [];

        for ($i = 0; $i < $years; $i++) {
            $taxYear = $currentYear + $i;
            $pl = $this->createPersonalPL($userId, (string) $taxYear);

            // Apply inflation assumptions
            $assumptions = $this->taxConfig->getAssumptions();
            $inflationRate = $assumptions['inflation_rate'] ?? 0.02;

            if ($i > 0) {
                $inflationMultiplier = pow(1 + $inflationRate, $i);

                // Inflate income and expenditure
                $pl['income']['total'] *= $inflationMultiplier;
                $pl['expenditure']['total'] *= $inflationMultiplier;
                $pl['net_surplus_deficit'] = $pl['income']['total'] - $pl['expenditure']['total'];
            }

            $projections[] = [
                'year' => $taxYear,
                'tax_year' => $pl['tax_year'],
                'income' => round($pl['income']['total'], 2),
                'expenditure' => round($pl['expenditure']['total'], 2),
                'net_cash_flow' => round($pl['net_surplus_deficit'], 2),
                'cumulative_cash_flow' => 0, // Will be calculated below
            ];
        }

        // Calculate cumulative cash flow
        $cumulative = 0;
        foreach ($projections as &$projection) {
            $cumulative += $projection['net_cash_flow'];
            $projection['cumulative_cash_flow'] = round($cumulative, 2);
        }

        return [
            'projection_years' => $years,
            'projections' => $projections,
            'summary' => [
                'total_income' => round(array_sum(array_column($projections, 'income')), 2),
                'total_expenditure' => round(array_sum(array_column($projections, 'expenditure')), 2),
                'total_net_cash_flow' => round(array_sum(array_column($projections, 'net_cash_flow')), 2),
            ],
        ];
    }

    /**
     * Identify cash flow issues
     */
    public function identifyCashFlowIssues(array $projection): array
    {
        $issues = [];
        $projections = $projection['projections'];

        // Check for consecutive deficit years
        $consecutiveDeficits = 0;
        foreach ($projections as $year) {
            if ($year['net_cash_flow'] < 0) {
                $consecutiveDeficits++;

                if ($consecutiveDeficits >= 2) {
                    $issues[] = [
                        'type' => 'Consecutive Deficits',
                        'severity' => 'High',
                        'description' => "{$consecutiveDeficits} consecutive years of cash flow deficit",
                        'recommendation' => 'Review expenditure or increase income to achieve positive cash flow',
                    ];
                    break;
                }
            } else {
                $consecutiveDeficits = 0;
            }
        }

        // Check for large single-year deficits
        foreach ($projections as $year) {
            if ($year['net_cash_flow'] < -10000) {
                $issues[] = [
                    'type' => 'Large Deficit Year',
                    'severity' => 'Medium',
                    'year' => $year['year'],
                    'deficit' => abs($year['net_cash_flow']),
                    'description' => "Year {$year['year']} shows deficit of £".number_format(abs($year['net_cash_flow']), 2),
                    'recommendation' => 'Plan for this deficit with savings or temporary income increase',
                ];
            }
        }

        // Check cumulative negative cash flow
        $lastYear = end($projections);
        if ($lastYear['cumulative_cash_flow'] < 0) {
            $issues[] = [
                'type' => 'Negative Cumulative Cash Flow',
                'severity' => 'High',
                'cumulative_deficit' => abs($lastYear['cumulative_cash_flow']),
                'description' => 'Cumulative cash flow deficit of £'.number_format(abs($lastYear['cumulative_cash_flow']), 2),
                'recommendation' => 'Significant restructuring needed - reduce expenditure or increase income substantially',
            ];
        }

        // Check for declining surplus
        $surplusYears = array_filter($projections, fn ($p) => $p['net_cash_flow'] > 0);
        if (count($surplusYears) >= 2) {
            $firstSurplus = reset($surplusYears);
            $lastSurplus = end($surplusYears);

            if ($lastSurplus['net_cash_flow'] < $firstSurplus['net_cash_flow'] * 0.5) {
                $issues[] = [
                    'type' => 'Declining Surplus',
                    'severity' => 'Medium',
                    'description' => 'Cash flow surplus declining significantly over projection period',
                    'recommendation' => 'Review expenditure growth and ensure income keeps pace with inflation',
                ];
            }
        }

        return [
            'has_issues' => count($issues) > 0,
            'issue_count' => count($issues),
            'issues' => $issues,
            'overall_health' => $this->assessCashFlowHealth($projection),
        ];
    }

    /**
     * Assess overall cash flow health
     */
    private function assessCashFlowHealth(array $projection): array
    {
        $projections = $projection['projections'];
        $surplusYears = count(array_filter($projections, fn ($p) => $p['net_cash_flow'] > 0));
        $totalYears = count($projections);

        $surplusPercentage = $totalYears > 0 ? ($surplusYears / $totalYears) * 100 : 0;

        $health = match (true) {
            $surplusPercentage >= 80 => [
                'status' => 'Healthy',
                'score' => 90,
                'description' => 'Strong positive cash flow throughout projection period',
            ],
            $surplusPercentage >= 60 => [
                'status' => 'Good',
                'score' => 75,
                'description' => 'Mostly positive cash flow with some deficit years',
            ],
            $surplusPercentage >= 40 => [
                'status' => 'Fair',
                'score' => 60,
                'description' => 'Mixed cash flow - roughly balanced between surplus and deficit',
            ],
            $surplusPercentage >= 20 => [
                'status' => 'Concerning',
                'score' => 40,
                'description' => 'More deficit years than surplus - action needed',
            ],
            default => [
                'status' => 'Poor',
                'score' => 20,
                'description' => 'Consistent cash flow deficits - immediate action required',
            ],
        };

        return $health;
    }

    /**
     * Calculate discretionary income (available for gifting)
     */
    public function calculateDiscretionaryIncome(int $userId, string $taxYear): array
    {
        $pl = $this->createPersonalPL($userId, $taxYear);

        $totalIncome = $pl['income']['total'];
        $essentialExpenditure = array_sum(
            array_column(
                array_filter($pl['expenditure']['items'], fn ($item) => $item['category'] === 'Essential'),
                'amount'
            )
        );

        $discretionaryIncome = $totalIncome - $essentialExpenditure;

        return [
            'tax_year' => $pl['tax_year'],
            'total_income' => round($totalIncome, 2),
            'essential_expenditure' => round($essentialExpenditure, 2),
            'discretionary_income' => round($discretionaryIncome, 2),
            'discretionary_percentage' => $totalIncome > 0
                ? round(($discretionaryIncome / $totalIncome) * 100, 2)
                : 0,
            'available_for_gifting' => round(max(0, $discretionaryIncome * 0.25), 2), // Conservative 25% suggestion
        ];
    }
}
