<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('publishes server-calculated protection gaps with inputs assumptions explanations and policy references', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(40),
        'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_other_income' => 0,
    ]);
    ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'annual_income' => 60000,
        'monthly_expenditure' => 2500,
        'mortgage_balance' => 140000,
        'other_debts' => 10000,
        'number_of_dependents' => 1,
        'dependents_ages' => [10],
    ]);
    $policy = LifeInsurancePolicy::factory()->create([
        'user_id' => $user->id,
        'provider' => 'Canonical Life',
        'sum_assured' => 100000,
    ]);
    Sanctum::actingAs($user);

    $presentation = $this->getJson('/api/protection')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data.coverage_gaps');

    $debt = collect($presentation['categories'])->firstWhere('key', 'debt_protection');
    $income = collect($presentation['categories'])->firstWhere('key', 'income_protection');

    expect($presentation['contract_version'])->toBe('protection_gap_v1')
        ->and($presentation)->toHaveKeys(['totals', 'categories', 'calculated_at'])
        ->and($debt)->toMatchArray([
            'label' => 'Debt protection',
            'need' => 150000,
            'cover' => 100000,
            'shortfall' => 50000,
            'status' => 'gap',
        ])
        ->and($debt['severity'])->toBeIn(['low', 'medium', 'high'])
        ->and($debt['explanation'])->toContain('debt')
        ->and($debt['inputs'])->toHaveKeys(['mortgage_balance', 'other_debts'])
        ->and($debt['assumptions'])->not->toBeEmpty()
        ->and($debt['relevant_policies'][0])->toMatchArray([
            'id' => $policy->id,
            'type' => 'life_insurance',
            'provider' => 'Canonical Life',
            'cover' => 100000,
        ])
        ->and($income)->toHaveKeys(['need', 'cover', 'shortfall', 'inputs', 'assumptions', 'explanation'])
        ->and(collect($presentation['categories'])->whereIn('key', [
            'human_capital',
            'debt_protection',
            'final_expenses',
            'education_funding',
        ])->sum('need'))->toEqual($presentation['totals']['need']);
});

/**
 * W-0384 — the coverage TOTAL reaches the other life assured, not just the policy list.
 *
 * `/m` showed Sarah **"Total lump-sum cover £0 · Across 1 policy"** directly above the
 * £500,000 policy that card was counting, and derived HIGH debt-protection and
 * final-expenses shortfalls from the £0 — while web told her at the same moment that she
 * had no shortfall at all. Two mechanisms behind one card: the count came from the
 * reach-aware policy list W-0186 fixed, the total from `coverage_gaps.totals.cover`,
 * which W-0186 never reached. **This is W-0186's own defect shape in a second place, and
 * this time the total is the wrong half.**
 *
 * Web was right by accident of path, not by agreement: `/protection` reads
 * `ProtectionAgent::analyze()` (routed in W-0186); `/m` and iOS read this payload from
 * `ProtectionGapPresentationService` (not routed). Two mechanisms answering one question
 * is the disease; both now read `LifeCoverReach::policiesCovering()`.
 *
 * **The fixture is asymmetric on purpose, and it must include the non-owning side**
 * (`tests/CLAUDE.md` §4, Collision). One joint policy is one number seen from two
 * directions, so £500,000 is the answer BOTH when the reach works and when each account
 * sees only its own half — a fixture built from the policy's owner cannot fail, which is
 * exactly how this shipped. Sarah therefore holds £120,000 of her own, single-life, so
 * every hypothesis lands on a different number:
 *
 * | Hypothesis | Sarah's `totals.cover` | David's `totals.cover` |
 * |---|---|---|
 * | Correct — life reaches, critical illness does not | **£620,000** | **£700,000** |
 * | `user_id`-only (the shipped bug) | £120,000 | £700,000 |
 * | Critical illness reached too | £820,000 | £700,000 |
 * | Reach applied to the owner as well | £620,000 | £820,000 |
 *
 * David is the control in every case: his answer moving at all would mean the reader
 * pulled whatever his spouse holds rather than only what covers him.
 */
function w0384Household(): array
{
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

    // His, joint-life: the one contract covering both of them, recorded once.
    $jointPolicy = LifeInsurancePolicy::factory()->create([
        'user_id' => $owner->id,
        'provider' => 'Vitality',
        'sum_assured' => 500000,
        'joint_life' => true,
        'in_trust' => true,
    ]);

    // Hers, single-life. It separates the hypotheses AND proves the reach is limited to
    // joint-life policies — it must never appear in his answer.
    $spouseOwnPolicy = LifeInsurancePolicy::factory()->create([
        'user_id' => $spouse->id,
        'provider' => 'Aviva',
        'sum_assured' => 120000,
        'joint_life' => false,
        'in_trust' => false,
    ]);

    // His critical illness. `critical_illness_policies` has no `joint_life` column, no
    // `joint_owner_id` and no ownership columns at all (verified with `SHOW COLUMNS`),
    // so it covers only him. If it ever reached her, her total reads £820,000.
    $criticalIllness = CriticalIllnessPolicy::factory()->create([
        'user_id' => $owner->id,
        'provider' => 'Vitality',
        'sum_assured' => 200000,
    ]);

    return compact('owner', 'spouse', 'jointPolicy', 'spouseOwnPolicy', 'criticalIllness');
}

