<?php

declare(strict_types=1);

namespace App\Services\Estate;

use Illuminate\Support\Collection;

/**
 * Analyzes asset liquidity for gifting strategy and Inheritance Tax payment purposes
 *
 * Classifies assets based on liquidity and giftability:
 * - Liquid: Cash accounts, savings accounts, Cash ISAs, Premium Bonds
 * - Semi-Liquid: Investment accounts (ISAs, General Investment Accounts, bonds),
 *   rental properties, second homes (can be sold but takes days/weeks, may incur Capital Gains Tax)
 * - Illiquid: Main residence, business interests, pensions (cannot be used to pay Inheritance Tax)
 */
class AssetLiquidityAnalyzer
{
    /**
     * Analyze all user assets and classify by liquidity
     *
     * @param  Collection  $assets  Collection of Asset models
     * @return array Liquidity analysis with giftable amounts
     */
    public function analyzeAssetLiquidity(Collection $assets): array
    {
        $liquidAssets = [];
        $semiLiquidAssets = [];
        $illiquidAssets = [];

        $totalLiquid = 0;
        $totalSemiLiquid = 0;
        $totalIlliquid = 0;

        foreach ($assets as $asset) {
            $classification = $this->classifyAsset($asset);

            $assetData = [
                'id' => $asset->id ?? null,
                'asset_name' => $asset->asset_name ?? 'Unknown Asset',
                'asset_type' => $asset->asset_type ?? 'other',
                'current_value' => (float) ($asset->current_value ?? 0),
                'is_main_residence' => $asset->is_main_residence ?? false,
                'is_giftable' => $classification['is_giftable'],
                'not_giftable_reason' => $classification['not_giftable_reason'],
                'gifting_considerations' => $classification['gifting_considerations'],
                'liquidity_classification' => $classification,
            ];

            if ($classification['liquidity'] === 'liquid') {
                $liquidAssets[] = $assetData;
                $totalLiquid += $asset->current_value;
            } elseif ($classification['liquidity'] === 'semi_liquid') {
                $semiLiquidAssets[] = $assetData;
                $totalSemiLiquid += $asset->current_value;
            } else {
                $illiquidAssets[] = $assetData;
                $totalIlliquid += $asset->current_value;
            }
        }

        return [
            'liquid' => [
                'assets' => $liquidAssets,
                'total_value' => round($totalLiquid, 2),
                'count' => count($liquidAssets),
                'description' => 'Easily giftable - can be gifted immediately without restriction',
            ],
            'semi_liquid' => [
                'assets' => $semiLiquidAssets,
                'total_value' => round($totalSemiLiquid, 2),
                'count' => count($semiLiquidAssets),
                'description' => 'Can be gifted with planning - consider tax implications and practicalities',
            ],
            'illiquid' => [
                'assets' => $illiquidAssets,
                'total_value' => round($totalIlliquid, 2),
                'count' => count($illiquidAssets),
                'description' => 'Cannot be gifted in current form - alternative strategies required',
            ],
            'summary' => [
                'total_giftable_value' => round($totalLiquid + $totalSemiLiquid, 2),
                'total_value' => round($totalLiquid + $totalSemiLiquid + $totalIlliquid, 2),
                'giftable_percentage' => ($totalLiquid + $totalSemiLiquid + $totalIlliquid) > 0
                    ? round((($totalLiquid + $totalSemiLiquid) / ($totalLiquid + $totalSemiLiquid + $totalIlliquid)) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * Classify a single asset by liquidity and giftability
     *
     * @return array Classification data
     */
    public function classifyAsset(object $asset): array
    {
        // Main residence - cannot be gifted while living in it
        if (($asset->is_main_residence ?? false) || ($asset->asset_type === 'property' && $this->isProbablyMainResidence($asset))) {
            return [
                'liquidity' => 'illiquid',
                'is_giftable' => false,
                'not_giftable_reason' => 'Main residence - gift with reservation of benefit (cannot gift while living in it)',
                'gifting_considerations' => [
                    'You cannot gift your main residence while continuing to live in it',
                    'This is called a "gift with reservation of benefit" and is treated as still being in your estate',
                    'Strategy: Consider downsizing once dependants leave home',
                    'Downsizing can release equity which can then be gifted',
                    'Alternatively: Move out and pay market rent to continue living there (complex)',
                ],
            ];
        }

        // Classify by asset type
        return match ($asset->asset_type ?? 'other') {
            'cash' => [
                'liquidity' => 'liquid',
                'is_giftable' => true,
                'not_giftable_reason' => null,
                'gifting_considerations' => [
                    'Cash is the most liquid asset and can be gifted immediately',
                    'Use annual exemption (£3,000/year) first',
                    'Small gifts (£250 per person per year) are immediately exempt',
                    'Regular gifts from surplus income are fully exempt (no limit)',
                    'Larger gifts are Potentially Exempt Transfers (PETs) - exempt after 7 years',
                    'Cash ISAs lose tax-free status when gifted',
                ],
            ],
            'investment' => [
                'liquidity' => 'semi_liquid',
                'is_giftable' => true,
                'not_giftable_reason' => null,
                'gifting_considerations' => [
                    'Investment assets can be sold but this takes days or weeks to settle',
                    'Can gift shares, funds, or cash from sales',
                    'Consider Capital Gains Tax on disposal before gifting',
                    'ISAs lose tax-free status when gifted (become normal investments)',
                    'Consider using annual exemption (£3,000) for regular small gifts',
                    'Large gifts are Potentially Exempt Transfers - exempt after 7 years',
                ],
            ],
            'pension', 'dc_pension', 'db_pension' => [
                'liquidity' => 'illiquid',
                'is_giftable' => false,
                'not_giftable_reason' => 'Pensions cannot be used to pay Inheritance Tax liabilities',
                'gifting_considerations' => [
                    'Pensions are currently NOT part of your taxable estate for Inheritance Tax (this may change from April 2027)',
                    'Pension funds pass to nominated beneficiaries outside the estate',
                    'Ensure beneficiaries are nominated on all pension schemes',
                    'Consider maximising pension contributions instead of gifting',
                    'Pension funds pass tax-free if you die before age 75',
                    'Pensions cannot be accessed to pay Inheritance Tax liabilities on the wider estate',
                ],
            ],
            'property' => [
                'liquidity' => 'semi_liquid',
                'is_giftable' => true,
                'not_giftable_reason' => null,
                'gifting_considerations' => [
                    'Rental or second properties can be gifted',
                    'Consider Stamp Duty Land Tax (SDLT) on transfer',
                    'Capital Gains Tax (CGT) may apply on gift (treated as disposal at market value)',
                    'Recipient assumes CGT base cost at market value on gift date',
                    'Can gift property in stages (e.g., transfer 10% per year)',
                    'Consider setting up a trust for property gifts',
                    'Large gifts are PETs - become exempt after 7 years',
                ],
            ],
            'business' => [
                'liquidity' => 'semi_liquid',
                'is_giftable' => true,
                'not_giftable_reason' => null,
                'gifting_considerations' => [
                    'Business assets may qualify for Business Property Relief (BPR)',
                    'BPR provides 50% or 100% IHT relief if owned for 2+ years',
                    'Gifting may lose BPR benefits - professional advice essential',
                    'Consider transferring business shares gradually',
                    'Capital Gains Tax relief available for some business disposals',
                    'Hold until 2-year BPR qualifying period if recently acquired',
                ],
            ],
            'chattel' => [
                'liquidity' => 'semi_liquid',
                'is_giftable' => true,
                'not_giftable_reason' => null,
                'gifting_considerations' => [
                    'Personal valuables can be gifted',
                    'Items worth over £6,000 may trigger Capital Gains Tax on gift',
                    'Wasting assets (lifespan under 50 years) are CGT exempt',
                    'Sets of items are valued together for CGT purposes',
                    'Professional valuation recommended for valuable items',
                    'Large gifts are PETs - exempt after 7 years',
                ],
            ],
            'other' => [
                'liquidity' => 'liquid', // Default to liquid for cash and other
                'is_giftable' => true,
                'not_giftable_reason' => null,
                'gifting_considerations' => [
                    'Cash and similar assets are highly liquid',
                    'Use annual exemption (£3,000/year) first',
                    'Small gifts (£250 per person per year) are immediately exempt',
                    'Larger gifts are PETs - exempt after 7 years',
                    'Consider regular gifts from surplus income (unlimited exemption)',
                ],
            ],
        };
    }

    /**
     * Heuristic to determine if a property is likely the main residence
     */
    private function isProbablyMainResidence(object $asset): bool
    {
        // Check asset name for indicators
        $name = strtolower($asset->asset_name ?? '');

        $mainResidenceKeywords = [
            'main',
            'primary',
            'family home',
            'residence',
            'home',
            'house',
        ];

        foreach ($mainResidenceKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate maximum giftable amount from liquid assets
     *
     * Provides a conservative estimate of how much can be gifted immediately
     */
    public function calculateMaximumGiftableAmount(Collection $assets): array
    {
        $analysis = $this->analyzeAssetLiquidity($assets);

        // Liquid assets can be gifted immediately
        $immediatelyGiftable = $analysis['liquid']['total_value'];

        // Semi-liquid assets require planning but are giftable
        $giftableWithPlanning = $analysis['semi_liquid']['total_value'];

        return [
            'immediately_giftable' => round($immediatelyGiftable, 2),
            'giftable_with_planning' => round($giftableWithPlanning, 2),
            'total_giftable' => round($immediatelyGiftable + $giftableWithPlanning, 2),
            'not_giftable' => round($analysis['illiquid']['total_value'], 2),
            'liquid_asset_count' => $analysis['liquid']['count'],
            'semi_liquid_asset_count' => $analysis['semi_liquid']['count'],
            'illiquid_asset_count' => $analysis['illiquid']['count'],
        ];
    }
}
