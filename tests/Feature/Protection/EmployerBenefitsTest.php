<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use App\Services\Protection\ProtectionActionDefinitionService;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * The cover a user's employer provides (CSJ 2026-09-29): the gap analysis read
 * death in service, group income protection and group critical illness, but
 * nothing wrote them. One writer (EmployerBenefitsWriter) serves the web form
 * and Fyn; every save stamps employer_benefits_recorded_at, so "none" is an
 * answer and the "Record your employer benefits" card clears.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
});

function employedWithProfile(): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000, 'annual_expenditure' => 30000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_rental_income' => 0,
        'annual_other_income' => 0, 'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
    ProtectionProfile::factory()->create([
        'user_id' => $user->id, 'annual_income' => 60000, 'monthly_expenditure' => 2500,
        'mortgage_balance' => 0, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => [],
        'death_in_service_multiple' => null, 'group_ip_benefit_percent' => null, 'group_ci_amount' => null,
        'has_employer_pmi' => false, 'employer_benefits_recorded_at' => null,
    ]);

    return $user;
}

function employerCardFires(User $user): bool
{
    $plan = app(ComprehensiveProtectionPlanService::class)->generateComprehensiveProtectionPlan($user->fresh());

    return collect(app(ProtectionActionDefinitionService::class)->evaluateActions($plan))
        ->contains(fn (array $r): bool => ($r['definition_key'] ?? null) === 'no_employer_benefits_recorded');
}

it('saves employer benefits and returns them', function () {
    $user = employedWithProfile();
    Sanctum::actingAs($user);

    $this->putJson('/api/protection/employer-benefits', [
        'employer_name' => 'Acme Ltd',
        'death_in_service_multiple' => 4,
        'group_ip_benefit_percent' => 50,
        'group_ip_benefit_months' => 24,
        'group_ip_definition' => 'own',
        'group_ci_amount' => 50000,
        'has_employer_pmi' => true,
    ])->assertOk()
        ->assertJsonPath('data.employer_name', 'Acme Ltd')
        ->assertJsonPath('data.death_in_service_multiple', 4)
        ->assertJsonPath('data.group_ip_definition', 'own')
        ->assertJsonPath('data.has_employer_pmi', true);

    $profile = $user->protectionProfile()->first();
    expect((float) $profile->group_ci_amount)->toBe(50000.0)
        ->and($profile->employer_benefits_recorded_at)->not->toBeNull();
});

it('counts death in service as life cover once it is saved', function () {
    $user = employedWithProfile();
    Sanctum::actingAs($user);
    $this->putJson('/api/protection/employer-benefits', ['death_in_service_multiple' => 4])->assertOk();

    $plan = app(ComprehensiveProtectionPlanService::class)->generateComprehensiveProtectionPlan($user->fresh());

    // 4 x £60,000 salary (CoverageGapAnalyzer: multiple x salary).
    expect((float) ($plan['coverage_analysis']['life_insurance']['coverage'] ?? 0))->toBeGreaterThanOrEqual(240000.0);
});

it('treats "none" as an answer: the card clears and every benefit is blank', function () {
    $user = employedWithProfile();
    expect(employerCardFires($user))->toBeTrue();

    Sanctum::actingAs($user);
    $this->putJson('/api/protection/employer-benefits', [
        'employer_name' => 'Acme Ltd', 'none' => true, 'death_in_service_multiple' => 4,
    ])->assertOk()
        ->assertJsonPath('data.death_in_service_multiple', null)
        ->assertJsonPath('data.employer_name', 'Acme Ltd');

    expect(employerCardFires($user))->toBeFalse();
});

it('refuses values outside the columns and the offered choices', function () {
    Sanctum::actingAs(employedWithProfile());

    $this->putJson('/api/protection/employer-benefits', [
        'death_in_service_multiple' => 25,
        'group_ip_benefit_percent' => 150,
        'group_ip_definition' => 'suited',
    ])->assertStatus(422)->assertJsonValidationErrors(['death_in_service_multiple', 'group_ip_benefit_percent', 'group_ip_definition']);
});

it('only writes the signed-in user\'s profile', function () {
    $other = employedWithProfile();
    Sanctum::actingAs(employedWithProfile());

    $this->putJson('/api/protection/employer-benefits', ['death_in_service_multiple' => 3])->assertOk();

    expect($other->protectionProfile()->first()->death_in_service_multiple)->toBeNull();
});
