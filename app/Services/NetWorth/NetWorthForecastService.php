<?php

declare(strict_types=1);

namespace App\Services\NetWorth;

use App\Models\User;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\LiabilityStore;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;

final class NetWorthForecastService
{
    private const ASSET_CATEGORIES = [
        'property',
        'investments',
        'pensions',
        'cash',
        'business',
        'valuables',
    ];

    private const LIABILITY_CATEGORIES = [
        'mortgages',
        'other_liabilities',
    ];

    public function __construct(
        private readonly NetWorthService $netWorthService,
        private readonly NetWorthForecastAssumptionService $assumptionService,
        private readonly SavingsStore $savingsStore,
        private readonly InvestmentAccountStore $investmentStore,
        private readonly PensionStore $pensionStore,
        private readonly MortgageStore $mortgageStore,
        private readonly LiabilityStore $liabilityStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forecast(User $user, int $years = 30): array
    {
        $years = min(50, max(1, $years));
        $recorded = $this->netWorthService->calculateNetWorth($user);
        $assumptions = $this->assumptionService->forUser($user);
        [$cashFlows, $warnings] = $this->knownAnnualCashFlows($user);
        $current = [
            'assets' => [
                'property' => (float) ($recorded['breakdown']['property'] ?? 0),
                'investments' => (float) ($recorded['breakdown']['investments'] ?? 0),
                'pensions' => (float) ($recorded['breakdown']['pensions'] ?? 0),
                'cash' => (float) ($recorded['breakdown']['cash'] ?? 0),
                'business' => (float) ($recorded['breakdown']['business'] ?? 0),
                'valuables' => (float) ($recorded['breakdown']['chattels'] ?? 0),
            ],
            'liabilities' => [
                'mortgages' => (float) ($recorded['liabilities_breakdown']['mortgages'] ?? 0),
                'other_liabilities' => array_sum([
                    (float) ($recorded['liabilities_breakdown']['loans'] ?? 0),
                    (float) ($recorded['liabilities_breakdown']['credit_cards'] ?? 0),
                    (float) ($recorded['liabilities_breakdown']['other'] ?? 0),
                ]),
            ],
        ];
        $current['total_assets'] = round(array_sum($current['assets']), 2);
        $current['total_liabilities'] = round(array_sum($current['liabilities']), 2);
        $current['net_worth'] = round($current['total_assets'] - $current['total_liabilities'], 2);

        if ($current['liabilities']['other_liabilities'] > 0
            && $cashFlows['annual_repayments']['other_liabilities'] === 0.0) {
            $warnings[] = 'Other-liability principal repayments are not projected because no canonical principal repayment is recorded.';
        }

        return [
            'contract_version' => 'net_worth_forecast_v1',
            'recorded_as_of' => (string) $recorded['as_of_date'],
            'current' => $current,
            'points' => self::projectPoints(
                $current,
                $assumptions,
                $cashFlows,
                $years,
                now()->year,
            ),
            'assumptions' => $assumptions,
            'cash_flows' => $cashFlows,
            'warnings' => array_values(array_unique($warnings)),
            'methodology' => [
                'asset_order' => 'growth_then_recorded_contribution',
                'liability_order' => 'rate_then_recorded_principal_repayment',
                'forecast_points_written_to_recorded_history' => false,
                'maximum_horizon_years' => 50,
            ],
        ];
    }

    /**
     * @param  array{assets: array<string, float>, liabilities: array<string, float>}  $current
     * @param  array<string, array{rate_percent: float|int}>  $assumptions
     * @param  array{
     *     annual_contributions: array<string, float>,
     *     annual_repayments: array<string, float>
     * }  $cashFlows
     * @return array<int, array<string, mixed>>
     */
    public static function projectPoints(
        array $current,
        array $assumptions,
        array $cashFlows,
        int $years,
        int $startYear,
    ): array {
        $years = max(0, $years);
        $assets = self::normaliseCategories($current['assets'] ?? [], self::ASSET_CATEGORIES);
        $liabilities = self::normaliseCategories($current['liabilities'] ?? [], self::LIABILITY_CATEGORIES);
        $points = [self::point(0, $startYear, $assets, $liabilities, 'recorded')];

        for ($year = 1; $year <= $years; $year++) {
            foreach (self::ASSET_CATEGORIES as $category) {
                $rate = ((float) ($assumptions[$category]['rate_percent'] ?? 0)) / 100;
                $contribution = (float) ($cashFlows['annual_contributions'][$category] ?? 0);
                $assets[$category] = round(max(0, ($assets[$category] * (1 + $rate)) + $contribution), 2);
            }

            foreach (self::LIABILITY_CATEGORIES as $category) {
                $rate = ((float) ($assumptions[$category]['rate_percent'] ?? 0)) / 100;
                $repayment = (float) ($cashFlows['annual_repayments'][$category] ?? 0);
                $liabilities[$category] = round(max(0, ($liabilities[$category] * (1 + $rate)) - $repayment), 2);
            }

            $points[] = self::point($year, $startYear + $year, $assets, $liabilities, 'projected');
        }

        return $points;
    }

    /**
     * @return array{0: array<string, array<string, float>>, 1: array<int, string>}
     */
    private function knownAnnualCashFlows(User $user): array
    {
        $cashFlows = [
            'annual_contributions' => [
                'cash' => 0.0,
                'investments' => 0.0,
                'pensions' => 0.0,
            ],
            'annual_repayments' => [
                'mortgages' => 0.0,
                'other_liabilities' => 0.0,
            ],
        ];
        $warnings = [];

        foreach ($this->savingsStore->forUser($user) as $account) {
            $amount = (float) ($account->regular_contribution_amount ?? 0);
            $cashFlows['annual_contributions']['cash'] += $amount
                * self::annualFrequencyMultiplier($account->contribution_frequency)
                * self::ownershipMultiplier($account, (int) $user->id);
        }

        foreach ($this->investmentStore->forUser($user) as $account) {
            $amount = (float) ($account->monthly_contribution_amount ?? 0);
            $cashFlows['annual_contributions']['investments'] += $amount
                * self::annualFrequencyMultiplier($account->contribution_frequency)
                * self::ownershipMultiplier($account, (int) $user->id);
        }

        foreach ($this->pensionStore->forUserByType($user, 'dc') as $pension) {
            $annualContribution = $pension->annual_contribution_gbp;
            if ($annualContribution === null) {
                $annualContribution = self::annualPensionContribution($pension);
            }
            $cashFlows['annual_contributions']['pensions'] += (float) $annualContribution;
        }

        foreach ($this->mortgageStore->forUser($user) as $mortgage) {
            $monthlyPayment = (float) ($mortgage->monthly_payment ?? 0);
            if ($monthlyPayment <= 0) {
                continue;
            }

            if (($mortgage->mortgage_type ?? null) === 'interest_only') {
                continue;
            }

            if ($mortgage->monthly_interest_portion === null) {
                $warnings[] = 'A mortgage principal repayment is not projected because its monthly interest portion is not recorded.';

                continue;
            }

            $monthlyInterest = (float) $mortgage->monthly_interest_portion;
            $monthlyPrincipal = max(0, $monthlyPayment - $monthlyInterest);
            $cashFlows['annual_repayments']['mortgages'] += $monthlyPrincipal
                * 12
                * self::ownershipMultiplier($mortgage, (int) $user->id);
        }

        foreach ($this->liabilityStore->forUser($user) as $liability) {
            if ((float) ($liability->monthly_payment ?? 0) > 0) {
                $warnings[] = 'A liability payment is recorded, but principal repayment is excluded because the principal portion is not recorded.';
            }
        }

        foreach ($cashFlows as $type => $values) {
            foreach ($values as $category => $value) {
                $cashFlows[$type][$category] = round($value, 2);
            }
        }

        return [$cashFlows, $warnings];
    }

    private static function annualPensionContribution(object $pension): float
    {
        $salary = (float) ($pension->annual_salary ?? 0);
        $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
        $employerPercent = (float) ($pension->employer_contribution_percent ?? 0);

        if ($salary > 0 && ($employeePercent + $employerPercent) > 0) {
            return $salary * (($employeePercent + $employerPercent) / 100);
        }

        return (float) ($pension->monthly_contribution_amount ?? 0) * 12;
    }

    private static function annualFrequencyMultiplier(?string $frequency): int
    {
        return match ($frequency) {
            'quarterly' => 4,
            'annually' => 1,
            default => 12,
        };
    }

    private static function ownershipMultiplier(object $record, int $userId): float
    {
        $ownershipType = (string) ($record->ownership_type ?? 'individual');
        if (! in_array($ownershipType, ['joint', 'tenants_in_common'], true)) {
            return (int) $record->user_id === $userId ? 1.0 : 0.0;
        }

        $primaryShare = ((float) ($record->ownership_percentage ?? 50)) / 100;

        if ((int) $record->user_id === $userId) {
            return $primaryShare;
        }

        return (int) ($record->joint_owner_id ?? 0) === $userId
            ? 1 - $primaryShare
            : 0.0;
    }

    /**
     * @param  array<string, float|int>  $values
     * @param  array<int, string>  $categories
     * @return array<string, float>
     */
    private static function normaliseCategories(array $values, array $categories): array
    {
        $normalised = [];
        foreach ($categories as $category) {
            $normalised[$category] = round((float) ($values[$category] ?? 0), 2);
        }

        return $normalised;
    }

    /**
     * @param  array<string, float>  $assets
     * @param  array<string, float>  $liabilities
     * @return array<string, mixed>
     */
    private static function point(
        int $year,
        int $calendarYear,
        array $assets,
        array $liabilities,
        string $source,
    ): array {
        $totalAssets = round(array_sum($assets), 2);
        $totalLiabilities = round(array_sum($liabilities), 2);

        return [
            'year' => $year,
            'calendar_year' => $calendarYear,
            'categories' => $assets,
            'liabilities' => $liabilities,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'net_worth' => round($totalAssets - $totalLiabilities, 2),
            'source' => $source,
        ];
    }
}
