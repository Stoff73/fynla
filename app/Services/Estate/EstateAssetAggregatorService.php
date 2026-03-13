<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Support\Collection;

/**
 * Service for aggregating estate assets and liabilities across all modules
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL asset value
 * - user_id = primary owner, joint_owner_id = secondary owner
 * - Query pattern: where('user_id', $id)->orWhere('joint_owner_id', $id)
 * - User's share calculated from full value * ownership_percentage
 *
 * IHT Considerations:
 * - Joint tenancy: Passes to survivor (may exclude from first death estate)
 * - Tenants in common: User's share included in estate
 */
class EstateAssetAggregatorService
{
    use CalculatesOwnershipShare;

    /**
     * Gather all assets for a user from all modules
     *
     * Single-record pattern: Query assets where user is owner OR joint_owner.
     * Returns a collection of standardized asset objects suitable for IHT calculations.
     */
    public function gatherUserAssets(User $user): Collection
    {
        $assets = Asset::where('user_id', $user->id)->get();

        // Investment accounts - Single-record pattern
        $investmentAccounts = InvestmentAccount::forUserOrJoint($user->id)
            ->get();
        $investmentAssets = $investmentAccounts->map(function ($account) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'investment',
                'asset_name' => $account->provider.' - '.strtoupper($account->account_type),
                'current_value' => $this->calculateUserShare($account, $user->id),
                'full_value' => (float) $account->current_value,
                'ownership_type' => $account->ownership_type ?? 'individual',
                'ownership_percentage' => $account->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($account, $user->id),
                'is_iht_exempt' => false, // ISAs are NOT IHT-exempt
            ];
        });

        // Properties - Single-record pattern
        $properties = Property::forUserOrJoint($user->id)
            ->get();
        $propertyAssets = $properties->map(function ($property) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'property',
                'asset_name' => $property->address_line_1 ?: 'Property',
                'current_value' => $this->calculateUserShare($property, $user->id),
                'full_value' => (float) $property->current_value,
                'ownership_type' => $property->ownership_type ?? 'individual',
                'ownership_percentage' => $property->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($property, $user->id),
                'property_type' => $property->property_type ?? 'unknown', // For RNRB eligibility
                'is_iht_exempt' => false,
            ];
        });

        // Savings/Cash - Single-record pattern
        $savingsAccounts = SavingsAccount::forUserOrJoint($user->id)
            ->get();
        $savingsAssets = $savingsAccounts->map(function ($account) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'cash',
                'asset_name' => $account->institution.' - '.ucfirst($account->account_type),
                'current_value' => $this->calculateUserShare($account, $user->id),
                'full_value' => (float) $account->current_balance,
                'ownership_type' => $account->ownership_type ?? 'individual',
                'ownership_percentage' => $account->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($account, $user->id),
                'is_iht_exempt' => false, // Cash ISAs are NOT IHT-exempt
            ];
        });

        // Business Interests - Single-record pattern with joint ownership support
        $businessInterests = BusinessInterest::forUserOrJoint($user->id)
            ->get();
        $businessAssets = $businessInterests->map(function ($business) use ($user) {
            // Use trait to calculate user's share based on ownership_percentage
            $userValue = $this->calculateUserShare($business, $user->id);

            // Business Property Relief (BPR): 100% relief for qualifying trading businesses
            // Requires: bpr_eligible flag AND trading status AND 2+ years ownership
            $ihtExempt = false;
            if ($business->bpr_eligible && $business->trading_status === 'trading') {
                // Check 2-year ownership rule if acquisition_date is set
                if ($business->acquisition_date) {
                    $yearsOwned = \Carbon\Carbon::parse($business->acquisition_date)->diffInYears(now());
                    $ihtExempt = $yearsOwned >= 2;
                } else {
                    // If no acquisition date set but marked BPR eligible, assume eligible
                    $ihtExempt = true;
                }
            }

            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'business',
                'asset_name' => $business->business_name,
                'current_value' => $userValue,
                'full_value' => (float) $business->current_valuation,
                'ownership_type' => $business->ownership_type ?? 'individual',
                'ownership_percentage' => $business->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($business, $user->id),
                'is_iht_exempt' => $ihtExempt, // BPR at 100% for qualifying trading businesses
                'bpr_eligible' => $business->bpr_eligible ?? false,
                'trading_status' => $business->trading_status,
            ];
        });

        // Chattels (personal property) - Single-record pattern with joint ownership support
        $chattels = Chattel::forUserOrJoint($user->id)
            ->get();
        $chattelAssets = $chattels->map(function ($chattel) use ($user) {
            // Use trait to calculate user's share based on ownership_percentage
            $userValue = $this->calculateUserShare($chattel, $user->id);

            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'chattel',
                'asset_name' => $chattel->name,
                'current_value' => $userValue,
                'full_value' => (float) $chattel->current_value,
                'ownership_type' => $chattel->ownership_type ?? 'individual',
                'ownership_percentage' => $chattel->ownership_percentage ?? 100,
                'is_primary_owner' => $this->isPrimaryOwner($chattel, $user->id),
                'is_iht_exempt' => false,
            ];
        });

        // DC Pensions (not IHT liable but needed for income projections in gifting strategy)
        $dcPensions = DCPension::where('user_id', $user->id)->get();
        $dcPensionAssets = $dcPensions->map(function ($pension) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'dc_pension',
                'asset_name' => $pension->scheme_name,
                'current_value' => $pension->current_fund_value,
                'full_value' => (float) $pension->current_fund_value,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'is_primary_owner' => true,
                'is_iht_exempt' => true, // DC pensions outside estate if beneficiary nominated
            ];
        });

        // DB Pensions (for income projections only - no transfer value in estate)
        $dbPensions = DBPension::where('user_id', $user->id)->get();
        $dbPensionAssets = $dbPensions->map(function ($pension) use ($user) {
            return (object) [
                'user_id' => $user->id,
                'asset_type' => 'db_pension',
                'asset_name' => $pension->scheme_name,
                'current_value' => 0, // DB pensions have no IHT value (die with member)
                'full_value' => 0,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'is_primary_owner' => true,
                'is_iht_exempt' => true,
                'annual_income' => $pension->expected_annual_pension ?? 0, // For income projections
            ];
        });

        return $assets
            ->concat($investmentAssets)
            ->concat($propertyAssets)
            ->concat($savingsAssets)
            ->concat($businessAssets)
            ->concat($chattelAssets)
            ->concat($dcPensionAssets)
            ->concat($dbPensionAssets);
    }

    /**
     * Calculate total liabilities for a user
     *
     * Single-record pattern: Query liabilities where user is owner OR joint_owner.
     * Calculate user's share from full value.
     */
    public function calculateUserLiabilities(User $user): float
    {
        // Get liabilities - single-record pattern
        $liabilitiesCollection = Liability::forUserOrJoint($user->id)
            ->get();
        $liabilities = $liabilitiesCollection->sum(function ($liability) use ($user) {
            // Calculate user's share using the trait
            $userShare = $this->calculateUserShare($liability, $user->id);
            \Log::info('Liability: '.($liability->institution ?? 'Unknown').' | Type: '.($liability->type ?? 'Unknown').' | User Share: £'.$userShare);

            return $userShare;
        });

        // Get mortgages - single-record pattern
        $mortgages = Mortgage::forUserOrJoint($user->id)
            ->get()
            ->sum(fn ($mortgage) => $this->calculateUserMortgageShare($mortgage, $user->id));

        return $liabilities + $mortgages;
    }

    /**
     * Get mortgages collection for a user
     *
     * Single-record pattern: Returns mortgages where user is owner OR joint_owner.
     */
    public function getUserMortgages(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)
            ->get();
    }

    /**
     * Get liabilities collection for a user
     *
     * Single-record pattern: Returns liabilities where user is owner OR joint_owner.
     */
    public function getUserLiabilities(User $user): Collection
    {
        return Liability::forUserOrJoint($user->id)
            ->get();
    }

    /**
     * Get total existing life cover for a user
     */
    public function getExistingLifeCover(User $user): float
    {
        $lifeInsurance = \App\Models\LifeInsurancePolicy::where('user_id', $user->id)
            ->sum('sum_assured');

        $criticalIllness = \App\Models\CriticalIllnessPolicy::where('user_id', $user->id)
            ->sum('sum_assured');

        return $lifeInsurance + $criticalIllness;
    }

    /**
     * Get user expenditure data
     */
    public function getUserExpenditure(User $user): array
    {
        // Try ExpenditureProfile first
        $expenditureProfile = \App\Models\ExpenditureProfile::where('user_id', $user->id)->first();
        if ($expenditureProfile) {
            return [
                'monthly_expenditure' => $expenditureProfile->total_monthly_expenditure,
                'annual_expenditure' => $expenditureProfile->total_monthly_expenditure * 12,
            ];
        }

        // Fall back to ProtectionProfile if available
        $protectionProfile = \App\Models\ProtectionProfile::where('user_id', $user->id)->first();
        if ($protectionProfile && $protectionProfile->monthly_expenditure) {
            return [
                'monthly_expenditure' => $protectionProfile->monthly_expenditure,
                'annual_expenditure' => $protectionProfile->monthly_expenditure * 12,
            ];
        }

        // No expenditure data available
        return [
            'monthly_expenditure' => 0,
            'annual_expenditure' => 0,
        ];
    }
}
