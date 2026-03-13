<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * InvestmentDefaults - Centralised defaults for investment analysis and portfolio calculations.
 *
 * Provides target allocations by risk level, asset class mappings, fee thresholds,
 * and helper methods for resolving asset types and risk levels consistently
 * across all investment services.
 *
 * Last reviewed: 13 March 2026
 */
final class InvestmentDefaults
{
    // ==================== Target Allocations by Risk Level ====================

    /**
     * Model portfolio allocations keyed by risk level (1-5).
     * All keys use plural form: equities, bonds, cash, alternatives.
     * Values are percentages that sum to 100.
     */
    public const TARGET_ALLOCATIONS = [
        1 => ['equities' => 10, 'bonds' => 70, 'cash' => 20, 'alternatives' => 0],
        2 => ['equities' => 30, 'bonds' => 55, 'cash' => 10, 'alternatives' => 5],
        3 => ['equities' => 50, 'bonds' => 40, 'cash' => 5, 'alternatives' => 5],
        4 => ['equities' => 75, 'bonds' => 20, 'cash' => 0, 'alternatives' => 5],
        5 => ['equities' => 90, 'bonds' => 5, 'cash' => 0, 'alternatives' => 5],
    ];

    // ==================== Risk Level Mapping ====================

    /**
     * Maps legacy string risk labels to integer risk levels (1-5).
     * Supports multiple naming conventions used across the codebase.
     */
    public const RISK_LEVEL_MAP = [
        'low' => 1, 'cautious' => 1,
        'lower_medium' => 2, 'conservative' => 2,
        'medium' => 3, 'balanced' => 3,
        'upper_medium' => 4, 'growth' => 4,
        'high' => 5, 'adventurous' => 5, 'aggressive' => 5,
    ];

    // ==================== Asset Class Mapping ====================

    /**
     * Union of all asset type strings to their parent asset class.
     * Consolidated from five separate mapping implementations.
     */
    public const ASSET_CLASS_MAP = [
        // Equities
        'uk_equity' => 'equities',
        'us_equity' => 'equities',
        'international_equity' => 'equities',
        'global_equity' => 'equities',
        'emerging_markets' => 'equities',
        'equity' => 'equities',
        'stock' => 'equities',
        'etf' => 'equities',
        // Bonds
        'bond' => 'bonds',
        'fixed_income' => 'bonds',
        'gilt' => 'bonds',
        'uk_bonds' => 'bonds',
        'global_bonds' => 'bonds',
        'government_bonds' => 'bonds',
        'corporate_bonds' => 'bonds',
        // Cash
        'cash' => 'cash',
        'money_market' => 'cash',
        // Alternatives
        'alternative' => 'alternatives',
        'property' => 'alternatives',
        'real_estate' => 'alternatives',
        'reit' => 'alternatives',
        'commodities' => 'alternatives',
        // Fund (needs sub_type for accurate classification)
        'fund' => 'mixed',
    ];

    // ==================== Fund Sub-Type Mapping ====================

    /**
     * Maps fund sub-types to their parent asset class.
     * Used when asset_type is 'fund' to provide accurate classification.
     */
    public const FUND_SUB_TYPES = [
        'equity_fund' => 'equities',
        'bond_fund' => 'bonds',
        'mixed_fund' => 'mixed',
        'income_fund' => 'bonds',
        'index_fund' => 'equities',
        'money_market_fund' => 'cash',
        'property_fund' => 'alternatives',
    ];

    // ==================== Fee Thresholds ====================

    /**
     * High OCF threshold as a decimal (0.75%).
     */
    public const HIGH_OCF_THRESHOLD_DECIMAL = 0.0075;

    /**
     * High OCF threshold as a percentage value.
     */
    public const HIGH_OCF_THRESHOLD_PERCENT = 0.75;

    /**
     * Total fee percentage considered high (platform + fund fees combined).
     */
    public const HIGH_TOTAL_FEE_PERCENT = 1.0;

    /**
     * Platform fee percentage considered high.
     */
    public const HIGH_PLATFORM_FEE_PERCENT = 0.8;

    // ==================== Default OCF Estimates ====================

    /**
     * Estimated ongoing charge figures by holding type (decimal form).
     * Used when the user has not provided an actual OCF value.
     */
    public const DEFAULT_OCF_ESTIMATES = [
        'index_fund' => 0.001,
        'etf' => 0.001,
        'active_fund' => 0.0075,
        'equity' => 0.0,
        'stock' => 0.0,
        'bond' => 0.0005,
        'alternative' => 0.015,
        'default' => 0.005,
    ];

    // ==================== Helper Methods ====================

    /**
     * Resolve an asset type (and optional sub-type) to a parent asset class.
     *
     * Priority:
     *  1. If $subType is set and exists in FUND_SUB_TYPES, return that mapping.
     *  2. If $assetType is 'fund' with no recognised $subType, return 'mixed'.
     *  3. Look up $assetType in ASSET_CLASS_MAP.
     *  4. Fallback to 'equities'.
     */
    public static function resolveAssetClass(string $assetType, ?string $subType = null): string
    {
        if ($subType !== null && isset(self::FUND_SUB_TYPES[$subType])) {
            return self::FUND_SUB_TYPES[$subType];
        }

        $normalised = strtolower($assetType);

        if ($normalised === 'fund' && $subType === null) {
            return 'mixed';
        }

        return self::ASSET_CLASS_MAP[$normalised] ?? 'equities';
    }

    /**
     * Get the target allocation array for a given risk level.
     *
     * Accepts an integer (1-5) or a string label (e.g. 'balanced', 'cautious').
     * Returns TARGET_ALLOCATIONS[3] (balanced) as a safe default for unrecognised values.
     */
    public static function getTargetAllocation(int|string $riskLevel): array
    {
        if (is_string($riskLevel)) {
            $riskLevel = self::RISK_LEVEL_MAP[strtolower($riskLevel)] ?? 3;
        }

        return self::TARGET_ALLOCATIONS[$riskLevel] ?? self::TARGET_ALLOCATIONS[3];
    }
}
