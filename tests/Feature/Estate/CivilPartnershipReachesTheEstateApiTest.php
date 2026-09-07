<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0480, findings F2 and F5 from the `tax-compliance-reviewer` gate.
 *
 * The four services the item named were fixed first, and two of them turned out not to
 * be the path a user is on:
 *
 *  - `TrustController:201` held the identical line to `ComprehensiveEstatePlanService`,
 *    feeding the same `IHTCalculationService::calculate()`. A civil partnership got a
 *    single-person liability here beside the corrected one next door.
 *  - `LifePolicyController:45` selects the second-death Inheritance Tax basis, and
 *    `LifeCoverCalculator::calculateLifeCoverRecommendations()` — the method the board
 *    item pointed at — has NO production caller. Fixing the service alone moved no
 *    user's figure at all.
 *
 * **These are HTTP tests on purpose.** A unit test on either service would have passed
 * while the controller above it still passed `null` for the partner.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/** A household holding enough to be taxable, at the given marital status. */
function estateApiHousehold(string $status): User
{
    $partner = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => $status,
        'date_of_birth' => '1970-07-19',
        'gender' => 'female',
    ]);

    $user = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => $status,
        'date_of_birth' => '1968-03-04',
        'gender' => 'male',
        'spouse_id' => $status === 'single' ? null : $partner->id,
    ]);

    if ($status !== 'single') {
        $partner->update(['spouse_id' => $user->id]);
    }

    Property::factory()->create([
        'user_id' => $user->id,
        'property_type' => 'main_residence',
        'current_value' => 900000,
        'ownership_type' => 'individual',
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 400000,
    ]);

    return $user->fresh();
}

it('gives a civil partnership the same trust-recommendation estate as a marriage', function () {
    $liability = function (string $status) {
        $user = estateApiHousehold($status);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/estate/trust-recommendations');
        $response->assertOk();

        return $response->json('data.iht_liability');
    };

    $civil = $liability('civil_partnership');
    $married = $liability('married');
    $single = $liability('single');

    // The partner brings a second nil rate band and residence allowance, so the
    // liability is strictly lower than a single person's on the same estate.
    expect($civil)->toBe($married)
        ->and($civil)->toBeLessThan($single);
});

it('puts a civil partnership on the second-death life cover basis, as it does a marriage', function () {
    $basis = function (string $status) {
        $user = estateApiHousehold($status);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/estate/life-policy-strategy');
        $response->assertOk();

        return $response->json('data.iht_basis') ?? $response->json('data');
    };

    expect($basis('civil_partnership'))->toEqual($basis('married'));
});
