<?php

declare(strict_types=1);

namespace App\Services\NetWorth;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Shared\CrossModuleAssetAggregator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class NetWorthService
{
    public function __construct(
        private CrossModuleAssetAggregator $assetAggregator
    ) {}

    /**
     * Calculate net worth for a user
     */
    public function calculateNetWorth(User $user, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::now();
        $userId = $user->id;

        // Use CrossModuleAssetAggregator for cross-module assets
        $assetTotals = $this->assetAggregator->getAssetTotals($userId);
        $propertyValue = $assetTotals['property'];
        $investmentValue = $assetTotals['investment'];
        $cashValue = $assetTotals['cash'];

        // Calculate Estate-specific assets
        $businessValue = $this->calculateBusinessValue($userId);
        $chattelValue = $this->calculateChattelValue($userId);

        // Calculate pension values
        $pensionValue = $this->calculatePensionValue($userId);

        $totalAssets = $propertyValue + $investmentValue + $cashValue + $pensionValue + $businessValue + $chattelValue;

        // Use CrossModuleAssetAggregator for mortgages
        $mortgages = $this->assetAggregator->calculateMortgageTotal($userId);

        // Calculate all liabilities breakdown
        $liabilitiesBreakdown = $this->calculateLiabilitiesBreakdown($userId);

        // Add mortgages to the breakdown
        $liabilitiesBreakdown['mortgages'] = $mortgages;

        $totalLiabilities = array_sum($liabilitiesBreakdown);

        // Calculate net worth
        $netWorth = $totalAssets - $totalLiabilities;

        return [
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'net_worth' => round($netWorth, 2),
            'as_of_date' => $asOfDate->toDateString(),
            'breakdown' => [
                'pensions' => round($pensionValue, 2),
                'property' => round($propertyValue, 2),
                'investments' => round($investmentValue, 2),
                'cash' => round($cashValue, 2),
                'business' => round($businessValue, 2),
                'chattels' => round($chattelValue, 2),
            ],
            'liabilities_breakdown' => [
                'mortgages' => round($liabilitiesBreakdown['mortgages'], 2),
                'loans' => round($liabilitiesBreakdown['loans'], 2),
                'credit_cards' => round($liabilitiesBreakdown['credit_cards'], 2),
                'other' => round($liabilitiesBreakdown['other'], 2),
            ],
        ];
    }

    /**
     * Calculate total business value
     *
     * IMPORTANT: current_valuation is ALREADY stored as the user's share in the database
     * (divided by ownership_percentage when saving). No need to multiply again.
     */
    private function calculateBusinessValue(int $userId): float
    {
        return (float) BusinessInterest::where('user_id', $userId)
            ->sum('current_valuation');
    }

    /**
     * Calculate total chattel value
     *
     * IMPORTANT: current_value is ALREADY stored as the user's share in the database
     * (divided by ownership_percentage when saving). No need to multiply again.
     */
    private function calculateChattelValue(int $userId): float
    {
        return (float) Chattel::where('user_id', $userId)
            ->sum('current_value');
    }

    /**
     * Calculate liabilities breakdown by type
     *
     * Returns an array with keys: loans, credit_cards, other
     * (mortgages are calculated separately via CrossModuleAssetAggregator)
     *
     * Each user has their own liability records. For joint liabilities,
     * reciprocal records exist with each owner's share stored in current_balance.
     */
    private function calculateLiabilitiesBreakdown(int $userId): array
    {
        // Get all liabilities from the liabilities table for this user
        $liabilities = Liability::where('user_id', $userId)->get();

        $breakdown = [
            'mortgages' => 0.0, // Will be filled with property mortgages
            'loans' => 0.0,
            'credit_cards' => 0.0,
            'other' => 0.0,
        ];

        foreach ($liabilities as $liability) {
            $balance = $liability->current_balance ?? 0;

            // Map granular liability types to display categories
            switch ($liability->liability_type) {
                // Loan types - all map to 'loans'
                case 'loan':
                case 'secured_loan':
                case 'personal_loan':
                case 'hire_purchase':
                case 'student_loan':
                case 'business_loan':
                    $breakdown['loans'] += $balance;
                    break;

                    // Credit card debt
                case 'credit_card':
                    $breakdown['credit_cards'] += $balance;
                    break;

                    // Mortgages - skip as they're tracked via property mortgages
                case 'mortgage':
                    // Skip mortgages from liabilities table - they're tracked via property mortgages
                    break;

                    // Other liabilities
                case 'overdraft':
                case 'other':
                default:
                    $breakdown['other'] += $balance;
                    break;
            }
        }

        return $breakdown;
    }

    /**
     * Calculate total pension value (DC + DB capital equivalent)
     * Note: State Pension is excluded as it cannot be accessed as a capital sum
     */
    private function calculatePensionValue(int $userId): float
    {
        // DC Pensions - use current fund value
        $dcValue = DCPension::where('user_id', $userId)
            ->sum('current_fund_value');

        // DB Pensions - calculate capital equivalent
        // Use 20x annual pension as a rough capital value (standard actuarial multiplier)
        $dbValue = DBPension::where('user_id', $userId)
            ->get()
            ->sum(function ($dbPension) {
                $annualPension = $dbPension->accrued_annual_pension ?? 0;
                $lumpSum = $dbPension->lump_sum_entitlement ?? 0;

                // Capital value = (Annual pension × 20) + Lump sum
                return ($annualPension * 20) + $lumpSum;
            });

        // Note: State Pension is NOT included in Net Worth calculation
        // as it is not accessible as a capital sum

        return (float) ($dcValue + $dbValue);
    }

