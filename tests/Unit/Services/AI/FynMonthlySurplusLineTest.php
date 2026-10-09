<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\Coordination\CashFlowCoordinator;
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
        ->and($profile)->toContain('£130.00')
        ->and($profile)->toContain('−£'.number_format(abs($surplus), 2));
});
