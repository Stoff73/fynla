<?php

declare(strict_types=1);

use App\Agents\RetirementAgent;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Retirement\RequiredCapitalCalculator;
use App\Services\Retirement\RetirementDrawdownPosition;
use App\Services\Retirement\RetirementHeadline;
use App\Services\Retirement\RetirementProjectionContractService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/*
 * One figure, every surface (CSJ 2026-10-01): RetirementHeadline is the one home
 * for the retirement figures the Retirement page, the dashboard, the cards and
 * Fyn show. These tests pin that every reader carries the same numbers.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::flush();
    $this->user = User::factory()->create([
        'date_of_birth' => now()->subYears(40)->toDateString(),
        'annual_employment_income' => 60000,
        'employment_status' => 'employed',
    ]);
    RetirementProfile::create(['user_id' => $this->user->id, 'current_age' => 40, 'target_retirement_age' => 65, 'target_retirement_income' => 35000]);
    DCPension::create([
        'user_id' => $this->user->id, 'scheme_name' => 'Workplace', 'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'employee_contribution_percent' => 3, 'employer_contribution_percent' => 3, 'current_fund_value' => 30000, 'annual_salary' => 60000, 'retirement_age' => 65,
    ]);
});

it('takes the projection from the planning contract and the target from the required capital calculator', function () {
    $headline = app(RetirementHeadline::class)->for($this->user->fresh());
    $plan = app(RetirementProjectionContractService::class)->build($this->user->fresh(), withUncertainty: false);
    $required = app(RequiredCapitalCalculator::class)->calculate($this->user->id);

    expect($headline['projected_income'])->toEqualWithDelta((float) $plan['planning_total_at_target_age'], 0.01)
        ->and($headline['target_income'])->toEqualWithDelta((float) $required['required_income'], 0.01)
        ->and($headline['target_source'])->toBe('profile')
        ->and($headline['kind'])->toBe('projected')
        ->and($headline['value'])->toBe($headline['projected_income'])
        ->and($headline['dc_value_today'])->toBe(30000.0)
        ->and($headline['years_to_retirement'])->toBe(25);
});

it('signs the gap: positive is short, negative is over', function () {
    $short = app(RetirementHeadline::class)->for($this->user->fresh());
    expect($short['income_gap'])->toEqualWithDelta(35000 - $short['projected_income'], 0.01)
        ->and($short['income_gap'])->toBeGreaterThan(0);

    RetirementProfile::where('user_id', $this->user->id)->update(['target_retirement_income' => 1000]);
    $over = app(RetirementHeadline::class)->for($this->user->fresh());
    expect($over['income_gap'])->toBeLessThan(0)
        ->and($over['progress_percent'])->toBeGreaterThan(100);
});

it('leads with the secured income for a household with no pot', function () {
    DCPension::where('user_id', $this->user->id)->delete();
    DBPension::create(['user_id' => $this->user->id, 'scheme_name' => 'NHS', 'scheme_type' => 'public_sector', 'accrued_annual_pension' => 35000, 'normal_retirement_age' => 65]);

    $headline = app(RetirementHeadline::class)->for($this->user->fresh());

    expect($headline['kind'])->toBe('guaranteed')
        ->and($headline['value'])->toBe($headline['guaranteed_income'])
        ->and($headline['guaranteed_income'])->toBeGreaterThanOrEqual(35000.0);
});

it('gives the agent summary, which the dashboard and Fyn read, the same figures', function () {
    $headline = app(RetirementHeadline::class)->for($this->user->fresh());
    $summary = app(RetirementAgent::class)->analyze($this->user->id)['data']['summary'];

    expect($summary['headline'])->toBe($headline)
        ->and($summary['projected_retirement_income'])->toBe($headline['projected_income'])
        ->and($summary['current_dc_value'])->toBe($headline['dc_value_today'])
        ->and($summary['income_gap'])->toEqualWithDelta(max(0, $headline['income_gap']), 0.01);
});

it('publishes the same headline on the projections endpoint', function () {
    $this->actingAs($this->user);
    $headline = app(RetirementHeadline::class)->for($this->user->fresh());

    $this->getJson('/api/retirement/projections')->assertOk()
        ->assertJsonPath('data.headline.projected_income', $headline['projected_income'])
        ->assertJsonPath('data.headline.income_gap', $headline['income_gap'])
        ->assertJsonPath('data.headline.target_source', 'profile');
});

it('leads someone drawing with the Retirement page\'s own income this year', function () {
    $this->user->update(['employment_status' => 'retired', 'annual_employment_income' => 0, 'date_of_birth' => now()->subYears(66)->toDateString()]);
    DCPension::where('user_id', $this->user->id)->update(['annual_drawdown_income' => 9000, 'current_fund_value' => 150000]);

    $user = $this->user->fresh();
    $headline = app(RetirementHeadline::class)->for($user);
    $page = app(RetirementDrawdownPosition::class)->for($user);

    expect($headline['kind'])->toBe('drawing')
        ->and($headline['value'])->toEqual(round((float) $page['income']['total'], 2))
        ->and($headline['drawing_income'])->toEqual($headline['value'])
        ->and($headline['drawing_per_year'])->toEqual(9000.0)
        ->and($headline['drawing_lasts_to_age'])->toBe($page['pot']['lasts_to_age']['middle']);
});
