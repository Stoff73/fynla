<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Constants\TaxDefaults;
use App\Models\PlanConfiguration;
use Illuminate\Support\Arr;

/**
 * Plan Configuration Service
 *
 * Provides centralised access to admin-configurable plan values.
 * Request-scoped singleton - loads active config once per request and caches in memory.
 *
 * Follows the same pattern as TaxConfigService. All plan-related rates,
 * benchmarks, and defaults that were previously hardcoded must be accessed
 * through this service.
 *
 * Usage:
 *   $planConfig = app(PlanConfigService::class);
 *   $growthRate = $planConfig->getDefaultGrowthRate();
 *   $agegate = $planConfig->getEstateAgeGate();
 */
class PlanConfigService
{
    /**
     * Cached active plan configuration (request-scoped)
     */
    private ?array $config = null;

    /**
     * Get the full active plan configuration
     */
    public function getAll(): array
    {
        return $this->loadActiveConfig();
    }

    /**
     * Get a specific plan configuration value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->loadActiveConfig();

        return Arr::get($config, $key, $default);
    }

    /**
     * Check if a configuration key exists
     */
    public function has(string $key): bool
    {
        $config = $this->loadActiveConfig();

        return Arr::has($config, $key);
    }

    // =========================================================================
    // Growth & Return Rates
    // =========================================================================

    /**
     * Default annual growth rate for investment and pension projections.
     * Conservative estimate net of inflation.
     */
    public function getDefaultGrowthRate(): float
    {
        return (float) $this->get('rates.default_growth_rate', TaxDefaults::DEFAULT_GROWTH_RATE);
    }

    /**
     * Sustainable withdrawal / annuity rate for retirement income calculations.
     */
    public function getWithdrawalRate(): float
    {
        return (float) $this->get('rates.withdrawal_rate', TaxDefaults::SAFE_WITHDRAWAL_RATE);
    }

    /**
     * Consolidation efficiency gain percentage for pension consolidation.
     */
    public function getConsolidationEfficiencyGain(): float
    {
        return (float) $this->get('rates.consolidation_efficiency_gain', 0.02);
    }

    /**
     * Tax optimisation gain percentage for pension tax relief optimisation.
     */
    public function getTaxOptimisationGain(): float
    {
        return (float) $this->get('rates.tax_optimisation_gain', 0.03);
    }

    /**
     * Default gain percentage for miscellaneous plan actions.
     */
    public function getDefaultActionGain(): float
    {
        return (float) $this->get('rates.default_action_gain', 0.01);
    }

    /**
     * Optimised annual growth rate (with recommendations implemented).
     * Higher than default to model the benefit of portfolio optimisation.
     */
    public function getOptimisedGrowthRate(): float
    {
        return (float) $this->get('rates.optimised_growth_rate', 0.06);
    }

    // =========================================================================
    // Emergency Fund
    // =========================================================================

    /**
     * Target emergency fund in months of expenses.
     * Used for surplus waterfall calculations and funding source eligibility.
     */
    public function getEmergencyFundTargetMonths(): int
    {
        return (int) $this->get('emergency_fund.target_months', 6);
    }

    // =========================================================================
    // Fee Benchmarks
    // =========================================================================

    /**
     * Target platform fee percentage benchmark.
     * Used to calculate potential fee savings on investment accounts.
     */
    public function getPlatformFeeBenchmark(): float
    {
        return (float) $this->get('benchmarks.platform_fee_percent', 0.25);
    }

    /**
     * Target Ongoing Charges Figure (OCF) benchmark.
     * Used to calculate potential fund fee savings.
     */
    public function getOCFBenchmark(): float
    {
        return (float) $this->get('benchmarks.ocf_percent', 0.15);
    }

    // =========================================================================
    // Estate Planning
    // =========================================================================

    /**
     * Minimum age gate for estate planning.
     * Users below this age will see a "not applicable" message.
     */
    public function getEstateAgeGate(): int
    {
        return (int) $this->get('estate.age_gate', 35);
    }

    /**
     * Charitable giving threshold percentage for reduced IHT rate.
     */
    public function getCharitableGivingThreshold(): float
    {
        return (float) $this->get('estate.charitable_giving_threshold_percent', 10.0);
    }

    // =========================================================================
    // Cache TTL Values
    // =========================================================================

    /**
     * Cache TTL for plan generation (in seconds).
     */
    public function getPlanCacheTTL(): int
    {
        return (int) $this->get('cache.plan_ttl', 1800);
    }

    /**
     * Cache TTL for retirement analysis (in seconds).
     */
    public function getRetirementCacheTTL(): int
    {
        return (int) $this->get('cache.retirement_ttl', 3600);
    }

    /**
     * Cache TTL for savings analysis (in seconds).
     */
    public function getSavingsCacheTTL(): int
    {
        return (int) $this->get('cache.savings_ttl', 1800);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Load active plan configuration (with request-scoped caching)
     *
     * Falls back to built-in defaults if no active configuration found,
     * unlike TaxConfigService which throws. Plans should always work
     * even without explicit DB configuration.
     */
    private function loadActiveConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        try {
            $model = PlanConfiguration::where('is_active', true)->first();

            if ($model) {
                $this->config = $model->config_data;
            } else {
                $this->config = $this->getBuiltInDefaults();
            }
        } catch (\Throwable $e) {
            // DB unavailable - fall back to built-in defaults so plans still work
            $this->config = $this->getBuiltInDefaults();
        }

        return $this->config;
    }

    /**
     * Built-in defaults used when no active DB configuration exists.
     * These are the initial seeded values and serve as a safety net.
     */
    private function getBuiltInDefaults(): array
    {
        return [
            'rates' => [
                'default_growth_rate' => TaxDefaults::DEFAULT_GROWTH_RATE,
                'optimised_growth_rate' => 0.06,
                'withdrawal_rate' => TaxDefaults::SAFE_WITHDRAWAL_RATE,
                'consolidation_efficiency_gain' => 0.02,
                'tax_optimisation_gain' => 0.03,
                'default_action_gain' => 0.01,
            ],
            'benchmarks' => [
                'platform_fee_percent' => 0.25,
                'ocf_percent' => 0.15,
            ],
            'estate' => [
                'age_gate' => 35,
                'charitable_giving_threshold_percent' => 10.0,
            ],
            'emergency_fund' => [
                'target_months' => 6,
            ],
            'cache' => [
                'plan_ttl' => 1800,
                'retirement_ttl' => 3600,
                'savings_ttl' => 1800,
            ],
        ];
    }

    /**
     * Clear cached configuration (mainly for testing)
     */
    public function clearCache(): void
    {
        $this->config = null;
    }
}
