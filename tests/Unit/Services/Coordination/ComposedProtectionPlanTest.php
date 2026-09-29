<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(ProtectionActionDefinitionSeeder::class);
});

it('exposes the seeded protection strategy rows as metadata', function (): void {
    $rows = app(ProtectionStrategySource::class)->metadataRows();

    expect($rows->pluck('strategy_type')->sort()->values()->all())->toBe([
        'protection_critical_illness_gap',
        'protection_income_protection_gap',
        'protection_life_cover_gap',
        'protection_policy_in_trust',
        'protection_policy_review',
    ]);
});

it('locks every protection strategy whose required_data are unmet for a bare user', function (): void {
    // A bare user has no FamilyMember, no LifeInsurancePolicy, no income fields set.
    $user = User::factory()->create([
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(ProtectionStrategySource::class), $user);

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->sort()->values()->all();

    expect($lockedTypes)->toBe([
        'protection_critical_illness_gap',
        'protection_income_protection_gap',
        'protection_life_cover_gap',
        'protection_policy_in_trust',
        'protection_policy_review',
    ]);
});

it('unlocks trust and review strategies once a life insurance policy exists', function (): void {
    $user = User::factory()->create([
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);

    LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(ProtectionStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    // life_cover_in_force is now true — trust and review strategies unlock.
    expect($lockedTypes)->not->toContain('protection_policy_in_trust')
        ->and($lockedTypes)->not->toContain('protection_policy_review')
        // income_known and dependants_known are still unmet for this bare user.
        ->and($lockedTypes)->toContain('protection_life_cover_gap')
        ->and($lockedTypes)->toContain('protection_income_protection_gap')
        ->and($lockedTypes)->toContain('protection_critical_illness_gap');
});

/**
 * W-0401 — the plan recommended cover the non-owner already holds.
 *
 * This class's own docblock says it *"Mirrors ProtectionAgent::analyze() for the gap +
 * profile build"*. **The agent was routed to `LifeCoverReach` in W-0186; the mirror was
 * not**, so `:68` still read the plain `user_id` hasMany. A joint-life policy is recorded
 * once, on the account that entered it, so the non-owning spouse was assessed at £0 of
 * life cover and the engine duly recommended she buy some.
 *
 * Measured on the live `peak_earners` persona before the fix — one recommendation engine,
 * two input paths, opposite advice for one user at one moment:
 *
 * ```
 * ProtectionStrategySource->recommendations(Sarah)  ->  "Add decreasing term cover for debts"
 * ProtectionAgent::analyze(17)                      ->  debt_protection_gap = 0
 * ```
 *
 * **That is a recommendation to purchase a financial product she does not need, generated
 * from a figure the application itself knows is wrong** — an advice surface, not a summary
 * card, which is why this is filed as high rather than cosmetic.
 *
 * **The fixture is asymmetric and includes the non-owning side** (`tests/CLAUDE.md` §4,
 * Collision). Sarah holds £120,000 of her own, less than her £150,000 debt need, so the
 * two hypotheses separate cleanly:
 *
 * | Hypothesis | Sarah's life cover | a life cover card |
 * |---|---|---|
 * | **Correct** — the joint policy reaches her | **£620,000** | **absent** |
 * | `user_id`-only (the shipped bug) | £120,000 vs £150,000 of debt | **present** |
 *
 * David is the control: his £500,000 already exceeds his £200,000 debt need, so his
 * recommendations are identical under both hypotheses and **must not move**.
 */
function w0401Household(): array
{
    test()->seed(TaxConfigurationSeeder::class);

    $owner = User::factory()->create([
        'first_name' => 'David',
        'surname' => 'Jones',
        'marital_status' => 'married',
        'date_of_birth' => now()->subYears(45),
        'annual_employment_income' => 150000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_other_income' => 0,
    ]);
    $spouse = User::factory()->create([
        'first_name' => 'Sarah',
        'surname' => 'Jones',
        'marital_status' => 'married',
        'spouse_id' => $owner->id,
        'date_of_birth' => now()->subYears(43),
        'annual_employment_income' => 40000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_other_income' => 0,
    ]);
    $owner->update(['spouse_id' => $spouse->id]);

    ProtectionProfile::factory()->create([
        'user_id' => $owner->id,
        'annual_income' => 150000,
        'monthly_expenditure' => 3000,
        'mortgage_balance' => 200000,
        'other_debts' => 0,
        'number_of_dependents' => 0,
        'dependents_ages' => [],
        'death_in_service_multiple' => null,
        'group_ip_benefit_percent' => null,
        'group_ci_amount' => null,
    ]);
    ProtectionProfile::factory()->create([
        'user_id' => $spouse->id,
        'annual_income' => 40000,
        'monthly_expenditure' => 2000,
        'mortgage_balance' => 140000,
        'other_debts' => 10000,
        'number_of_dependents' => 0,
        'dependents_ages' => [],
        'death_in_service_multiple' => null,
        'group_ip_benefit_percent' => null,
        'group_ci_amount' => null,
    ]);

    LifeInsurancePolicy::factory()->create([
        'user_id' => $owner->id,
        'provider' => 'Vitality',
        'sum_assured' => 500000,
        'joint_life' => true,
        'in_trust' => true,
    ]);
    // Hers, single-life, and deliberately SHORT of her own debt need, so the bug and the
    // fix land on different recommendation sets rather than the same one.
    LifeInsurancePolicy::factory()->create([
        'user_id' => $spouse->id,
        'provider' => 'Aviva',
        'sum_assured' => 120000,
        'joint_life' => false,
        'in_trust' => false,
    ]);
    CriticalIllnessPolicy::factory()->create([
        'user_id' => $owner->id,
        'provider' => 'Vitality',
        'sum_assured' => 200000,
    ]);

    return compact('owner', 'spouse');
}

it('does not recommend life cover to the spouse a joint-life policy already covers', function (): void {
    ['spouse' => $spouse] = w0401Household();

    $types = collect(app(ProtectionStrategySource::class)->recommendations($spouse->fresh()))
        ->pluck('type');

    // The phantom. Present whenever the source reads the plain user_id relation.
    // Protection cards are the action definitions (CSJ 2026-09-29), so every
    // life cover rule is checked by its own key.
    // Gap rules are reasons on the position cards (CSJ 2026-09-29): her life position
    // must not be short, and no life reason may fire for her.
    $life = collect(app(ProtectionStrategySource::class)->recommendations($spouse->fresh()))->firstWhere('type', 'life_cover_position');
    expect($life?->extra['figures']['is_short'] ?? false)->toBeFalse()
        ->and($life?->extra['figures']['life_insurance_gap'] ?? false)->toBeFalse()
        ->and($life?->extra['figures']['mortgage_no_decreasing_term'] ?? false)->toBeFalse()
        // NOT empty: a source that returns [] on a failure would pass a naive absence
        // assertion. Her genuine income-protection gap proves the method ran.
        ->and($types)->not->toBeEmpty()
        ->and($types)->toContain('income_protection_position');
});

it('leaves the policy owner\'s recommendations unchanged', function (): void {
    ['owner' => $owner] = w0401Household();

    $types = collect(app(ProtectionStrategySource::class)->recommendations($owner->fresh()))
        ->pluck('type');

    // The control: his own policies reach him under both hypotheses. His joint policy is
    // in trust, so no trust card fires for it; her single-life Aviva policy is hers, so a
    // reader pulling his wife's policies in would raise hers on his list.
    expect($types)->not->toContain('policy_not_in_trust')
        ->and($types)->not->toContain('policy_not_joint_married')
        ->and($types)->toContain('income_protection_position');
});

it('builds protection cards from the action definitions, with each card\'s figures', function (): void {
    // CSJ 2026-09-29: the cards and the Protection Plan page read one catalogue.
    // A user with a profile, income and a mortgage but no policies gets the plan
    // page's "no policies" warning as a card, keyed by its definition.
    test()->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(40),
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_other_income' => 0,
        'annual_expenditure' => 30000,
    ]);
    ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'annual_income' => 60000,
        'monthly_expenditure' => 2500,
        'mortgage_balance' => 200000,
        'other_debts' => 0,
        'number_of_dependents' => 0,
        'dependents_ages' => [],
    ]);

    $recs = collect(app(ProtectionStrategySource::class)->recommendations($user->fresh()));
    $noPolicies = $recs->firstWhere('type', 'no_policies_warning');

    expect($noPolicies)->not->toBeNull()
        ->and($noPolicies->extra['definition_key'])->toBe('no_policies_warning')
        ->and($noPolicies->extra['figures']['total_gap'] ?? null)->toStartWith('£')
        ->and($recs->pluck('type'))->toContain('income_protection_position');
});
