<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\User;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
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
 * - Chattel values (from Estate module)
 * - Business interest values (from Business module)
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

    public function __construct(
        private readonly SavingsStore $savingsStore,
        private readonly PropertyStore $propertyStore,
        private readonly MortgageStore $mortgageStore,
    ) {}

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

        // Get chattels from the Estate module. W-0138: these were absent, so every
        // consumer of this collection — including the estate net worth the /m estate
        // screen reads — presented an estate with the user's valuables missing.
        $chattels = $this->getChattelAssets($userId);
        $allAssets = $allAssets->concat($chattels);

        // Business interests, absent for the same reason and found by the W-0138
        // census. Invisible to the peak_earners persona, which has none.
        $business = $this->getBusinessAssets($userId);
        $allAssets = $allAssets->concat($business);

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
        $user = User::findOrFail($userId);

        return $this->propertyStore->forUserWithJointOwner($user)
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
        $user = User::findOrFail($userId);

        return $this->savingsStore->forUser($user)
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
     * Get chattel assets for a user.
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * Calculate user's share based on ownership_percentage.
     */
    public function getChattelAssets(int $userId): Collection
    {
        return Chattel::forUserOrJoint($userId)
            ->get()
            ->map(function ($chattel) use ($userId) {
                $userShare = $this->calculateUserShare($chattel, $userId);
                $fullValue = $this->getFullValue($chattel);

                return (object) [
                    'asset_type' => 'chattel',
                    'asset_name' => $chattel->name,
                    'current_value' => $userShare,
                    'full_value' => $fullValue,
                    'ownership_type' => $chattel->ownership_type ?? 'individual',
                    'ownership_percentage' => $chattel->ownership_percentage ?? 100,
                    'is_primary_owner' => $this->isPrimaryOwner($chattel, $userId),
                    'is_shared' => $this->isSharedOwnership($chattel),
                    'is_iht_exempt' => false,
                    'source_id' => $chattel->id,
                    'source_model' => 'Chattel',
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
            'chattel' => $this->calculateChattelTotal($userId),
            'business' => $this->calculateBusinessTotal($userId),
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
        $user = User::findOrFail($userId);

        return $this->propertyStore->forUserWithJointOwner($user)
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
        $user = User::findOrFail($userId);

        return $this->savingsStore->forUser($user)
            ->sum(fn ($account) => $this->calculateUserShare($account, $userId));
    }

    /**
     * Get business interest assets for a user.
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * CalculatesOwnershipShare already knows business interests are different —
     * ownership_percentage is a SHAREHOLDING and applies even to individually
     * owned records, where for every other class individual means 100%.
     *
     * `is_iht_exempt` is deliberately absent. Business Property Relief depends on
     * bpr_eligible, trading status and two years' ownership; this collection is
     * asset VALUE, nothing here reads the flag, and relief belongs to the
     * Inheritance Tax path (EstateAssetAggregatorService::gatherUserAssets), which
     * models it already. Asserting a flat `false` would state something untrue of
     * a qualifying trading business.
     */
    public function getBusinessAssets(int $userId): Collection
    {
        return BusinessInterest::forUserOrJoint($userId)
            ->get()
            ->map(function ($business) use ($userId) {
                $userShare = $this->calculateUserShare($business, $userId);

                return (object) [
                    'asset_type' => 'business',
                    'asset_name' => $business->business_name,
                    'current_value' => $userShare,
                    'full_value' => (float) $business->current_valuation,
                    'ownership_type' => $business->ownership_type ?? 'individual',
                    'ownership_percentage' => $business->ownership_percentage ?? 100,
                    'is_primary_owner' => $this->isPrimaryOwner($business, $userId),
                    'is_shared' => $this->isSharedOwnership($business),
                    'source_id' => $business->id,
                    'source_model' => 'BusinessInterest',
                ];
            });
    }

    /**
     * Calculate total business interest value (user's share).
     *
     * Single-record pattern: Sum user's share of all business interests where
     * user is owner OR joint_owner.
     */
    public function calculateBusinessTotal(int $userId): float
    {
        return BusinessInterest::forUserOrJoint($userId)
            ->get()
            ->sum(fn ($business) => $this->calculateUserShare($business, $userId));
    }

    /**
     * Calculate total chattel value (user's share).
     *
     * Single-record pattern: Sum user's share of all chattels where user
     * is owner OR joint_owner.
     */
    public function calculateChattelTotal(int $userId): float
    {
        return Chattel::forUserOrJoint($userId)
            ->get()
            ->sum(fn ($chattel) => $this->calculateUserShare($chattel, $userId));
    }

    /**
     * Get all mortgages for a user.
     *
     * Two-leg pattern (matches Pass 4 Property sibling):
     *   1. Direct mortgages where user is owner OR joint_owner (joint-aware via MortgageStore).
     *   2. Mortgages on the user's properties that are NOT owned by the user — the
     *      cross-link case where a shared property has a mortgage held by a different
     *      party (e.g. one spouse's mortgage on a jointly-owned home). This leg is
     *      INTENTIONALLY unscoped by mortgage owner and stays as a raw Eloquent read
     *      because MortgageStore's user-scoped contract cannot express it.
     *      Reads are not policed by MortgageStoreBoundaryTest (writes only).
     */
    public function getMortgages(int $userId): Collection
    {
        $user = User::findOrFail($userId);
        $directMortgages = $this->mortgageStore->forUser($user);

        $propertyIds = $this->propertyStore->forUserWithJointOwner($user)->pluck('id');
        $propertyMortgages = Mortgage::whereIn('property_id', $propertyIds)
            ->whereNotIn('id', $directMortgages->pluck('id'))
            ->get();

        return $directMortgages->concat($propertyMortgages);
    }

    /**
     * Calculate total mortgage liabilities (user's share).
     *
     * Two-leg pattern — see getMortgages() docblock for rationale on the unscoped
     * cross-link leg. User-share calculation handles non-owner mortgages by
     * returning 0.0 via CalculatesOwnershipShare::calculateUserMortgageShare.
     */
    public function calculateMortgageTotal(int $userId): float
    {
        $user = User::findOrFail($userId);
        $directMortgages = $this->mortgageStore->forUser($user);

        $propertyIds = $this->propertyStore->forUserWithJointOwner($user)->pluck('id');
        $propertyMortgages = Mortgage::whereIn('property_id', $propertyIds)
            ->whereNotIn('id', $directMortgages->pluck('id'))
            ->get();

        return $directMortgages->concat($propertyMortgages)
            ->sum(fn ($mortgage) => $this->calculateUserMortgageShare($mortgage, $userId));
    }

    /**
     * What this user owes, split the way every surface presents it, at THEIR share.
     *
     * The one home for the liability side (Rule 20). Three mechanisms answered
     * this question and none of them applied the share: the profile's
     * `calculateLiabilitiesSummary` and both protection paths read
     * `forUserPrimaryOnly` / `$user->liabilities()`, which are scoped to `user_id`
     * alone. So the primary owner was charged the WHOLE of every shared debt —
     * his spouse's half of a joint mortgage, and the 60% of a tenants-in-common
     * loan belonging to a co-owner who has no account here at all (W-0187) —
     * while the joint owner was shown none of it.
     *
     * `liabilities` carries `ownership_type`, `ownership_percentage` and
     * `joint_owner_id` like every other shared record. `calculateUserShare`
     * returns 0.0 for anyone who is neither party, so a third party's share
     * reduces the user's figure without being credited to anybody.
     *
     * **Mortgage-type liability rows count as mortgages, not as "other".** They
     * are a second way to record the same debt and the profile has always
     * presented them alongside the mortgages table. Summing them into "other"
     * would have counted the same borrowing twice.
     *
     * @return array{mortgages: float, other: float, total: float}
     */
    public function calculateLiabilityTotals(int $userId): array
    {
        $liabilities = Liability::forUserOrJoint($userId)->get();

        $share = fn (Liability $liability): float => $this->calculateUserShare($liability, $userId);

        $mortgages = $this->calculateMortgageTotal($userId)
            + $liabilities->where('liability_type', 'mortgage')->sum($share);

        $other = (float) $liabilities->where('liability_type', '!=', 'mortgage')->sum($share);

        return [
            'mortgages' => round((float) $mortgages, 2),
            'other' => round($other, 2),
            'total' => round((float) $mortgages + $other, 2),
        ];
    }

    /**
     * Get asset breakdown with counts.
     *
     * Note: Count includes all assets where user is owner OR joint_owner.
     */
    public function getAssetBreakdown(int $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'property' => [
                'count' => $this->propertyStore->forUserWithJointOwner($user)->count(),
                'total' => $this->calculatePropertyTotal($userId),
            ],
            'investment' => [
                'count' => InvestmentAccount::forUserOrJoint($userId)->count(),
                'total' => $this->calculateInvestmentTotal($userId),
            ],
            'cash' => [
                'count' => $this->savingsStore->forUser($user)->count(),
                'total' => $this->calculateCashTotal($userId),
            ],
            'chattel' => [
                'count' => Chattel::forUserOrJoint($userId)->count(),
                'total' => $this->calculateChattelTotal($userId),
            ],
            'business' => [
                'count' => BusinessInterest::forUserOrJoint($userId)->count(),
                'total' => $this->calculateBusinessTotal($userId),
            ],
            'mortgages' => [
                'count' => $this->mortgageStore->forUser($user)->count(),
                'total' => $this->calculateMortgageTotal($userId),
            ],
        ];
    }
}
