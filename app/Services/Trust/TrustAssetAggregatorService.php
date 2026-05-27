<?php

declare(strict_types=1);

namespace App\Services\Trust;

use App\Models\BusinessInterest;
use App\Models\CashAccount;
use App\Models\Chattel;
use App\Models\Estate\Trust;
use App\Models\Investment\InvestmentAccount;
use App\Services\Stores\PropertyStore;
use Illuminate\Support\Collection;

class TrustAssetAggregatorService
{
    public function __construct(
        private readonly PropertyStore $propertyStore,
    ) {}

    /**
     * Aggregate all assets held in a specific trust
     */
    public function aggregateAssetsForTrust(Trust $trust): array
    {
        $assets = [
            'properties' => $this->getPropertiesInTrust($trust->id),
            'investment_accounts' => $this->getInvestmentAccountsInTrust($trust->id),
            'cash_accounts' => $this->getCashAccountsInTrust($trust->id),
            'business_interests' => $this->getBusinessInterestsInTrust($trust->id),
            'chattels' => $this->getChattelsInTrust($trust->id),
        ];

        $totalValue = $this->calculateTotalValue($assets);

        return [
            'assets' => $assets,
            'total_value' => $totalValue,
            'asset_count' => $this->countAssets($assets),
            'breakdown' => $this->createValueBreakdown($assets),
        ];
    }

    /**
     * Get all properties held in trust
     */
    private function getPropertiesInTrust(int $trustId): Collection
    {
        return $this->propertyStore
            ->forTrust($trustId)
            ->map(function ($property) {
                return [
                    'id' => $property->id,
                    'type' => 'property',
                    'name' => $property->address_line_1.', '.$property->city,
                    'property_type' => $property->property_type,
                    'value' => $property->current_value * ($property->ownership_percentage / 100),
                    'full_value' => $property->current_value,
                    'ownership_percentage' => $property->ownership_percentage,
                    'valuation_date' => $property->valuation_date,
                ];
            });
    }

    /**
     * Get all investment accounts held in trust
     */
    private function getInvestmentAccountsInTrust(int $trustId): Collection
    {
        return InvestmentAccount::where('trust_id', $trustId)
            ->get()
            ->map(function ($account) {
                $totalValue = $account->holdings()->sum('current_value');

                return [
                    'id' => $account->id,
                    'type' => 'investment_account',
                    'name' => $account->account_name,
                    'institution' => $account->institution_name,
                    'account_type' => $account->account_type,
                    'value' => $totalValue * ($account->ownership_percentage / 100),
                    'full_value' => $totalValue,
                    'ownership_percentage' => $account->ownership_percentage,
                    'holdings_count' => $account->holdings()->count(),
                ];
            });
    }

    /**
     * Get all cash accounts held in trust
     */
    private function getCashAccountsInTrust(int $trustId): Collection
    {
        return CashAccount::where('trust_id', $trustId)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'type' => 'cash_account',
                    'name' => $account->account_name,
                    'institution' => $account->institution_name,
                    'account_type' => $account->account_type,
                    'value' => $account->current_balance * ($account->ownership_percentage / 100),
                    'full_value' => $account->current_balance,
                    'ownership_percentage' => $account->ownership_percentage,
                    'is_isa' => $account->is_isa,
                ];
            });
    }

    /**
     * Get all business interests held in trust
     */
    private function getBusinessInterestsInTrust(int $trustId): Collection
    {
        return BusinessInterest::where('trust_id', $trustId)
            ->get()
            ->map(function ($business) {
                return [
                    'id' => $business->id,
                    'type' => 'business_interest',
                    'name' => $business->business_name,
                    'business_type' => $business->business_type,
                    'value' => $business->current_valuation * ($business->ownership_percentage / 100),
                    'full_value' => $business->current_valuation,
                    'ownership_percentage' => $business->ownership_percentage,
                    'valuation_date' => $business->valuation_date,
                ];
            });
    }

    /**
     * Get all chattels held in trust
     */
    private function getChattelsInTrust(int $trustId): Collection
    {
        return Chattel::where('trust_id', $trustId)
            ->get()
            ->map(function ($chattel) {
                return [
                    'id' => $chattel->id,
                    'type' => 'chattel',
                    'name' => $chattel->item_name,
                    'category' => $chattel->category,
                    'value' => $chattel->current_value * ($chattel->ownership_percentage / 100),
                    'full_value' => $chattel->current_value,
                    'ownership_percentage' => $chattel->ownership_percentage,
                    'valuation_date' => $chattel->valuation_date,
                ];
            });
    }

    /**
     * Calculate total value of all assets
     */
    private function calculateTotalValue(array $assets): float
    {
        $total = 0.0;

        foreach ($assets as $assetType => $assetCollection) {
            $total += $assetCollection->sum('value');
        }

        return $total;
    }

    /**
     * Count total number of assets
     */
    private function countAssets(array $assets): int
    {
        $count = 0;

        foreach ($assets as $assetType => $assetCollection) {
            $count += $assetCollection->count();
        }

        return $count;
    }

    /**
     * Create value breakdown by asset type
     */
    private function createValueBreakdown(array $assets): array
    {
        $breakdown = [];
        $totalValue = $this->calculateTotalValue($assets);

        foreach ($assets as $assetType => $assetCollection) {
            $typeValue = $assetCollection->sum('value');
            $breakdown[$assetType] = [
                'value' => $typeValue,
                'count' => $assetCollection->count(),
                'percentage' => $totalValue > 0 ? ($typeValue / $totalValue) * 100 : 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Get all trusts with aggregated asset values for a user
     */
    public function aggregateAssetsForUser(int $userId): Collection
    {
        $trusts = Trust::where('user_id', $userId)->get();

        return $trusts->map(function ($trust) {
            $aggregation = $this->aggregateAssetsForTrust($trust);

            return [
                'trust' => $trust,
                'total_value' => $aggregation['total_value'],
                'asset_count' => $aggregation['asset_count'],
                'breakdown' => $aggregation['breakdown'],
            ];
        });
    }
}
