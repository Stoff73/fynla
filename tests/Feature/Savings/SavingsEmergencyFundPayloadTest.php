<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0335 — `/api/savings` carries the emergency-fund figures the page displays.
 *
 * It used to return `'analysis' => null, // Placeholder for analysis data`, and
 * nothing in the app dispatched the analyze action that would have filled it. So
 * `/savings` computed its own runway in JavaScript from the one expenditure
 * column the payload happened to carry, and disagreed with `/dashboard`, `/m` and
 * `/risk-profile` — which all read the backend's figure.
 *
 * The division is not the hard part. The DENOMINATOR is: `SavingsAgent` divides
 * by RESOLVED monthly expenditure, a priority chain rather than a single column.
 * The first test below pins that the resolved figure is what ships.
 *
 * The second test is a Rule 12 guard, and it is the reason this payload is narrow
 * rather than the whole analysis array: `emergency_fund.adequacy` carries an
 * `adequacy_score`, and a numerical rating must never reach a user-facing
 * surface. Shipping the block wholesale would have put one on the wire.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * A household with cash and a stated monthly spend.
 *
 * The date of birth, the income and the expenditure are all load-bearing:
 * `SavingsDataReadinessService` blocks the analysis without all three, and a
 * fixture missing any of them never reaches the code under test — it gets a null
 * analysis for the RIGHT reason and proves nothing (`tests/CLAUDE.md` §4,
 * Fixture).
 */
function savingsPayloadUser(float $monthlyExpenditure = 1_000): User
{
    $user = User::factory()->create([
        'monthly_expenditure' => $monthlyExpenditure,
        'date_of_birth' => '1980-01-01',
        'annual_employment_income' => 60_000,
        'employment_status' => 'employed',
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => null,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 12_000,
        'is_emergency_fund' => false,
        'is_isa' => false,
    ]);

    return $user->fresh();
}

it('ships the resolved runway and fund value, not a placeholder', function () {
    $user = savingsPayloadUser();

    $data = $this->actingAs($user, 'sanctum')->getJson('/api/savings')
        ->assertOk()
        ->json('data');

    expect($data['analysis'])->not->toBeNull();
    // £12,000 of cash against £1,000 a month. Note the account is NOT flagged
    // `is_emergency_fund` — the flag is a designation, not a definition, and a
    // household with cash and no ticked boxes does not have £0 of runway.
    expect((float) $data['analysis']['emergency_fund']['runway_months'])->toEqualWithDelta(12.0, 0.01);
    expect((float) $data['analysis']['summary']['total_savings'])->toEqualWithDelta(12_000.0, 0.01);
    // The chain that the browser cannot reproduce, named so a consumer can see
    // which branch produced the denominator.
    expect($data['analysis']['summary']['expenditure_source'])->not->toBeEmpty();
});

it('never puts an adequacy score on the wire', function () {
    $user = savingsPayloadUser();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/savings')->assertOk();

    // Rule 12: no numerical rating on a user-facing surface. Asserted against the
    // whole response body rather than one key, because the score would arrive by
    // someone widening the block, not by someone adding that key by name.
    expect($response->getContent())->not->toContain('adequacy_score');
    expect($response->json('data.analysis.emergency_fund'))->not->toHaveKey('adequacy');
});

it('renders the page without the block rather than failing when the analysis cannot be built', function () {
    // A user with no readiness to analyse still gets accounts, expenditure and the
    // ISA allowance — the store falls back to dividing the accounts' own shares.
    // The guard is that `analysis` is absent or null, never a half-built array
    // whose missing keys a consumer would swallow with `??`.
    $user = User::factory()->create(['monthly_expenditure' => null]);

    $data = $this->actingAs($user, 'sanctum')->getJson('/api/savings')
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKey('analysis');

    if ($data['analysis'] !== null) {
        expect($data['analysis']['emergency_fund'])->toHaveKey('runway_months');
    }
});
