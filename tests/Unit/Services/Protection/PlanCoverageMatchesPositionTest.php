<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('gives the cards the same need, cover and shortfall as the cover position', function () {
    $user = User::factory()->create(['employment_status' => 'employed', 'annual_employment_income' => 60000, 'annual_self_employment_income' => 0,
        'annual_rental_income' => 0, 'annual_dividend_income' => 0, 'annual_other_income' => 0, 'annual_expenditure' => 30000,
        'date_of_birth' => now()->subYears(40)]);
    ProtectionProfile::factory()->create(['user_id' => $user->id, 'annual_income' => 60000, 'monthly_expenditure' => 2500,
        'mortgage_balance' => 0, 'other_debts' => 0, 'number_of_dependents' => 0, 'dependents_ages' => []]);
    CriticalIllnessPolicy::factory()->create(['user_id' => $user->id, 'sum_assured' => 50000]);

    $plan = app(ComprehensiveProtectionPlanService::class)->generateComprehensiveProtectionPlan($user->fresh());
    $ci = $plan['coverage_analysis']['critical_illness'];
    $position = $plan['cover_position']['critical_illness'];

    // 3 x £60,000 from configuration, not a literal.
    expect($position['need'])->toBe(180000.0)
        ->and((float) $ci['need'])->toBe($position['need'])
        ->and((float) $ci['coverage'])->toBe($position['total_cover'])
        ->and((float) $ci['gap'])->toBe($position['short_by'])
        ->and((float) $plan['coverage_analysis']['life_insurance']['gap'])->toBe($plan['cover_position']['life']['short_by']);
});
