<?php

declare(strict_types=1);

namespace App\Services\Risk;

use App\Models\Investment\RiskProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Risk Preference Service
 *
 * Manages user's main risk level and validates per-product risk preferences.
 * Implements the 5-level risk system with constrained per-product overrides.
 */
class RiskPreferenceService
{
    /**
     * Risk level ordering for constraint validation
     */
    private array $riskLevelOrder = [
        'low' => 1,
        'lower_medium' => 2,
        'medium' => 3,
        'upper_medium' => 4,
        'high' => 5,
    ];

    /**
     * Risk level configurations with display names and descriptions
     */
    private array $riskLevelConfigs = [
        'low' => [
            'level_numeric' => 1,
            'display_name' => 'Low',
            'short_description' => 'Prioritises capital preservation with minimal volatility.',
            'full_description' => 'You are a very cautious investor. You prioritise investment products of low uncertainty on risk and prefer to minimise investment loss. You may have limited knowledge and/or experience in financial investment.',
            'asset_allocation' => ['equities' => 10, 'bonds' => 70, 'cash' => 20, 'alternatives' => 0],
            'expected_returns' => ['min' => 1.0, 'max' => 3.0, 'typical' => 2.0],
            'volatility_percent' => 3.0,
            'colour_class' => 'green',
        ],
        'lower_medium' => [
            'level_numeric' => 2,
            'display_name' => 'Lower-Medium',
            'short_description' => 'Seeks stability with modest growth potential.',
            'full_description' => 'You are a cautious investor. You are equipped with some knowledge and/or experience in financial investment and are willing to take modest risk for the potential to achieve returns better than bank deposits.',
            'asset_allocation' => ['equities' => 30, 'bonds' => 55, 'cash' => 10, 'alternatives' => 5],
            'expected_returns' => ['min' => 2.0, 'max' => 4.5, 'typical' => 3.5],
            'volatility_percent' => 6.0,
            'colour_class' => 'teal',
        ],
        'medium' => [
            'level_numeric' => 3,
            'display_name' => 'Medium',
            'short_description' => 'Balanced approach accepting moderate volatility.',
            'full_description' => 'You are equipped with related investment knowledge and/or experience. You are willing to accept commensurable price fluctuation and take a certain degree of risk to achieve returns in comparison with the major stock market indexes. You possess good financial capability to deal with potential losses.',
            'asset_allocation' => ['equities' => 50, 'bonds' => 35, 'cash' => 10, 'alternatives' => 5],
            'expected_returns' => ['min' => 3.5, 'max' => 6.5, 'typical' => 5.0],
            'volatility_percent' => 10.0,
            'colour_class' => 'blue',
        ],
        'upper_medium' => [
            'level_numeric' => 4,
            'display_name' => 'Upper-Medium',
            'short_description' => 'Prioritises growth, comfortable with fluctuations.',
            'full_description' => 'You are equipped with related investment knowledge and/or experience. You are willing to accept relatively higher price fluctuation and take relatively higher risk to achieve returns better than the major stock market indexes. You possess solid financial capability to deal with potential losses.',
            'asset_allocation' => ['equities' => 75, 'bonds' => 20, 'cash' => 0, 'alternatives' => 5],
            'expected_returns' => ['min' => 5.0, 'max' => 8.5, 'typical' => 6.5],
            'volatility_percent' => 15.0,
            'colour_class' => 'orange',
        ],
        'high' => [
            'level_numeric' => 5,
            'display_name' => 'High',
            'short_description' => 'Seeks maximum growth, accepts high volatility.',
            'full_description' => 'You demonstrate a strong preference, knowledge and/or experience for high-risk, complex or leveraged products. You possess substantial financial capability to deal with potential losses from financial investment.',
            'asset_allocation' => ['equities' => 90, 'bonds' => 0, 'cash' => 5, 'alternatives' => 5],
            'expected_returns' => ['min' => 6.0, 'max' => 12.0, 'typical' => 8.0],
            'volatility_percent' => 20.0,
            'colour_class' => 'red',
        ],
    ];