    /**
     * Get net worth trend over specified number of months
     */
    public function getNetWorthTrend(User $user, int $months = 12): array
    {
        $trend = [];
        $currentDate = Carbon::now();

        // For now, generate current net worth
        // In production, this would pull from historical snapshots
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $currentDate->copy()->subMonths($i);

            // Calculate net worth for this month
            // In production, this would come from stored snapshots
            $netWorthData = $this->calculateNetWorth($user, $date);

            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'month' => $date->format('M Y'),
                'net_worth' => $netWorthData['net_worth'],
            ];
        }

        return $trend;
    }

    /**
     * Get asset breakdown with percentages
     */
    public function getAssetBreakdown(User $user): array
    {
        $netWorth = $this->calculateNetWorth($user);
        $breakdown = $netWorth['breakdown'];
        $totalAssets = $netWorth['total_assets'];

        $percentages = [];
        foreach ($breakdown as $type => $value) {
            if ($totalAssets > 0) {
                $percentages[$type] = [
                    'value' => $value,
                    'percentage' => round(($value / $totalAssets) * 100, 2),
                ];
            } else {
                $percentages[$type] = [
                    'value' => 0,
                    'percentage' => 0,
                ];
            }
        }

        return $percentages;
    }

    /**
     * Get assets summary with counts and totals
     */
    public function getAssetsSummary(User $user): array
    {
        $userId = $user->id;

        // Use CrossModuleAssetAggregator for cross-module asset breakdowns
        $breakdown = $this->assetAggregator->getAssetBreakdown($userId);

        // Calculate pension counts
        $dcCount = DCPension::where('user_id', $userId)->count();
        $dbCount = DBPension::where('user_id', $userId)->count();
        $stateCount = StatePension::where('user_id', $userId)->count();
        $pensionCount = $dcCount + $dbCount + $stateCount;

        return [
            'pensions' => [
                'count' => $pensionCount,
                'total_value' => $this->calculatePensionValue($userId),
                'breakdown' => [
                    'dc' => $dcCount,
                    'db' => $dbCount,
                    'state' => $stateCount,
                ],
            ],
            'property' => [
                'count' => $breakdown['property']['count'],
                'total_value' => $breakdown['property']['total'],
            ],
            'investments' => [
                'count' => $breakdown['investment']['count'],
                'total_value' => $breakdown['investment']['total'],
            ],
            'cash' => [
                'count' => $breakdown['cash']['count'],
                'total_value' => $breakdown['cash']['total'],
            ],
            'business' => [
                'count' => BusinessInterest::where('user_id', $userId)->count(),
                'total_value' => $this->calculateBusinessValue($userId),
            ],
            'chattels' => [
                'count' => Chattel::where('user_id', $userId)->count(),
                'total_value' => $this->calculateChattelValue($userId),
            ],
        ];
    }

    /**
     * Get joint assets for a user
     */
    public function getJointAssets(User $user): array
    {
        $userId = $user->id;
        $jointAssets = [];

        // Get joint properties
        $properties = Property::where('user_id', $userId)
            ->where('ownership_type', 'joint')
            ->get()
            ->map(function ($property) {
                return [
                    'type' => 'property',
                    'id' => $property->id,
                    'description' => $property->address_line_1,
                    'value' => $property->current_value,
                    'ownership_percentage' => $property->ownership_percentage,
                    'co_owner' => null, // Co-owner tracking not in schema
                ];
            });

        // Get joint investments
        $investments = InvestmentAccount::where('user_id', $userId)
            ->where('ownership_type', 'joint')
            ->get()
            ->map(function ($investment) {
                return [
                    'type' => 'investment',
                    'id' => $investment->id,
                    'description' => $investment->provider.' - '.$investment->account_type,
                    'value' => $investment->current_value,
                    'ownership_percentage' => $investment->ownership_percentage,
                    'co_owner' => null, // Co-owner tracking not in schema
                ];
            });

        // Get joint savings accounts
        // Note: ownership_type validation added to StoreSavingsAccountRequest
        // Implementation pending: Query SavingsAccount model with ownership_type filter
        $cashAccounts = collect([]);

        // Get joint businesses
        $businesses = BusinessInterest::where('user_id', $userId)
            ->where('ownership_type', 'joint')
            ->get()
            ->map(function ($business) {
                return [
                    'type' => 'business',
                    'id' => $business->id,
                    'description' => $business->business_name,
                    'value' => $business->current_valuation,
                    'ownership_percentage' => $business->ownership_percentage,
                    'co_owner' => null, // Co-owner tracking not in schema
                ];
            });

        // Get joint chattels
        $chattels = Chattel::where('user_id', $userId)
            ->where('ownership_type', 'joint')
            ->get()
            ->map(function ($chattel) {
                return [
                    'type' => 'chattel',
                    'id' => $chattel->id,
                    'description' => $chattel->name,
                    'value' => $chattel->current_value,
                    'ownership_percentage' => $chattel->ownership_percentage,
                    'co_owner' => null, // Co-owner tracking not in schema
                ];
            });

        return array_merge(
            $properties->toArray(),
            $investments->toArray(),
            $cashAccounts->toArray(),
            $businesses->toArray(),
            $chattels->toArray()
        );
    }

    /**
     * Get cached net worth or calculate and cache
     */
    public function getCachedNetWorth(User $user): array
    {
        $cacheKey = "net_worth:user_{$user->id}:date_".Carbon::now()->toDateString();

        return Cache::remember($cacheKey, 1800, function () use ($user) {
            return $this->calculateNetWorth($user);
        });
    }

    /**
     * Invalidate net worth cache for a user
     */
    public function invalidateCache(int $userId): void
    {
        $cacheKey = "net_worth:user_{$userId}:date_".Carbon::now()->toDateString();
        Cache::forget($cacheKey);
    }
}
