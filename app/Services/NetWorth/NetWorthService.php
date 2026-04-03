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
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class NetWorthService
{
    use CalculatesOwnershipShare;

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

        // Calculate pension values (DC only - DB excluded as not accessible capital, same as State Pension)
        $pensionBreakdown = $this->calculatePensionBreakdown($userId);
        $pensionValue = $pensionBreakdown['dc'];

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
            'has_db_pensions' => $pensionBreakdown['has_db'],
            'liabilities_breakdown' => [
                'mortgages' => round($liabilitiesBreakdown['mortgages'], 2),
                'loans' => round($liabilitiesBreakdown['loans'], 2),
                'credit_cards' => round($liabilitiesBreakdown['credit_cards'], 2),
                'other' => round($liabilitiesBreakdown['other'], 2),
            ],
        ];
    }

    /**
     * Calculate total business value for user
     *
     * Single-Record Architecture:
     * - Database stores FULL business valuation in current_valuation
     * - Query includes records where user is owner OR joint_owner
     * - User's share is calculated from ownership_percentage
     */
    private function calculateBusinessValue(int $userId): float
    {
        $businesses = BusinessInterest::forUserOrJoint($userId)
            ->get();

        $total = 0.0;
        foreach ($businesses as $business) {
            $total += $this->calculateUserShare($business, $userId);
        }

        return $total;
    }

    /**
     * Calculate total chattel value for user
     *
     * Single-Record Architecture:
     * - Database stores FULL chattel value in current_value
     * - Query includes records where user is owner OR joint_owner
     * - User's share is calculated from ownership_percentage
     */
    private function calculateChattelValue(int $userId): float
    {
        $chattels = Chattel::forUserOrJoint($userId)
            ->get();

        $total = 0.0;
        foreach ($chattels as $chattel) {
            $total += $this->calculateUserShare($chattel, $userId);
        }

        return $total;
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
     * Calculate pension values split by DC and DB.
     *
     * DC pensions are included as accessible capital (fund value).
     * DB pensions are excluded from net worth (not accessible as a capital sum,
     * same rationale as State Pension). A flag is returned so the frontend
     * can display an appropriate note.
     */
    private function calculatePensionBreakdown(int $userId): array
    {
        $dcValue = (float) DCPension::where('user_id', $userId)
            ->sum('current_fund_value');

        $hasDB = DBPension::where('user_id', $userId)->exists();

        return [
            'dc' => $dcValue,
            'has_db' => $hasDB,
        ];
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
                'total_value' => $this->calculatePensionBreakdown($userId)['dc'],
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
                'count' => BusinessInterest::forUserOrJoint($userId)->count(),
                'total_value' => $this->calculateBusinessValue($userId),
            ],
            'chattels' => [
                'count' => Chattel::forUserOrJoint($userId)->count(),
                'total_value' => $this->calculateChattelValue($userId),
            ],
        ];
    }

    /**
     * Get assets summary with detailed individual account lists
     * Used for the Net Worth Overview cards
     */
    public function getAssetsSummaryWithDetails(User $user): array
    {
        $userId = $user->id;

        // Get pension items
        $dcPensions = DCPension::where('user_id', $userId)->get();
        $dbPensions = DBPension::where('user_id', $userId)->get();

        $pensionItems = [];

        foreach ($dcPensions as $pension) {
            $name = $pension->scheme_name ?: ($pension->provider.' '.$pension->pension_type);
            $pensionItems[] = [
                'id' => $pension->id,
                'type' => 'dc',
                'name' => $name,
                'provider' => $pension->provider,
                'value' => (float) $pension->current_fund_value,
            ];
        }

        foreach ($dbPensions as $pension) {
            $name = $pension->scheme_name ?: 'DB Pension';
            // Capital value = (Annual pension × 20) + Lump sum
            $capitalValue = (($pension->accrued_annual_pension ?? 0) * 20) + ($pension->lump_sum_entitlement ?? 0);
            $pensionItems[] = [
                'id' => $pension->id,
                'type' => 'db',
                'name' => $name,
                'provider' => $pension->employer,
                'value' => (float) $capitalValue,
                'annual_pension' => (float) ($pension->accrued_annual_pension ?? 0),
            ];
        }

        // Get property items
        $properties = Property::where('user_id', $userId)->get();
        $propertyItems = $properties->map(function ($property) {
            $name = $property->address_line_1 ?: $property->property_type;

            return [
                'id' => $property->id,
                'name' => $name,
                'type' => $property->property_type,
                'value' => (float) $property->current_value,
                'ownership_type' => $property->ownership_type,
            ];
        })->toArray();

        // Get investment items
        $investments = InvestmentAccount::where('user_id', $userId)->get();
        $investmentItems = $investments->map(function ($investment) {
            $name = $investment->provider;
            if ($investment->account_type) {
                $name .= ' - '.ucwords(str_replace('_', ' ', $investment->account_type));
            }

            return [
                'id' => $investment->id,
                'name' => $name,
                'account_type' => $investment->account_type,
                'provider' => $investment->provider,
                'value' => (float) $investment->current_value,
                'ownership_type' => $investment->ownership_type,
            ];
        })->toArray();

        // Get cash/savings items
        $savingsAccounts = SavingsAccount::where('user_id', $userId)->get();
        $cashItems = $savingsAccounts->map(function ($account) {
            $name = $account->institution;
            if ($account->account_type) {
                $name .= ' - '.ucwords(str_replace('_', ' ', $account->account_type));
            }

            return [
                'id' => $account->id,
                'name' => $name,
                'account_type' => $account->account_type,
                'institution' => $account->institution,
                'value' => (float) $account->current_balance,
                'is_isa' => $account->is_isa,
                'is_emergency_fund' => $account->is_emergency_fund,
            ];
        })->toArray();

        // Get business interest items (include joint-owned)
        $businesses = BusinessInterest::forUserOrJoint($userId)
            ->get();
        $businessItems = $businesses->map(function ($business) use ($userId) {
            return [
                'id' => $business->id,
                'name' => $business->business_name,
                'business_type' => $business->business_type,
                'value' => $this->calculateUserShare($business, $userId),
                'full_value' => (float) $business->current_valuation,
                'ownership_type' => $business->ownership_type,
                'ownership_percentage' => (float) ($business->ownership_percentage ?? 100),
                'annual_revenue' => (float) ($business->annual_revenue ?? 0),
                'annual_profit' => (float) ($business->annual_profit ?? 0),
                'is_primary_owner' => $business->user_id === $userId,
            ];
        })->toArray();

        // Get chattel items (include joint-owned)
        $chattels = Chattel::forUserOrJoint($userId)
            ->get();
        $chattelItems = $chattels->map(function ($chattel) use ($userId) {
            return [
                'id' => $chattel->id,
                'name' => $chattel->name,
                'chattel_type' => $chattel->chattel_type,
                'value' => $this->calculateUserShare($chattel, $userId),
                'full_value' => (float) $chattel->current_value,
                'ownership_type' => $chattel->ownership_type,
                'ownership_percentage' => (float) ($chattel->ownership_percentage ?? 100),
                'make' => $chattel->make,
                'model' => $chattel->model,
                'year' => $chattel->year,
                'registration_number' => $chattel->registration_number,
                'is_primary_owner' => $chattel->user_id === $userId,
            ];
        })->toArray();

        // Calculate totals
        $pensionTotal = array_sum(array_column($pensionItems, 'value'));
        $propertyTotal = array_sum(array_column($propertyItems, 'value'));
        $investmentTotal = array_sum(array_column($investmentItems, 'value'));
        $cashTotal = array_sum(array_column($cashItems, 'value'));
        $businessTotal = array_sum(array_column($businessItems, 'value'));
        $chattelTotal = array_sum(array_column($chattelItems, 'value'));

        return [
            'pensions' => [
                'count' => count($pensionItems),
                'total_value' => round($pensionTotal, 2),
                'items' => $pensionItems,
            ],
            'property' => [
                'count' => count($propertyItems),
                'total_value' => round($propertyTotal, 2),
                'items' => $propertyItems,
            ],
            'investments' => [
                'count' => count($investmentItems),
                'total_value' => round($investmentTotal, 2),
                'items' => $investmentItems,
            ],
            'cash' => [
                'count' => count($cashItems),
                'total_value' => round($cashTotal, 2),
                'items' => $cashItems,
            ],
            'business' => [
                'count' => count($businessItems),
                'total_value' => round($businessTotal, 2),
                'items' => $businessItems,
            ],
            'chattels' => [
                'count' => count($chattelItems),
                'total_value' => round($chattelTotal, 2),
                'items' => $chattelItems,
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
        $cashAccounts = SavingsAccount::where('user_id', $userId)
            ->where('ownership_type', 'joint')
            ->get()
            ->map(function ($account) {
                return [
                    'type' => 'savings',
                    'id' => $account->id,
                    'description' => trim(($account->institution ?? '').(' - '.($account->account_type ?? '')), ' - '),
                    'value' => (float) $account->current_balance,
                    'ownership_percentage' => (float) ($account->ownership_percentage ?? 50),
                    'co_owner' => $account->jointOwner ? $account->jointOwner->name : null,
                ];
            });

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

        return Cache::remember($cacheKey, 86400, function () use ($user) {
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
