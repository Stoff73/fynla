<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Constants\TaxDefaults;
use Mockery\MockInterface;

/**
 * Canonical mocked TaxConfigService fixture for unit tests.
 *
 * The 2026-05-23 tech-debt audit found the same UK tax constants
 * (£325k NRB, £175k RNRB, £12,570 PA, £20k ISA, £60k AA) inlined into
 * Mockery setup across HouseholdPlanningServiceTest,
 * CrossModuleStrategyServiceTest, HolisticPlanRefactorTest,
 * PersonalizedTrustStrategyServiceTest, DividendTaxCalculatorTest,
 * TaxEfficiencyCalculatorTest, EstateAgentGoalsTest, and others.
 *
 * Sourcing from TaxDefaults keeps every test in lockstep with the
 * canonical 2026/27 values — when the next tax year ships (or
 * salary-sacrifice cap changes — see memory
 * `project_salary_sacrifice_2k_upcoming_law.md`), this is the only
 * file to touch.
 *
 * Usage:
 *   $taxConfig = Mockery::mock(TaxConfigService::class);
 *   TaxConfigFixture::apply($taxConfig);
 *   // or, for selective lookups:
 *   $taxConfig->shouldReceive('getInheritanceTax')
 *       ->andReturn(TaxConfigFixture::inheritanceTax());
 */
final class TaxConfigFixture
{
    /**
     * @return array{nil_rate_band: int, residence_nil_rate_band: int, rate: float}
     */
    public static function inheritanceTax(): array
    {
        return [
            'nil_rate_band' => TaxDefaults::NRB,
            'residence_nil_rate_band' => TaxDefaults::RNRB,
            'rate' => TaxDefaults::IHT_RATE,
        ];
    }

    /**
     * @return array{personal_allowance: int, basic_rate_limit?: int, additional_rate_limit?: int, bands?: array<int, array<string, mixed>>}
     */
    public static function incomeTax(): array
    {
        return [
            'personal_allowance' => TaxDefaults::PERSONAL_ALLOWANCE,
            'basic_rate_limit' => TaxDefaults::BASIC_RATE_BAND,
            'additional_rate_limit' => TaxDefaults::ADDITIONAL_RATE_THRESHOLD,
            'bands' => [
                ['name' => 'basic', 'rate' => 0.20, 'lower_limit' => TaxDefaults::PERSONAL_ALLOWANCE, 'upper_limit' => TaxDefaults::HIGHER_RATE_THRESHOLD],
                ['name' => 'higher', 'rate' => 0.40, 'lower_limit' => TaxDefaults::HIGHER_RATE_THRESHOLD, 'upper_limit' => TaxDefaults::ADDITIONAL_RATE_THRESHOLD],
                ['name' => 'additional', 'rate' => 0.45, 'lower_limit' => TaxDefaults::ADDITIONAL_RATE_THRESHOLD, 'upper_limit' => null],
            ],
        ];
    }

    /**
     * @return array{annual_allowance: int, lifetime_isa_max: int}
     */
    public static function isaAllowances(): array
    {
        return [
            'annual_allowance' => TaxDefaults::ISA_ALLOWANCE,
            'lifetime_isa_max' => TaxDefaults::LISA_ALLOWANCE,
        ];
    }

    /**
     * @return array{annual_allowance: int, money_purchase_annual_allowance: int}
     */
    public static function pensionAllowances(): array
    {
        return [
            'annual_allowance' => TaxDefaults::PENSION_ANNUAL_ALLOWANCE,
            'money_purchase_annual_allowance' => TaxDefaults::MPAA,
        ];
    }

    /**
     * Apply the canonical fixture to a Mockery mock of TaxConfigService.
     * Wires up the four most commonly-mocked methods. Tests that need
     * additional methods can chain `shouldReceive` afterwards.
     */
    public static function apply(MockInterface $taxConfig): MockInterface
    {
        $taxConfig->shouldReceive('getInheritanceTax')->andReturn(self::inheritanceTax());
        $taxConfig->shouldReceive('getIncomeTax')->andReturn(self::incomeTax());
        $taxConfig->shouldReceive('getISAAllowances')->andReturn(self::isaAllowances());
        $taxConfig->shouldReceive('getPensionAllowances')->andReturn(self::pensionAllowances());

        return $taxConfig;
    }
}
