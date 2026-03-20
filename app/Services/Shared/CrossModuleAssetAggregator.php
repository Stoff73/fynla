<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Support\Collection;

/**
 * Cross-Module Asset Aggregator
 *
 * Centralized service for aggregating assets and liabilities from multiple modules.
 * Eliminates duplication between NetWorthAnalyzer and NetWorthService.
 *
 * This service provides a single source of truth for:
 * - Property values (from Property module)
 * - Investment values (from Investment module)
 * - Cash/Savings values (from Savings module)
 * - Mortgage liabilities (from Property module)
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL asset value in current_value/current_balance
 * - user_id = primary owner, joint_owner_id = secondary owner
 * - ownership_percentage = primary owner's share (default 50 for joint)
 * - Query pattern: where('user_id', $id)->orWhere('joint_owner_id', $id)
 * - User's share = full_value * (percentage / 100) for primary owner
 * - User's share = full_value * ((100 - percentage) / 100) for joint owner
 */
class CrossModuleAssetAggregator
{
    use CalculatesOwnershipShare;

    /**
     * Get all cross-module assets for a user
     *
     * Returns a collection of asset objects in standardized format:
     * - asset_type: string
     * - asset_name: string
     * - current_value: float (user's share)
     * - full_value: float (total asset value)
     * - ownership_percentage: float
     * - is_primary_owner: bool
     */
    public function getAllAssets(int $userId): Collection
    {
        $allAssets = collect();

        // Get properties from Property module
        $properties = $this->getPropertyAssets($userId);
        $allAssets = $allAssets->concat($properties);

        // Get investment accounts from Investment module
        $investments = $this->getInvestmentAssets($userId);
        $allAssets = $allAssets->concat($investments);

        // Get savings/cash accounts from Savings module
        $savings = $this->getSavingsAssets($userId);
        $allAssets = $allAssets->concat($savings);

        return $allAssets;
    }

    /**
     * Get property assets for a user.
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * Calculate user's share based on ownership_percentage.
     */
    public function getPropertyAssets(int $userId): Collection
    {
        return Property::forUserOrJoint($userId)
            ->get()
            ->map(function ($property) use ($userId) {
                $userShare = $this->calculateUserShare($property, $userId);
                $fullValue = $this->getFullValue($property);

                return (object) [
                    'asset_type' => 'property',
                    'asset_name' => $property->address_line_1 ?: 'Property',
                    'current_value' => $userShare,
                    'full_value' => $fullValue,
                    'ownership_type' => $property->ownership_type ?? 'individual',
                    'ownership_percentage' => $property->ownership_percentage ?? 100,
                    'is_primary_owner' => $this->isPrimaryOwner($property, $userId),
                    'is_shared' => $this->isSharedOwnership($property),
                    'is_iht_exempt' => false,
                    'source_id' => $property->id,
                    'source_model' => 'Property',
                ];
            });
    }

    /**
     * Get investment account assets for a user.
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * Calculate user's share based on ownership_percentage.
     */
    public function getInvestmentAssets(int $userId): Collection
    {
        return InvestmentAccount::forUserOrJoint($userId)
            ->get()
            ->map(function ($account) use ($userId) {
                $userShare = $this->calculateUserShare($account, $userId);
                $fullValue = $this->getFullValue($account);

                return (object) [
                    'asset_type' => 'investment',
                    'asset_name' => $account->provider.' - '.strtoupper($account->account_type),
                    'current_value' => $userShare,
                    'full_value' => $fullValue,
                    'ownership_type' => $account->ownership_type ?? 'individual',
                    'ownership_percentage' => $account->ownership_percentage ?? 100,
                    'is_primary_owner' => $this->isPrimaryOwner($account, $userId),
                    'is_shared' => $this->isSharedOwnership($account),
                    'is_iht_exempt' => false, // ISAs are IHT taxable
                    'account_type' => $account->account_type,
                    'source_id' => $account->id,
                    'source_model' => 'InvestmentAccount',
                ];
            });
    }

