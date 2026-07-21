<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('includes tax strategy recommendations for a user passing the tax gate', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    $taxRecs = array_values(array_filter($recs, fn ($r) => $r['module'] === 'tax'));
    expect($taxRecs)->not->toBeEmpty()
        ->and(array_column($taxRecs, 'recommendation_id'))->toContain('tax_isa_topup_vs_psa');

    // The new metadata fields survive formatting.
    $isaRec = collect($taxRecs)->firstWhere('recommendation_id', 'tax_isa_topup_vs_psa');
    expect($isaRec['claim_tier'])->toBe('mechanical')
        ->and($isaRec['sequence_position'])->toBeInt()
        ->and($isaRec['potential_benefit'])->toBeGreaterThan(0);
});

it('contributes no tax recommendations when the tax gate is closed', function () {
    $user = User::factory()->create([
        'employment_status' => null, 'annual_employment_income' => 0,
    ]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    expect(array_filter($recs, fn ($r) => $r['module'] === 'tax'))->toBe([]);
});

it('counts tax in the summary by_module', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'employment_status' => 'full_time',
        'annual_employment_income' => 110000, 'monthly_expenditure' => 3000,
    ]);

    $summary = app(RecommendationsAggregatorService::class)->getSummary($user->id);

    expect($summary['by_module'])->toHaveKey('tax');
});
