<?php

declare(strict_types=1);

use App\Models\PlanConfiguration;
use App\Services\Plans\PlanConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PlanConfigService', function () {
    it('returns built-in defaults when no active configuration exists', function () {
        $service = new PlanConfigService;

        expect($service->getDefaultGrowthRate())->toBe(0.05)
            ->and($service->getWithdrawalRate())->toBe(0.04)
            ->and($service->getPlatformFeeBenchmark())->toBe(0.25)
            ->and($service->getOCFBenchmark())->toBe(0.15)
            ->and($service->getConsolidationEfficiencyGain())->toBe(0.02)
            ->and($service->getTaxOptimisationGain())->toBe(0.03)
            ->and($service->getDefaultActionGain())->toBe(0.01)
            ->and($service->getEstateAgeGate())->toBe(35)
            ->and($service->getCharitableGivingThreshold())->toBe(10.0)
            ->and($service->getPlanCacheTTL())->toBe(1800)
            ->and($service->getRetirementCacheTTL())->toBe(3600)
            ->and($service->getSavingsCacheTTL())->toBe(1800);
    });

    it('returns values from active database configuration', function () {
        PlanConfiguration::create([
            'version' => '1.0',
            'config_data' => [
                'rates' => [
                    'default_growth_rate' => 0.06,
                    'withdrawal_rate' => 0.035,
                    'consolidation_efficiency_gain' => 0.025,
                    'tax_optimisation_gain' => 0.04,
                    'default_action_gain' => 0.015,
                ],
                'benchmarks' => [
                    'platform_fee_percent' => 0.20,
                    'ocf_percent' => 0.10,
                ],
                'estate' => [
                    'age_gate' => 40,
                    'charitable_giving_threshold_percent' => 12.0,
                ],
                'cache' => [
                    'plan_ttl' => 900,
                    'retirement_ttl' => 7200,
                    'savings_ttl' => 900,
                ],
            ],
            'is_active' => true,
        ]);

        $service = new PlanConfigService;

        expect($service->getDefaultGrowthRate())->toBe(0.06)
            ->and($service->getWithdrawalRate())->toBe(0.035)
            ->and($service->getPlatformFeeBenchmark())->toBe(0.20)
            ->and($service->getOCFBenchmark())->toBe(0.10)
            ->and($service->getConsolidationEfficiencyGain())->toBe(0.025)
            ->and($service->getTaxOptimisationGain())->toBe(0.04)
            ->and($service->getDefaultActionGain())->toBe(0.015)
            ->and($service->getEstateAgeGate())->toBe(40)
            ->and($service->getCharitableGivingThreshold())->toBe(12.0)
            ->and($service->getPlanCacheTTL())->toBe(900)
            ->and($service->getRetirementCacheTTL())->toBe(7200)
            ->and($service->getSavingsCacheTTL())->toBe(900);
    });

    it('supports dot-notation get with custom defaults', function () {
        $service = new PlanConfigService;

        expect($service->get('rates.default_growth_rate'))->toBe(0.05)
            ->and($service->get('nonexistent.key', 'fallback'))->toBe('fallback');
    });

    it('checks key existence correctly', function () {
        $service = new PlanConfigService;

        expect($service->has('rates.default_growth_rate'))->toBeTrue()
            ->and($service->has('nonexistent.key'))->toBeFalse();
    });

    it('caches config per request and clears on clearCache', function () {
        PlanConfiguration::create([
            'version' => '1.0',
            'config_data' => ['rates' => ['default_growth_rate' => 0.07]],
            'is_active' => true,
        ]);

        $service = new PlanConfigService;
        expect($service->getDefaultGrowthRate())->toBe(0.07);

        // Update DB directly
        PlanConfiguration::where('is_active', true)->update([
            'config_data' => ['rates' => ['default_growth_rate' => 0.08]],
        ]);

        // Should still return cached value
        expect($service->getDefaultGrowthRate())->toBe(0.07);

        // After clearing cache, should fetch new value
        $service->clearCache();
        expect($service->getDefaultGrowthRate())->toBe(0.08);
    });

    it('returns all config data via getAll', function () {
        $service = new PlanConfigService;
        $all = $service->getAll();

        expect($all)->toBeArray()
            ->and($all)->toHaveKeys(['rates', 'benchmarks', 'estate', 'cache']);
    });
});
