<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Tiers\TeaserGate;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * W-0532. `family_module` and `benefits_child` appeared in the pricing comparison
 * and were read by nothing — the `investments_exotic` shape, sold and enforced
 * nowhere.
 *
 * Both are `full` on every tier today, so nothing is withheld from anyone right
 * now. The defect was that the enforcement point did not exist, so the day the
 * matrix changed the comparison would keep promising and the app would keep
 * giving. These drive a tier that does not carry the capability, which is the
 * only way to see whether a gate is really there.
 */
$withoutCapability = function (string $key): User {
    $user = User::factory()->create(['tier' => 'free', 'is_admin' => false, 'is_preview_user' => false]);

    // The gate reads the resolved tier's matrix, so withhold it there rather than
    // stubbing the gate — a stubbed gate would pass whether or not it is called.
    $config = \App\Models\TierConfiguration::where('tier', 'free')->firstOrFail();
    $matrix = $config->capability_matrix;
    $matrix[$key] = 'none';
    $config->update(['capability_matrix' => $matrix]);

    return $user;
};

describe('family_module', function () use ($withoutCapability) {
    it('refuses a family member over HTTP when the tier does not carry it', function () use ($withoutCapability) {
        $user = $withoutCapability('family_module');
        Sanctum::actingAs($user);

        $this->postJson('/api/user/family-members', [
            'first_name' => 'Ruth',
            'last_name' => 'Chen',
            'relationship' => 'child',
        ])->assertStatus(403)->assertJsonPath('error', 'tier_limit_reached');

        expect(FamilyMember::where('user_id', $user->id)->count())->toBe(0);
    });

    it('still allows one on a tier that does carry it', function () {
        $user = User::factory()->create(['tier' => 'free', 'is_admin' => false, 'is_preview_user' => false]);
        Sanctum::actingAs($user);

        // The seeded matrix has family_module full on every tier, which is the
        // state today — so the gate must be invisible until a tier says otherwise.
        $this->postJson('/api/user/family-members', [
            'first_name' => 'Ruth',
            'last_name' => 'Chen',
            'relationship' => 'child',
        ])->assertSuccessful();

        expect(FamilyMember::where('user_id', $user->id)->count())->toBe(1);
    });

    it('is gated on every write path, not just the HTTP one', function () {
        // Rule 20. `family_members` has three writers: the controller, Fyn's
        // create_family_member tool, and Fyn's onboarding dependants create. A
        // capability enforced on one of three is not enforced.
        $agent = (string) file_get_contents(app_path('Agents/CoordinatingAgent.php'));
        $controller = (string) file_get_contents(app_path('Http/Controllers/Api/FamilyMembersController.php'));

        expect(substr_count($agent, "requireCapability(\$user, 'family_module')"))->toBe(2)
            ->and($controller)->toContain("requireCapability(\$user, 'family_module')");
    });
});

describe('benefits_child', function () use ($withoutCapability) {
    it('withholds the child benefit position when the tier does not carry it', function () use ($withoutCapability) {
        $user = $withoutCapability('benefits_child');
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(8),
            'is_dependent' => true,
            'receives_child_benefit' => true,
        ]);

        $position = app(ChildBenefitService::class)->calculateChildBenefitPosition($user->fresh(), 50000.0);

        expect($position['benefit']['annual_amount'])->toBe(0.0)
            ->and($position['net_annual_benefit'])->toBe(0.0)
            ->and($position['hicbc']['applies'])->toBeFalse();
    });

    it('gives the position on a tier that does carry it', function () {
        $user = User::factory()->create(['tier' => 'free', 'is_admin' => false, 'is_preview_user' => false]);
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(8),
            'is_dependent' => true,
            'receives_child_benefit' => true,
        ]);

        $position = app(ChildBenefitService::class)->calculateChildBenefitPosition($user->fresh(), 50000.0);

        expect($position['benefit']['annual_amount'])->toBeGreaterThan(0.0);
    });
});

it('names the capability using the same words the pricing page does', function () {
    // The refusal and the advert must not describe the same thing differently.
    $user = User::factory()->create(['tier' => 'free', 'is_admin' => false, 'is_preview_user' => false]);
    $config = \App\Models\TierConfiguration::where('tier', 'free')->firstOrFail();
    $matrix = $config->capability_matrix;
    $matrix['family_module'] = 'none';
    $config->update(['capability_matrix' => $matrix]);

    try {
        app(TeaserGate::class)->requireCapability($user, 'family_module');
        $this->fail('expected the gate to refuse');
    } catch (\App\Services\Stores\Exceptions\TierLimitExceededException $e) {
        expect($e->getMessage())->toContain(\App\Services\Payment\TierComparisonService::labelFor('family_module'));
    }
});
