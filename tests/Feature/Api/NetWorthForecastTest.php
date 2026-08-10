<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\Mortgage;
use App\Models\SavingsAccount;
use App\Models\User;

it('requires authentication for every net worth forecast endpoint', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/net-worth/forecast'],
    ['PUT', '/api/net-worth/forecast/assumptions'],
    ['DELETE', '/api/net-worth/forecast/assumptions'],
]);

it('returns recorded year zero and projected points without writing forecast history', function (): void {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $user->id,
        'current_value' => 30_000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    InvestmentAccountValueSnapshot::query()->create([
        'investment_account_id' => $account->id,
        'column_name' => 'current_value_gbp',
        'value' => 30_000,
        'currency' => 'GBP',
        'value_gbp' => 30_000,
        'taken_at' => now(),
        'trigger_reason' => 'update',
        'ingest_source' => 'form',
    ]);
    $snapshotCount = InvestmentAccountValueSnapshot::query()->count();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/net-worth/forecast?years=1');

    $response->assertOk()
        ->assertJsonPath('data.contract_version', 'net_worth_forecast_v1')
        ->assertJsonPath('data.current.assets.investments', 30_000)
        ->assertJsonPath('data.points.0.source', 'recorded')
        ->assertJsonPath('data.points.0.net_worth', 30_000)
        ->assertJsonPath('data.points.1.source', 'projected')
        ->assertJsonPath('data.methodology.forecast_points_written_to_recorded_history', false)
        ->assertJsonCount(2, 'data.points');

    expect(InvestmentAccountValueSnapshot::query()->count())->toBe($snapshotCount);
});

it('discloses recorded recurring contributions and known mortgage principal repayments', function (): void {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 10_000,
        'regular_contribution_amount' => 100,
        'contribution_frequency' => 'monthly',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    InvestmentAccount::factory()->gia()->create([
        'user_id' => $user->id,
        'current_value' => 20_000,
        'monthly_contribution_amount' => 200,
        'contribution_frequency' => 'quarterly',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 30_000,
        'annual_contribution_gbp' => 3_000,
    ]);
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 200_000,
        'monthly_payment' => 1_200,
        'monthly_interest_portion' => 700,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/net-worth/forecast?years=1')
        ->assertOk()
        ->assertJsonPath('data.cash_flows.annual_contributions.cash', 1_200)
        ->assertJsonPath('data.cash_flows.annual_contributions.investments', 800)
        ->assertJsonPath('data.cash_flows.annual_contributions.pensions', 3_000)
        ->assertJsonPath('data.cash_flows.annual_repayments.mortgages', 6_000);
});

it('uses the recorded interest portion once for a mixed mortgage repayment', function (): void {
    $user = User::factory()->create();
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'mortgage_type' => 'mixed',
        'repayment_percentage' => 60,
        'interest_only_percentage' => 40,
        'outstanding_balance' => 200_000,
        'monthly_payment' => 1_200,
        'monthly_interest_portion' => 700,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/net-worth/forecast?years=1')
        ->assertOk()
        ->assertJsonPath('data.cash_flows.annual_repayments.mortgages', 6_000);
});

it('updates and resets the authenticated users assumptions', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/net-worth/forecast/assumptions', [
            'property' => 4.25,
            'basis' => 'real',
            'effective_from' => '2026-08-10',
        ])
        ->assertOk()
        ->assertJsonPath('data.property.rate_percent', 4.25)
        ->assertJsonPath('data.property.source', 'user_override')
        ->assertJsonPath('data.property.effective_from', '2026-08-10')
        ->assertJsonPath('data.property.basis', 'real');

    $this->deleteJson('/api/net-worth/forecast/assumptions')
        ->assertOk()
        ->assertJsonPath('data.property.rate_percent', 3)
        ->assertJsonPath('data.property.source', 'system_default')
        ->assertJsonPath('data.property.basis', 'nominal');
});

it('returns validation errors for invalid forecast assumptions', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/net-worth/forecast/assumptions', [
            'property' => 31,
            'basis' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['property', 'basis']);
});
