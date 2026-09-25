<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function headlinePlan(User $user): array
{
    return app(ComposedTaxPlanService::class)->forUser($user);
}

it('keeps the Lifetime ISA card but does not count its bonus as tax saved', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 30000,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(30)->toDateString(),
    ]);

    $lisa = collect(headlinePlan($user)['items'])->firstWhere('type', 'lifetime_isa');

    expect($lisa)->not->toBeNull()
        ->and($lisa['estimated_annual_tax_saved'])->toBeNull()
        ->and($lisa['government_bonus'])->toBeGreaterThan(0);
});

it('sums only real tax savings, counting ISA top-up and the savings gift once', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 60000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_savings_balance' => 0]);
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 150000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $plan = headlinePlan($user);
    $items = collect($plan['items'])->keyBy('type');

    expect($items->has('isa_topup_vs_psa'))->toBeTrue()
        ->and($items->has('savings_to_spouse'))->toBeTrue();

    // The three sole-name-interest items all conflict with each other (a
    // triangle): only the largest counts, and the others name it by title.
    $trio = collect($plan['items'])
        ->whereIn('type', ['isa_topup_vs_psa', 'savings_to_spouse', 'joint_savings_psa_split']);
    $winner = $trio->sortByDesc('estimated_annual_tax_saved')->first();

    foreach ($trio->reject(fn ($i) => $i['type'] === $winner['type']) as $loser) {
        expect($loser['conflict_note'])->toBeString()
            ->and($loser['conflict_note'])->toContain($winner['title'])
            ->and($loser['conflict_note'])->not->toContain('_');
    }

    $expected = collect($plan['items'])
        ->reject(fn ($i) => $trio->pluck('type')->contains($i['type']))
        ->sum(fn ($i) => (float) ($i['estimated_annual_tax_saved'] ?? 0))
        + (float) $winner['estimated_annual_tax_saved'];
    expect($plan['combined_annual_saving'])->toBe(round($expected, 2));
});
