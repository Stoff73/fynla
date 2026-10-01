<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ProtectionCoverPosition;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

function coverage(array $overrides = [], array $employer = []): array
{
    return array_merge([
        'life_coverage' => 0.0, 'critical_illness_coverage' => 0.0, 'income_protection_coverage' => 0.0,
        'disability_coverage' => 0.0, 'sickness_illness_coverage' => 0.0,
        'employer_benefits' => array_merge(['death_in_service' => 0.0, 'group_income_protection' => 0.0, 'group_critical_illness' => 0.0], $employer),
    ], $overrides);
}

it('splits life cover into own policies and the job, and says what is short', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 645538, 'gross_income' => 72000, 'income_protection_need' => 43200],
        coverage(['life_coverage' => 288000], ['death_in_service' => 288000]),
    )['life'];

    expect($p)->toMatchArray(['need' => 645538.0, 'own_cover' => 0.0, 'employer_cover' => 288000.0, 'short_by' => 357538.0,
        'over_by' => 0.0, 'status' => 'short', 'depends_on_job' => true, 'unit' => 'lump_sum']);
});

it('works out the critical illness need from gross income and the configured multiple', function () {
    // protection.income_multipliers.critical_illness = 3 (TaxConfigurationSeeder)
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 0, 'gross_income' => 72000, 'income_protection_need' => 0],
        coverage(['critical_illness_coverage' => 250000]),
    )['critical_illness'];

    expect($p)->toMatchArray(['need' => 216000.0, 'over_by' => 34000.0, 'short_by' => 0.0, 'status' => 'over']);
});

it('shows income protection a month, converting the annual group cover once', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 0, 'gross_income' => 60000, 'income_protection_need' => 36000],
        coverage(['income_protection_coverage' => 30000], ['group_income_protection' => 30000]),
    )['income_protection'];

    expect($p)->toMatchArray(['need' => 3000.0, 'employer_cover' => 2500.0, 'own_cover' => 0.0, 'short_by' => 500.0, 'unit' => 'monthly']);
});

it('never calls cover "over" when the need is unknown', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 0, 'gross_income' => 0, 'income_protection_need' => 0],
        coverage(['critical_illness_coverage' => 100000]),
    )['critical_illness'];

    expect($p['over_by'])->toBe(0.0)->and($p['status'])->toBe('covered');
});

it('does not flag job dependence at or below the configured share', function () {
    $p = app(ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 400000, 'gross_income' => 50000, 'income_protection_need' => 0],
        coverage(['life_coverage' => 400000], ['death_in_service' => 200000]),
    )['life'];

    expect($p['employer_share'])->toBe(0.5)->and($p['depends_on_job'])->toBeFalse()->and($p['status'])->toBe('covered');
});

it('counts a joint-life policy the spouse holds as the other life\'s own cover (W-0401)', function () {
    $owner = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 150000, 'date_of_birth' => now()->subYears(45)]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'spouse_id' => $owner->id, 'annual_employment_income' => 40000, 'date_of_birth' => now()->subYears(43)]);
    $owner->update(['spouse_id' => $spouse->id]);
    ProtectionProfile::factory()->create(['user_id' => $spouse->id, 'annual_income' => 40000, 'mortgage_balance' => 0, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => []]);
    LifeInsurancePolicy::factory()->create(['user_id' => $owner->id, 'sum_assured' => 500000, 'joint_life' => true]);

    $life = app(ProtectionCoverPosition::class)->forUser($spouse->fresh())['life'];

    expect($life['own_cover'])->toBeGreaterThanOrEqual(500000.0);
});

it('has no position for a user with no protection profile', function () {
    expect(app(ProtectionCoverPosition::class)->forUser(User::factory()->create()))->toBe([]);
});

// One figure, every surface (CSJ 2026-10-01): the words web, /m and iOS print
// come from here, not from three client copies.
it('words each cover type for every surface', function () {
    $position = app(\App\Services\Protection\ProtectionCoverPosition::class)->fromAnalysis(
        ['total_need' => 400000, 'gross_income' => 0, 'income_protection_need' => 30000],
        ['life_coverage' => 250000, 'income_protection_coverage' => 30000, 'employer_benefits' => []],
    );

    expect($position['life'])->toMatchArray([
        'label' => 'Life cover',
        'status_label' => 'Short by £150,000',
        'tone' => 'short',
        'need_label' => '£400,000',
        'own_cover_label' => '£250,000',
        'employer_cover_label' => '£0',
    ])->and($position['income_protection'])->toMatchArray([
        'status_label' => 'Covered',
        'tone' => 'covered',
        'need_label' => '£2,500 a month',
    ]);
});
