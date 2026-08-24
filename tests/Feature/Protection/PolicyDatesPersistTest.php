<?php

declare(strict_types=1);

use App\Http\Requests\Protection\BasePolicyRequest;
use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * W-0026 — a policy end date was validated, accepted, answered 201 and then
 * discarded by mass assignment on four of the five policy types, because only
 * LifeInsurancePolicy listed `policy_end_date` in $fillable.
 *
 * W-0027 — a life policy could name only one beneficiary and had no joint-life
 * flag on any entry surface, though the column and both consumers exist.
 */
$policyModels = [
    'life' => ['model' => LifeInsurancePolicy::class, 'table' => 'life_insurance_policies'],
    'critical illness' => ['model' => CriticalIllnessPolicy::class, 'table' => 'critical_illness_policies'],
    'income protection' => ['model' => IncomeProtectionPolicy::class, 'table' => 'income_protection_policies'],
    'disability' => ['model' => DisabilityPolicy::class, 'table' => 'disability_policies'],
    'sickness illness' => ['model' => SicknessIllnessPolicy::class, 'table' => 'sickness_illness_policies'],
];

describe('Protection policy models accept everything the shared request validates', function () use ($policyModels) {
    it('makes every validated column mass assignable', function (string $modelClass, string $table) {
        $request = new class extends BasePolicyRequest
        {
            public function rules(): array
            {
                return $this->commonRules();
            }
        };

        $model = new $modelClass;

        foreach (array_keys($request->rules()) as $field) {
            // Income protection has no policy_term_years column; a field the
            // table cannot hold is not a fillable gap.
            if (! Schema::hasColumn($table, $field)) {
                continue;
            }

            expect($model->isFillable($field))->toBeTrue(
                "{$modelClass} validates {$field} and the column exists, but mass assignment discards it."
            );
        }
    })->with(array_map(
        fn (array $spec) => [$spec['model'], $spec['table']],
        $policyModels
    ));

    it('casts both policy dates to dates', function (string $modelClass) {
        $casts = (new $modelClass)->getCasts();

        expect($casts['policy_start_date'] ?? null)->toBe('date')
            ->and($casts['policy_end_date'] ?? null)->toBe('date');
    })->with(array_map(fn (array $spec) => [$spec['model']], $policyModels));
});

describe('Protection API persists the policy end date', function () {
    it('stores the end date on a critical illness policy', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/protection/policies/critical-illness', [
            'provider' => 'Legal & General',
            'policy_type' => 'standalone',
            'sum_assured' => 200000,
            'premium_amount' => 125,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('critical_illness_policies', [
            'user_id' => $user->id,
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ]);
    });

    it('stores the end date on an income protection policy', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/protection/policies/income-protection', [
            'provider' => 'Aviva',
            'benefit_amount' => 3000,
            'benefit_frequency' => 'monthly',
            'premium_amount' => 45,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('income_protection_policies', [
            'user_id' => $user->id,
            'policy_end_date' => '2040-01-01',
        ]);
    });

    it('stores the end date on a disability policy', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/protection/policies/disability', [
            'provider' => 'Vitality',
            'benefit_amount' => 2000,
            'benefit_frequency' => 'monthly',
            'premium_amount' => 30,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('disability_policies', [
            'user_id' => $user->id,
            'policy_end_date' => '2040-01-01',
        ]);
    });

    it('stores the end date on a sickness illness policy', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/protection/policies/sickness-illness', [
            'provider' => 'Aviva',
            'benefit_amount' => 1500,
            'benefit_frequency' => 'monthly',
            'premium_amount' => 20,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('sickness_illness_policies', [
            'user_id' => $user->id,
            'policy_end_date' => '2040-01-01',
        ]);
    });

    it('stores the end date on a life policy', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/protection/policies/life', [
            'provider' => 'Vitality',
            'policy_type' => 'level_term',
            'sum_assured' => 500000,
            'premium_amount' => 85,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('life_insurance_policies', [
            'user_id' => $user->id,
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
        ]);
    });
});

describe('Life policies record joint life and every beneficiary', function () {
    it('stores the joint life flag and a multi-beneficiary split', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/protection/policies/life', [
            'provider' => 'Vitality',
            'policy_type' => 'level_term',
            'sum_assured' => 500000,
            'premium_amount' => 85,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2020-01-01',
            'policy_end_date' => '2040-01-01',
            'in_trust' => true,
            'joint_life' => true,
            'beneficiaries' => 'Sarah Jones: 50%, William Jones: 25%, Charlotte Jones: 25%',
        ])->assertStatus(201);

        $policy = LifeInsurancePolicy::where('user_id', $user->id)->firstOrFail();

        expect($policy->joint_life)->toBeTrue()
            ->and($policy->in_trust)->toBeTrue()
            ->and($policy->beneficiaries)->toBe('Sarah Jones: 50%, William Jones: 25%, Charlotte Jones: 25%');
    });

    it('exposes the joint life flag through the API resource', function () {
        $user = User::factory()->create();
        LifeInsurancePolicy::create([
            'user_id' => $user->id,
            'policy_type' => 'level_term',
            'provider' => 'Vitality',
            'sum_assured' => 500000,
            'joint_life' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/protection')
            ->assertStatus(200)
            ->assertJsonPath('data.policies.life_insurance.0.joint_life', true);
    });

    it('updates the joint life flag on an existing policy', function () {
        $user = User::factory()->create();
        $policy = LifeInsurancePolicy::create([
            'user_id' => $user->id,
            'policy_type' => 'level_term',
            'provider' => 'Vitality',
            'sum_assured' => 500000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/protection/policies/life/{$policy->id}", ['joint_life' => true])
            ->assertStatus(200);

        expect($policy->fresh()->joint_life)->toBeTrue();
    });
});