    /**
     * Get all available risk levels with their configurations
     */
    public function getAvailableRiskLevels(): array
    {
        $levels = [];

        foreach ($this->riskLevelConfigs as $key => $config) {
            $levels[] = [
                'key' => $key,
                'level_numeric' => $config['level_numeric'],
                'display_name' => $config['display_name'],
                'short_description' => $config['short_description'],
                'full_description' => $config['full_description'],
                'asset_allocation' => $config['asset_allocation'],
                'expected_returns' => $config['expected_returns'],
                'volatility_percent' => $config['volatility_percent'],
                'colour_class' => $config['colour_class'],
            ];
        }

        return $levels;
    }

    /**
     * Set user's main risk preference (self-select)
     */
    public function setMainRiskLevel(int $userId, string $riskLevel): RiskProfile
    {
        if (! isset($this->riskLevelOrder[$riskLevel])) {
            throw new \InvalidArgumentException("Invalid risk level: {$riskLevel}");
        }

        // Recalculate factor breakdown for audit accuracy
        $calculator = app(AutoRiskCalculator::class);
        $user = User::findOrFail($userId);
        $calculated = $calculator->calculateRiskProfile($user);

        $riskProfile = RiskProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'risk_level' => $riskLevel,
                'factor_breakdown' => $calculated['factor_breakdown'],
                'risk_assessed_at' => now(),
                'is_self_assessed' => true,
            ]
        );

        // Clear cached data
        $this->clearUserCache($userId);

        return $riskProfile;
    }

    /**
     * Calculate and set risk level automatically based on financial factors
     *
     * Uses AutoRiskCalculator to analyze 7 factors and determine appropriate risk level.
     */
    public function calculateAndSetRiskLevel(int $userId): array
    {
        $calculator = app(AutoRiskCalculator::class);
        $user = User::findOrFail($userId);

        $result = $calculator->calculateRiskProfile($user);

        $riskProfile = RiskProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'risk_level' => $result['risk_level'],
                'factor_breakdown' => $result['factor_breakdown'],
                'is_self_assessed' => false,
                'risk_assessed_at' => now(),
            ]
        );

        // Clear cached data
        $this->clearUserCache($userId);

        Log::info('Auto-calculated risk profile', [
            'user_id' => $userId,
            'risk_level' => $result['risk_level'],
            'factor_count' => count($result['factor_breakdown']),
        ]);

        // Check for risk mismatch
        $mismatch = $calculator->detectRiskMismatch($riskProfile);

        return [
            'risk_level' => $result['risk_level'],
            'factor_breakdown' => $result['factor_breakdown'],
            'risk_assessed_at' => $riskProfile->risk_assessed_at?->toIso8601String(),
            'is_self_assessed' => false,
            'config' => $this->getRiskLevelConfig($result['risk_level']),
            'risk_mismatch' => $mismatch,
        ];
    }

    /**
     * Get user's current main risk level
     */
    public function getMainRiskLevel(int $userId): ?string
    {
        $cacheKey = "user_risk_level_{$userId}";

        return Cache::remember($cacheKey, 86400, function () use ($userId) {
            $profile = RiskProfile::where('user_id', $userId)->first();

            return $profile?->risk_level;
        });
    }

    /**
     * Get user's complete risk profile
     *
     * Always recalculates factor_breakdown to ensure current values and components.
     * Includes risk mismatch detection when user has a stated tolerance.
     */
    public function getRiskProfile(int $userId): ?array
    {
        $profile = RiskProfile::where('user_id', $userId)->first();

        if (! $profile || ! $profile->risk_level) {
            return null;
        }

        $config = $this->getRiskLevelConfig($profile->risk_level);

        // Recalculate factors live for current values
        $calculator = app(AutoRiskCalculator::class);
        $user = User::findOrFail($userId);
        $calculated = $calculator->calculateRiskProfile($user);

        // Check for risk mismatch
        $mismatch = $calculator->detectRiskMismatch($profile);

        return [
            'risk_level' => $profile->risk_level,
            'risk_assessed_at' => $profile->risk_assessed_at?->toIso8601String(),
            'is_self_assessed' => $profile->is_self_assessed,
            'factor_breakdown' => $calculated['factor_breakdown'],
            'config' => $config,
            'risk_mismatch' => $mismatch,
        ];
    }

    /**
     * Get allowed risk levels for a product.
     *
     * Users can select any risk level for their products, regardless of their main profile.
     */
    public function getAllowedProductRiskLevels(int $userId): array
    {
        // Allow all risk levels - no restriction to adjacent levels
        return array_keys($this->riskLevelOrder);
    }

    /**
     * Get allowed risk levels with full configuration details
     */
    public function getAllowedProductRiskLevelsWithConfig(int $userId): array
    {
        $allowedKeys = $this->getAllowedProductRiskLevels($userId);
        $mainLevel = $this->getMainRiskLevel($userId);
        $result = [];

        foreach ($allowedKeys as $key) {
            $config = $this->riskLevelConfigs[$key];
            $result[] = [
                'key' => $key,
                'level_numeric' => $config['level_numeric'],
                'display_name' => $config['display_name'],
                'short_description' => $config['short_description'],
                'colour_class' => $config['colour_class'],
                'is_main_level' => $key === $mainLevel,
            ];
        }

        return $result;
    }

    /**
     * Validate a product risk level is within constraints
     */
    public function validateProductRiskLevel(int $userId, string $riskLevel): bool
    {
        $allowedLevels = $this->getAllowedProductRiskLevels($userId);

        return in_array($riskLevel, $allowedLevels, true);
    }

    /**
     * Check if a product risk level differs from the user's main profile
     */
    public function isCustomRiskLevel(int $userId, string $productRiskLevel): bool
    {
        $mainLevel = $this->getMainRiskLevel($userId);

        if (! $mainLevel) {
            return false;
        }

        return $productRiskLevel !== $mainLevel;
    }

    /**
     * Get risk level configuration details
     */
    public function getRiskLevelConfig(string $riskLevel): array
    {
        if (! isset($this->riskLevelConfigs[$riskLevel])) {
            throw new \InvalidArgumentException("Invalid risk level: {$riskLevel}");
        }

        return array_merge(
            ['key' => $riskLevel],
            $this->riskLevelConfigs[$riskLevel]
        );
    }

    /**
     * Get asset allocation for a risk level
     */
    public function getAssetAllocation(string $riskLevel): array
    {
        $config = $this->getRiskLevelConfig($riskLevel);

        return $config['asset_allocation'];
    }

    /**
     * Get expected return parameters for Monte Carlo simulations
     */
    public function getReturnParameters(string $riskLevel): array
    {
        $config = $this->getRiskLevelConfig($riskLevel);

        return [
            'expected_return_min' => $config['expected_returns']['min'],
            'expected_return_max' => $config['expected_returns']['max'],
            'expected_return_typical' => $config['expected_returns']['typical'],
            'volatility' => $config['volatility_percent'],
        ];
    }

    /**
     * Get the numeric order of a risk level (1-5)
     */
    public function getRiskLevelNumeric(string $riskLevel): int
    {
        if (! isset($this->riskLevelOrder[$riskLevel])) {
            throw new \InvalidArgumentException("Invalid risk level: {$riskLevel}");
        }

        return $this->riskLevelOrder[$riskLevel];
    }

    /**
     * Map legacy 3-level tolerance to new 5-level system
     */
    public function mapLegacyTolerance(?string $tolerance): string
    {
        return match ($tolerance) {
            'cautious' => 'lower_medium',
            'balanced' => 'medium',
            'adventurous' => 'upper_medium',
            default => 'medium',
        };
    }

    /**
     * Clear user's cached risk data
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget("user_risk_level_{$userId}");
        Cache::forget("risk_profile_{$userId}");
    }
}
