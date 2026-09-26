<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function ssRec(User $user): ?array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'salary_sacrifice_ni');
}

function ssUser(): User
{
    return User::factory()->create([
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'marital_status' => 'single',
    ]);
}

it('prices a workplace pension captured as a percentage of salary', function () {
    $user = ssUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace',
        'pension_type' => 'occupational',
        'monthly_contribution_amount' => null,
        'annual_salary' => null,
        'employee_contribution_percent' => 5,
        'salary_sacrifice' => false,
    ]);

    $rec = ssRec($user);

    expect($rec)->not->toBeNull()
        ->and($rec['annual_contribution'])->toBe(3000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0);
});

it('treats the onboarding form shape (occupational type, null scheme) as workplace', function () {
    $user = ssUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null,
        'pension_type' => 'occupational',
        'monthly_contribution_amount' => null,
        'annual_salary' => null,
        'employee_contribution_percent' => 5,
        'salary_sacrifice' => false,
    ]);

    expect(ssRec($user))->not->toBeNull();
});

it('never suggests salary sacrifice for a SIPP or personal pension', function (string $scheme, string $type) {
    $user = ssUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => $scheme,
        'pension_type' => $type,
        'monthly_contribution_amount' => 500,
        'salary_sacrifice' => false,
    ]);

    expect(ssRec($user))->toBeNull();
})->with([
    'sipp' => ['sipp', 'sipp'],
    'personal' => ['personal', 'personal'],
]);