function w0384GapsFor(User $user): array
{
    Sanctum::actingAs($user);

    return test()->getJson('/api/protection')
        ->assertOk()
        ->json('data.coverage_gaps');
}

it('counts a joint-life policy in the coverage total of the life assured who does not own it', function () {
    ['spouse' => $spouse] = w0384Household();

    $gaps = w0384GapsFor($spouse);
    $debt = collect($gaps['categories'])->firstWhere('key', 'debt_protection');
    $finalExpenses = collect($gaps['categories'])->firstWhere('key', 'final_expenses');

    // £620,000 = her own £120,000 + the £500,000 joint-life policy on David's account.
    // £120,000 is the shipped bug; £820,000 would mean critical illness reached too.
    expect((float) $gaps['totals']['cover'])->toBe(620000.0)
        // Her debt need is met in full, so the HIGH shortfall `/m` showed her is gone.
        // Debt is allocated FIRST, so this holds whatever the human-capital need is.
        ->and((float) $debt['need'])->toBe(150000.0)
        ->and((float) $debt['cover'])->toBe(150000.0)
        ->and((float) $debt['shortfall'])->toBe(0.0)
        ->and($debt['status'])->toBe('covered')
        ->and($debt['severity'])->toBe('none')
        // The second HIGH shortfall derived from the £0, cleared by the excess.
        ->and((float) $finalExpenses['shortfall'])->toBe(0.0)
        ->and($finalExpenses['status'])->toBe('covered')
        ->and((float) $gaps['totals']['shortfall'])->toBe(0.0);
});

it('leaves the policy owner untouched when the reach is applied to his spouse', function () {
    ['owner' => $owner] = w0384Household();

    $gaps = w0384GapsFor($owner);
    $debt = collect($gaps['categories'])->firstWhere('key', 'debt_protection');

    // £700,000 = his £500,000 life + his £200,000 critical illness. £820,000 here would
    // mean the reader pulled his wife's single-life £120,000 into his own cover.
    expect((float) $gaps['totals']['cover'])->toBe(700000.0)
        ->and((float) $debt['need'])->toBe(200000.0)
        ->and((float) $debt['cover'])->toBe(200000.0)
        ->and((float) $debt['shortfall'])->toBe(0.0);
});

it('does not reach critical illness to the other life, because the table has nothing to reach with', function () {
    ['spouse' => $spouse] = w0384Household();

    $gaps = w0384GapsFor($spouse);

    // Her total carries no trace of his £200,000: 620,000, not 820,000.
    expect((float) $gaps['totals']['cover'])->toBe(620000.0)
        ->and((float) $gaps['totals']['cover'])->not->toBe(820000.0);
});

it('lists every policy covering the non-owner behind each lump-sum gap, and withholds the contract details', function () {
    ['spouse' => $spouse, 'jointPolicy' => $jointPolicy, 'spouseOwnPolicy' => $spouseOwnPolicy] = w0384Household();

    $gaps = w0384GapsFor($spouse);
    $debt = collect($gaps['categories'])->firstWhere('key', 'debt_protection');
    $references = collect($debt['relevant_policies']);

    // The other half of the same card. A total of £620,000 sitting above a policy list
    // of one — or of none, as shipped — is the disagreement this item is about, so both
    // halves are asserted together and both are built from one read of the reach.
    expect($references->pluck('id')->sort()->values()->all())
        ->toBe(collect([$spouseOwnPolicy->id, $jointPolicy->id])->sort()->values()->all())
        ->and((float) $references->sum('cover'))->toBe(620000.0);

    // W-0383 withheld `policy_number` and `beneficiaries` from the non-owner because a
    // policy number is effectively a credential and `beneficiaries` commonly names the
    // couple's children. This payload is a second route to the same policy, so it is
    // checked here rather than assumed to be safe.
    $reached = $references->firstWhere('id', $jointPolicy->id);
    expect(array_keys($reached))->toBe(['id', 'type', 'provider', 'name', 'cover'])
        ->and($reached)->not->toHaveKey('policy_number')
        ->and($reached)->not->toHaveKey('beneficiaries');
});

it('does not move the income-protection gap, which the life-cover reach cannot touch', function () {
    ['spouse' => $spouse] = w0384Household();

    $gaps = w0384GapsFor($spouse);
    $income = collect($gaps['categories'])->firstWhere('key', 'income_protection');

    // The item reported three HIGH shortfalls derived from the £0. Two were false and
    // are now closed; this one is genuine — she holds no income-protection policy, and
    // `income_protection_policies` is not reachable, so it must survive the fix intact.
    expect((float) $income['need'])->toBeGreaterThan(0.0)
        ->and((float) $income['cover'])->toBe(0.0)
        ->and((float) $income['shortfall'])->toBe((float) $income['need'])
        ->and($income['status'])->toBe('gap');
});
