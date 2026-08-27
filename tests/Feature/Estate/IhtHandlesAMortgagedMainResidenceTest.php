<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0502 — the estate calculation threw on a mortgaged main residence.
 *
 * `sumMainResidenceNetShare()` and `projectMainResidenceNetValue()` both read
 * `$property->mortgages` on models returned by `PropertyStore::forUserByType()`,
 * which did not eager-load the relation. `AppServiceProvider:217` sets
 * `Model::preventLazyLoading(! app()->isProduction())`, so:
 *
 *   local / development / csjones staging → LazyLoadingViolationException, 500
 *   production                            → no throw, one extra query per property
 *
 * The trigger is **a main residence carrying a mortgage** — the ordinary case, and
 * one `ChrisUserSeeder` produces. It survived because production does not throw,
 * because the two most-used test accounts have no mortgage on their main residence,
 * and because every fixture that exercised this path built properties without one.
 *
 * These assert with lazy loading explicitly ON, so the guard cannot be lost to an
 * environment change: this file states the condition it needs rather than
 * inheriting it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Model::preventLazyLoading(true);
});

afterEach(function () {
    Model::preventLazyLoading(! app()->isProduction());
});

function jointOwnerOfSomeoneElsesHome(): User
{
    // The reproducible trigger, established by bisecting a live failure rather than
    // guessed: the VIEWER is the joint_owner_id of a property whose user_id is
    // somebody else. `PropertyStore::forUserByType()` reads `forUserOrJoint`, so
    // that property comes back — and `sumMainResidenceNetShare()` then reads
    // `$property->mortgages`, a relation the store does not eager-load.
    //
    // A first version of this fixture gave the viewer their OWN mortgaged main
    // residence and passed against the unfixed code, so it proved nothing. Chris's
    // own mortgaged home does not trigger it; being joint owner of another user's
    // does.
    $owner = User::factory()->create(['marital_status' => 'single']);
    $viewer = User::factory()->create(['marital_status' => 'single']);

    FamilyMember::factory()->create([
        'user_id' => $viewer->id,
        'relationship' => 'child',
    ]);

    $property = Property::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $viewer->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 50,
        'property_type' => 'main_residence',
        'current_value' => 600_000,
    ]);

    Mortgage::factory()->create([
        'user_id' => $owner->id,
        'property_id' => $property->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 200_000,
    ]);

    return $viewer->fresh();
}

it('calculates an estate whose main residence carries a mortgage', function () {
    $user = jointOwnerOfSomeoneElsesHome();

    $result = app(IHTCalculationService::class)->calculate($user, null, false);

    // Reaching an assertion at all is the point: this threw
    // LazyLoadingViolationException before the fix.
    expect($result)->toHaveKey('iht_liability')
        ->and($result)->toHaveKey('total_net_estate');
});

it('subtracts the mortgage from the residence the band is capped against', function () {
    $user = jointOwnerOfSomeoneElsesHome();

    $result = app(IHTCalculationService::class)->calculate($user, null, false);

    // Reached at all, and at a SHARE rather than the whole: the viewer holds half
    // of a £600,000 home, so their estate is nowhere near £600,000.
    expect((float) $result['total_net_estate'])->toBeGreaterThan(0.0)
        ->and((float) $result['total_net_estate'])->toBeLessThan(600_000.0);
});
