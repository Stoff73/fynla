<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\Coordination\CashFlowCoordinator;
use App\Support\Money;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Fyn quotes the plan's monthly surplus, the same server figure the plan and
 * the actions list use (CashFlowCoordinator::calculateAvailableSurplus; CSJ
 * 2026-10-01, one figure fetched by every surface). Without it Fyn worked it
 * out from take-home and spending, left out the pension payment, and said
 * "£1,900.00 a month left … about £6.70 a month short" (csjones, Jamie,
 * 2026-10-08), where the plan's figure is −£136.70.
 */
beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('gives Fyn the plan\'s monthly commitments and what is left over', function () {
    $user = User::factory()->create([
        'employment_status' => 'part_time', 'annual_employment_income' => 26000,
        'monthly_expenditure' => 1900, 'expenditure_entry_mode' => 'simple',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id, 'scheme_type' => 'personal',
        'monthly_contribution_amount' => 130, 'employee_contribution_percent' => null,
        'employer_contribution_percent' => null, 'salary_sacrifice' => false,
    ]);

    $profile = app(AdvicePromptBuilder::class)->buildUserProfile($user->fresh());
    $surplus = app(CashFlowCoordinator::class)->calculateAvailableSurplus($user->id);

    expect($surplus)->toBeLessThan(0.0)
        // Money as the user reads it (CSJ 2026-10-12, walk fix plan T15): £130
        // when whole, pence only when the figure has them, never rounded.
        ->and($profile)->toContain('each month, on top of the expenditure above (pension payments from take-home, protection premiums, regular saving, loan repayments): £130')
        ->and($profile)->not->toContain('£130.00')
        ->and($profile)->toContain(Money::exact($surplus));
});

it('keeps the pence of a figure handed to the model and never rounds it', function () {
    $user = User::factory()->create([
        'employment_status' => 'part_time', 'annual_employment_income' => 26000,
        'monthly_expenditure' => 1900, 'expenditure_entry_mode' => 'simple',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id, 'scheme_type' => 'personal',
        'monthly_contribution_amount' => 130.45, 'employee_contribution_percent' => null,
        'employer_contribution_percent' => null, 'salary_sacrifice' => false,
    ]);

    expect(app(AdvicePromptBuilder::class)->buildUserProfile($user->fresh()))
        ->toContain('loan repayments): £130.45')
        ->toContain('- Total annual income: £26,000')
        ->not->toContain('£26,000.00');
});
