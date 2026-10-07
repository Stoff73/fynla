<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Estate\LifeCoverCalculator;
use App\Services\Tax\TaxActionDefinitionService;
use App\Services\Tax\TaxOptimisationService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0480 — a civil partnership is a marriage in all four of these services.
 *
 * W-0474 fixed `IHTCalculationService`, which read `['married']` alone. Four siblings
 * read it alone too, so the same household saw a corrected Inheritance Tax figure beside
 * life cover, an estate plan and two tax strategies still computed as though they were
 * single. Each now reads `HouseholdPooling::hasSpousalStatus()`.
 *
 * **The assertion is parity, not a fixed number.** Each test builds the identical
 * household twice — once `married`, once `civil_partnership` — and requires the same
 * answer, then requires `single` to still get the other one. A number pinned here would
 * pass just as well if both branches were broken in the same direction.
 *
 * Before this change, every `civil_partnership` expectation below failed and produced
 * exactly the `single` answer.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/** A household with a spouse on both sides, at the given marital status. */
function w0480Household(string $status, int $userIncome = 80000, int $spouseIncome = 20000): array
{
    $spouse = User::factory()->create([
        'marital_status' => $status,
        'annual_employment_income' => $spouseIncome,
        'date_of_birth' => '1970-07-19',
    ]);

    $user = User::factory()->create([
        'marital_status' => $status,
        'annual_employment_income' => $userIncome,
        'date_of_birth' => '1968-03-04',
        'spouse_id' => $spouse->id,
    ]);

    $spouse->update(['spouse_id' => $user->id]);

    return [$user->fresh(), $spouse->fresh()];
}

describe('LifeCoverCalculator', function () {
    it('warns a civil partnership about a single life policy, as it warns a marriage', function () {
        $calculator = app(LifeCoverCalculator::class);

        $warn = function (string $status) use ($calculator) {
            [$user] = w0480Household($status);
            $policy = LifeInsurancePolicy::factory()->create([
                'user_id' => $user->id,
                'joint_life' => false,
                'in_trust' => true,
                'policy_type' => 'whole_of_life',
            ]);

            $assessment = $calculator->assessExistingPolicies(collect([$policy]), $user);

            return collect($assessment['warnings'] ?? [])
                ->contains(fn ($w) => $w['type'] === 'single_life_married');
        };

        expect($warn('civil_partnership'))->toBeTrue()
            ->and($warn('married'))->toBeTrue()
            ->and($warn('single'))->toBeFalse();
    });
});

describe('TaxOptimisationService', function () {
    it('offers a civil partnership the spousal strategy it offers a marriage', function () {
        $service = app(TaxOptimisationService::class);

        $strategy = function (string $status) use ($service) {
            [$user] = w0480Household($status);

            return collect($service->generateStrategies($user)['strategies'])
                ->firstWhere('type', 'spousal_optimisation');
        };

        $civil = $strategy('civil_partnership');
        $married = $strategy('married');

        expect($civil)->not->toBeNull()
            ->and($civil['details']['user_tax_band'])->toBe('higher')
            ->and($civil['details']['spouse_tax_band'])->toBe('basic')
            ->and($civil['estimated_annual_saving'])->toBe($married['estimated_annual_saving'])
            ->and($strategy('single'))->toBeNull();
    });
});

describe('TaxActionDefinitionService', function () {
    it('fires the spousal transfer action for a civil partnership', function () {
        $this->seed(TaxActionDefinitionSeeder::class);
        // The seeder disables orphaned agent rows by design; this exercises the
        // evaluator directly, as TaxActionDefinitionServiceTest does.
        TaxActionDefinition::where('source', 'agent')->update(['is_enabled' => true]);

        $service = app(TaxActionDefinitionService::class);

        $fires = function (string $status) use ($service) {
            [$user] = w0480Household($status);

            return collect($service->evaluateActions($user)['recommendations'])
                ->contains(fn ($r) => ($r['definition_key'] ?? '') === 'spousal_transfer_beneficial');
        };

        expect($fires('civil_partnership'))->toBeTrue()
            ->and($fires('married'))->toBeTrue()
            ->and($fires('single'))->toBeFalse();
    });
});
