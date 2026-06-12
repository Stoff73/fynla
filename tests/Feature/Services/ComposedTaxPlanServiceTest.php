<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('produces a composed plan with locked strategies for a thin user', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    expect($plan)->toHaveKeys(['items', 'combined_annual_saving', 'locked'])
        // No pension records captured → salary_sacrifice_ni must appear LOCKED, not vanish.
        ->and(array_column($plan['locked'], 'strategy_type'))->toContain('salary_sacrifice_ni');
});

it('does not list a strategy as locked when it actually fired', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    // £110k user with £81k non-ISA savings at 3.25% → annual interest ≈ £2,632
    // which exceeds the £500 PSA for an additional-rate payer, and transferable
    // ≈ £19,900 (capped by remaining ISA room) > the £1,000 fire floor.
    // → isa_topup_vs_psa FIRES (appears in items) and must NOT appear in locked.
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'annual_employment_income' => 110000,
        'employment_status' => 'full_time',
        'marital_status' => 'married',
    ]);

    // Non-ISA savings account — generates interest above PSA
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'is_isa' => false,
        'current_balance' => 81000.00,
        'interest_rate' => 0.0325,
    ]);

    // ISA account with £100 subscribed so isa_subscriptions_ytd is available
    // and there is remaining ISA allowance (£20,000 − £100 = £19,900 room)
    SavingsAccount::factory()->isa()->create([
        'user_id' => $user->id,
        'current_balance' => 5000.00,
        'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
        'isa_subscription_amount' => 100.00,
    ]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    $firedTypes = array_column($plan['items'], 'type');
    $lockedTypes = array_column($plan['locked'], 'strategy_type');

    expect($firedTypes)->toContain('isa_topup_vs_psa')
        ->and($lockedTypes)->not->toContain('isa_topup_vs_psa');
});

it('never double-counts the shared ISA allowance in combined_annual_saving', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    // 30-year-old higher-rate user: lifetime_isa (£1,000 bonus) and
    // isa_topup_vs_psa both fire and draw on the SAME £20,000 allowance.
    // The winner keeps full sizing; the wrap is re-evaluated against the
    // remaining £16,000 → £256, so the combined figure must reflect ONE
    // allowance pool, never the same capacity counted twice.
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 60000,
        'date_of_birth' => now()->subYears(30),
        'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'is_isa' => false,
        'current_balance' => 50000,
        'interest_rate' => 0.04,
    ]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    $items = collect($plan['items']);
    $lisa = $items->firstWhere('type', 'lifetime_isa');
    $topup = $items->firstWhere('type', 'isa_topup_vs_psa');

    expect($lisa['estimated_annual_tax_saved'])->toBe(1000.0)
        ->and($topup['estimated_annual_tax_saved'])->toBe(256.0)
        ->and($topup['conflict_note'])->toContain('ISA allowance');

    // The combined total equals the sum of realisable (non-excluded) savings —
    // proving the constrained figure, not the pass-1 inflated one, is counted.
    $expected = round((float) $items
        ->filter(fn (array $i) => ($i['isa_allowance_excluded'] ?? false) !== true)
        ->sum(fn (array $i) => (float) ($i['estimated_annual_tax_saved'] ?? 0)), 2);
    expect($plan['combined_annual_saving'])->toBe($expected);
});

it('drops a pool-exhausted ISA strategy from the combined total but keeps it noted in the plan', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    // £15,500 already subscribed → £4,500 pool. lifetime_isa draws £4,000,
    // leaving £500 — below isa_topup's £1,000 floor → excluded + noted.
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 60000,
        'date_of_birth' => now()->subYears(30),
        'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'is_isa' => false,
        'current_balance' => 50000,
        'interest_rate' => 0.04,
    ]);
    SavingsAccount::factory()->isa()->create([
        'user_id' => $user->id,
        'current_balance' => 15500,
        'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
        'isa_subscription_amount' => 15500,
    ]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    $items = collect($plan['items']);
    $topup = $items->firstWhere('type', 'isa_topup_vs_psa');

    expect($topup)->not->toBeNull()
        ->and($topup['isa_allowance_excluded'])->toBeTrue()
        ->and($topup['conflict_note'])->toContain('same ISA allowance');

    // Excluded from the total: combined equals the sum WITHOUT the flagged item.
    $expected = round((float) $items
        ->filter(fn (array $i) => ($i['isa_allowance_excluded'] ?? false) !== true)
        ->sum(fn (array $i) => (float) ($i['estimated_annual_tax_saved'] ?? 0)), 2);
    expect($plan['combined_annual_saving'])->toBe($expected)
        ->and($plan['combined_annual_saving'])->toBeLessThan(
            $expected + (float) $topup['estimated_annual_tax_saved']
        );
});

it('marks disabled catalogue rows as neither fired nor locked', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    TaxActionDefinition::where('strategy_type', 'salary_sacrifice_ni')->update(['is_enabled' => false]);

    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
    ]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    expect(array_column($plan['locked'], 'strategy_type'))->not->toContain('salary_sacrifice_ni');
});
