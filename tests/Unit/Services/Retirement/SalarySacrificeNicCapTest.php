<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Retirement\RetirementStrategyService;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Pins the salary-sacrifice NIC-exemption cap's date-gated behaviour.
 *
 * From 2027-04-06 (start of UK tax year 2027/28, per CSJ-confirmed Budget
 * announcement), only the first £2,000/year of employee salary sacrifice
 * into a pension is exempt from NICs. Before that date, the full sacrificed
 * amount is NIC-exempt and treated as zero-cost in `calculateNetCostOfContribution`.
 *
 * The cap value (£2,000) and effective date are sourced from TaxConfigService
 * (`pension.salary_sacrifice.nic_exemption_cap` / `*_effective_date`), so this
 * behaviour activates automatically when the clock crosses the threshold —
 * no code change required at the boundary.
 *
 * Audit reference: review-tax-compliance Tier 1 #4 / memory
 * `project_salary_sacrifice_2k_upcoming_law.md`.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(RetirementStrategyService::class);
    $this->reflection = new ReflectionClass(RetirementStrategyService::class);
    $this->method = $this->reflection->getMethod('calculateNetCostOfContribution');
    $this->method->setAccessible(true);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('Salary sacrifice NIC-exemption cap — date-gated', function () {
    it('treats the entire contribution as zero-cost before the cap effective date', function () {
        Carbon::setTestNow('2026-12-31');

        // Basic-rate user, £5,000 contribution. Pre-2027 the cap is inactive,
        // so the whole sacrifice is NIC-exempt and treated as zero-cost.
        $user = User::factory()->create(['annual_employment_income' => 30_000]);

        $cost = $this->method->invoke($this->service, 5_000.0, $user);

        expect($cost)->toBe(0.0);
    });

    it('caps salary-sacrificed zero-cost portion at £2,000 on the effective date', function () {
        Carbon::setTestNow('2027-04-06');

        // Basic-rate user (20% marginal), £5,000 contribution.
        // First £2k zero-cost; remaining £3k via relief at source costs £3,000 × 0.80 = £2,400.
        $user = User::factory()->create(['annual_employment_income' => 30_000]);

        $cost = $this->method->invoke($this->service, 5_000.0, $user);

        expect($cost)->toBe(2_400.0);
    });

    it('keeps the cap active after the effective date', function () {
        Carbon::setTestNow('2028-01-01');

        // Higher-rate user (40% marginal), £6,000 contribution.
        // First £2k zero-cost; remaining £4k via relief at source costs £4,000 × 0.60 = £2,400.
        $user = User::factory()->create(['annual_employment_income' => 80_000]);

        $cost = $this->method->invoke($this->service, 6_000.0, $user);

        expect($cost)->toBe(2_400.0);
    });

    it('returns zero when the contribution stays within the cap on/after the effective date', function () {
        Carbon::setTestNow('2027-04-06');

        $user = User::factory()->create(['annual_employment_income' => 50_000]);

        $cost = $this->method->invoke($this->service, 1_500.0, $user);

        expect($cost)->toBe(0.0);
    });
});
