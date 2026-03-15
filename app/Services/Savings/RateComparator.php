<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\SavingsAccount;
use App\Models\SavingsMarketRate;

class RateComparator
{
    public function __construct(
        private readonly ISATracker $isaTracker
    ) {}

    /**
     * Compare account rate to market benchmarks
     *
     * @return array{account_rate: float, market_rate: float, difference: float, is_competitive: bool, category: string}
     */
    public function compareToMarketRates(SavingsAccount $account): array
    {
        $benchmarks = $this->getMarketBenchmarks();
        $accountType = $account->account_type;
        $accountRate = (float) $account->interest_rate;

        // Get appropriate benchmark based on account type and ISA status
        $marketRate = $this->getBenchmarkForAccount($account, $benchmarks);

        $difference = $accountRate - $marketRate;
        $isCompetitive = $difference >= -0.005; // Within 0.5% is considered competitive

        // Categorize the rate
        $category = match (true) {
            $difference >= 0.01 => 'Excellent', // 1%+ above market
            $difference >= 0 => 'Good', // At or above market
            $difference >= -0.01 => 'Fair', // Within 1% of market
            default => 'Poor', // More than 1% below market
        };

        return [
            'account_rate' => round($accountRate, 4),
            'market_rate' => round($marketRate, 4),
            'difference' => round($difference, 4),
            'is_competitive' => $isCompetitive,
            'category' => $category,
        ];
    }

    /**
     * Get market benchmark rates by account type from database.
     *
     * Falls back to sensible defaults if no rates are seeded.
     *
     * @return array<string, float>
     */
    public function getMarketBenchmarks(?string $taxYear = null): array
    {
        $taxYear = $taxYear ?? $this->isaTracker->getCurrentTaxYear();

        $rates = SavingsMarketRate::where('tax_year', $taxYear)
            ->pluck('rate', 'rate_key')
            ->map(fn ($rate) => (float) $rate)
            ->toArray();

        // Fall back to defaults if no rates seeded
        if (empty($rates)) {
            return [
                'easy_access' => 0.0400,
                'easy_access_isa' => 0.0400,
                'notice' => 0.0400,
                'notice_isa' => 0.0400,
                'fixed_1_year' => 0.0400,
                'fixed_1_year_isa' => 0.0400,
                'fixed_2_year' => 0.0400,
                'fixed_2_year_isa' => 0.0400,
                'fixed_3_year' => 0.0400,
                'fixed_3_year_isa' => 0.0400,
            ];
        }

        return $rates;
    }

    /**
     * Calculate potential interest difference over a year
     */
    public function calculateInterestDifference(SavingsAccount $account, float $marketRate): float
    {
        $balance = (float) $account->current_balance;
        $accountRate = (float) $account->interest_rate;

        $currentInterest = $balance * $accountRate;
        $potentialInterest = $balance * $marketRate;

        return round($potentialInterest - $currentInterest, 2);
    }

    /**
     * Get appropriate benchmark for an account
     */
    private function getBenchmarkForAccount(SavingsAccount $account, array $benchmarks): float
    {
        $accountType = $account->account_type;
        $isIsa = $account->is_isa;

        // Determine benchmark key based on account characteristics
        $benchmarkKey = match ($account->access_type) {
            'immediate' => $isIsa ? 'easy_access_isa' : 'easy_access',
            'notice' => $isIsa ? 'notice_isa' : 'notice',
            'fixed' => $this->getFixedRateBenchmark($account, $isIsa),
            default => $isIsa ? 'easy_access_isa' : 'easy_access',
        };

        return $benchmarks[$benchmarkKey] ?? 0.0400; // Default to 4% if not found
    }

    /**
     * Get benchmark for fixed-rate accounts based on term
     */
    private function getFixedRateBenchmark(SavingsAccount $account, bool $isIsa): string
    {
        if (! $account->maturity_date) {
            return $isIsa ? 'fixed_1_year_isa' : 'fixed_1_year';
        }

        $now = now();
        $maturityDate = $account->maturity_date;
        $yearsToMaturity = $now->diffInYears($maturityDate);

        return match (true) {
            $yearsToMaturity >= 3 => $isIsa ? 'fixed_3_year_isa' : 'fixed_3_year',
            $yearsToMaturity >= 2 => $isIsa ? 'fixed_2_year_isa' : 'fixed_2_year',
            default => $isIsa ? 'fixed_1_year_isa' : 'fixed_1_year',
        };
    }

    /**
     * Get institution exposure for FSCS grouping
     *
     * Groups accounts by banking licence group and calculates per-institution totals.
     * Used by FSCSAssessor for FSCS protection analysis.
     */
    public function getInstitutionExposure(\Illuminate\Support\Collection $accounts): array
    {
        $licenceGroups = config('banking_licence_groups', []);

        $grouped = $accounts->groupBy(function ($account) use ($licenceGroups) {
            $provider = strtolower(trim($account->provider ?? $account->institution ?? 'unknown'));

            foreach ($licenceGroups as $groupName => $members) {
                $members = array_map('strtolower', $members);
                if (in_array($provider, $members, true)) {
                    return $groupName;
                }
            }

            return ucfirst($provider);
        });

        return $grouped->map(function ($groupAccounts, $groupName) {
            return [
                'institution_group' => $groupName,
                'account_count' => $groupAccounts->count(),
                'total_balance' => round($groupAccounts->sum('current_balance'), 2),
                'accounts' => $groupAccounts->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->account_name,
                    'balance' => (float) $a->current_balance,
                ])->toArray(),
            ];
        })->values()->toArray();
    }
}