    /**
     * Get savings/cash account assets for a user.
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * Calculate user's share based on ownership_percentage.
     */
    public function getSavingsAssets(int $userId): Collection
    {
        return SavingsAccount::forUserOrJoint($userId)
            ->get()
            ->map(function ($account) use ($userId) {
                $userShare = $this->calculateUserShare($account, $userId);
                $fullValue = $this->getFullValue($account);

                return (object) [
                    'asset_type' => 'cash',
                    'asset_name' => $account->institution.' - '.ucfirst($account->account_type),
                    'current_value' => $userShare,
                    'full_value' => $fullValue,
                    'ownership_type' => $account->ownership_type ?? 'individual',
                    'ownership_percentage' => $account->ownership_percentage ?? 100,
                    'is_primary_owner' => $this->isPrimaryOwner($account, $userId),
                    'is_shared' => $this->isSharedOwnership($account),
                    'is_iht_exempt' => false, // Cash ISAs are IHT taxable
                    'account_type' => $account->account_type,
                    'source_id' => $account->id,
                    'source_model' => 'SavingsAccount',
                ];
            });
    }

    /**
     * Calculate total asset values by type
     */
    public function getAssetTotals(int $userId): array
    {
        return [
            'property' => $this->calculatePropertyTotal($userId),
            'investment' => $this->calculateInvestmentTotal($userId),
            'cash' => $this->calculateCashTotal($userId),
        ];
    }

    /**
     * Calculate total property value (user's share).
     *
     * Single-record pattern: Sum user's share of all properties where user
     * is owner OR joint_owner.
     */
    public function calculatePropertyTotal(int $userId): float
    {
        return Property::forUserOrJoint($userId)
            ->get()
            ->sum(fn ($property) => $this->calculateUserShare($property, $userId));
    }

    /**
     * Calculate total investment value (user's share).
     *
     * Single-record pattern: Sum user's share of all investments where user
     * is owner OR joint_owner.
     */
    public function calculateInvestmentTotal(int $userId): float
    {
        return InvestmentAccount::forUserOrJoint($userId)
            ->get()
            ->sum(fn ($account) => $this->calculateUserShare($account, $userId));
    }

    /**
     * Calculate total cash/savings value (user's share).
     *
     * Single-record pattern: Sum user's share of all savings accounts where user
     * is owner OR joint_owner.
     */
    public function calculateCashTotal(int $userId): float
    {
        return SavingsAccount::forUserOrJoint($userId)
            ->get()
            ->sum(fn ($account) => $this->calculateUserShare($account, $userId));
    }

    /**
     * Get all mortgages for a user.
     *
     * Single-record pattern: Query mortgages where user is owner OR joint_owner.
     */
    public function getMortgages(int $userId): Collection
    {
        $directMortgages = Mortgage::forUserOrJoint($userId)->get();

        $propertyIds = Property::forUserOrJoint($userId)->pluck('id');
        $propertyMortgages = Mortgage::whereIn('property_id', $propertyIds)
            ->whereNotIn('id', $directMortgages->pluck('id'))
            ->get();

        return $directMortgages->concat($propertyMortgages);
    }

    /**
     * Calculate total mortgage liabilities (user's share).
     *
     * Single-record pattern: Sum user's share of all mortgages where user
     * is owner OR joint_owner.
     */
    public function calculateMortgageTotal(int $userId): float
    {
        $directMortgages = Mortgage::forUserOrJoint($userId)->get();

        $propertyIds = Property::forUserOrJoint($userId)->pluck('id');
        $propertyMortgages = Mortgage::whereIn('property_id', $propertyIds)
            ->whereNotIn('id', $directMortgages->pluck('id'))
            ->get();

        return $directMortgages->concat($propertyMortgages)
            ->sum(fn ($mortgage) => $this->calculateUserMortgageShare($mortgage, $userId));
    }

    /**
     * Get asset breakdown with counts.
     *
     * Note: Count includes all assets where user is owner OR joint_owner.
     */
    public function getAssetBreakdown(int $userId): array
    {
        return [
            'property' => [
                'count' => Property::forUserOrJoint($userId)->count(),
                'total' => $this->calculatePropertyTotal($userId),
            ],
            'investment' => [
                'count' => InvestmentAccount::forUserOrJoint($userId)->count(),
                'total' => $this->calculateInvestmentTotal($userId),
            ],
            'cash' => [
                'count' => SavingsAccount::forUserOrJoint($userId)->count(),
                'total' => $this->calculateCashTotal($userId),
            ],
            'mortgages' => [
                'count' => Mortgage::forUserOrJoint($userId)->count(),
                'total' => $this->calculateMortgageTotal($userId),
            ],
        ];
    }
}
