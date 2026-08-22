<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0172, through the endpoint the Add Property wizard actually posts to.
 *
 * The persona's Manchester property is held tenants in common at 40% with
 * Mike Barrett, who is not a Fynla user. The property saved correctly and its
 * £120,000 mortgage did not: it was stored at 50%, so David was charged
 * £60,000 of a debt he holds £48,000 of — and because his spouse correctly did
 * not hold the mortgage either, the other £60,000 belonged to nobody.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($this->user);
});

/** The wizard's payload for the persona's Manchester property. */
function manchesterProperty(array $overrides = []): array
{
    return array_merge([
        'property_type' => 'buy_to_let',
        'address_line_1' => 'Unit 12, Victoria Mill',
        'city' => 'Manchester',
        'postcode' => 'M4 6JL',
        'current_value' => 295000,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 40,
        'joint_owner_name' => 'Mike Barrett',

        // The mortgage step. Borrower(s) offers "Just me" / "Joint borrowers"
        // and carries no share input, so the payload states no share.
        'outstanding_mortgage' => 120000,
        'mortgage_lender_name' => 'NatWest',
        'mortgage_ownership_type' => 'joint',
        'mortgage_joint_owner_name' => 'Mike Barrett',
    ], $overrides);
}

it('carries the property share onto its mortgage instead of inventing 50/50', function () {
    $this->postJson('/api/properties', manchesterProperty())->assertCreated();

    $property = Property::sole();
    $mortgage = Mortgage::sole();

    // The property side was always right and must stay right.
    expect($property->ownership_type)->toBe('tenants_in_common')
        ->and((float) $property->ownership_percentage)->toBe(40.0)
        ->and($property->joint_owner_id)->toBeNull()
        ->and($property->joint_owner_name)->toBe('Mike Barrett');

    // The debt now sits on the same basis as the asset.
    expect((float) $mortgage->ownership_percentage)->toBe(40.0);

    // £48,000 of the £120,000, not £60,000.
    expect((float) $mortgage->outstanding_balance * ((float) $mortgage->ownership_percentage / 100))
        ->toBe(48000.0);
});

it('names the off-platform co-owner on the mortgage, so the rest is not unattributed', function () {
    $this->postJson('/api/properties', manchesterProperty())->assertCreated();

    $mortgage = Mortgage::sole();

    // The remaining 60% is explicitly Mike Barrett's rather than silently absent.
    expect($mortgage->joint_owner_id)->toBeNull()
        ->and($mortgage->joint_owner_name)->toBe('Mike Barrett');
});

it('still honours a share the caller states on the mortgage itself', function () {
    // Supplied beats inherited here too: two people can own 40/60 and borrow
    // on a different split.
    $this->postJson('/api/properties', manchesterProperty([
        'mortgage_ownership_percentage' => 70,
    ]))->assertCreated();

    expect((float) Mortgage::sole()->ownership_percentage)->toBe(70.0);
});

it('leaves a solely-borrowed mortgage on a shared property at 100', function () {
    // The user did not say the borrowing was shared, so nothing is inherited —
    // a mortgage's liability is still configured independently of the asset.
    $this->postJson('/api/properties', manchesterProperty([
        'mortgage_ownership_type' => 'individual',
        'mortgage_joint_owner_name' => null,
    ]))->assertCreated();

    $mortgage = Mortgage::sole();

    expect($mortgage->ownership_type)->toBe('individual')
        ->and((float) $mortgage->ownership_percentage)->toBe(100.0);
});

it('defaults to 50/50 when the property itself has no share to inherit', function () {
    // A jointly-owned property carries the 50/50 default, and the mortgage
    // takes that same 50 — inherited from one source, not invented beside it.
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $this->user->update(['spouse_id' => $spouse->id]);

    $this->postJson('/api/properties', manchesterProperty([
        'ownership_type' => 'joint',
        // The joint branch of the form shows no share input, so none is stated
        // and the property takes the 50/50 default.
        'ownership_percentage' => null,
        'joint_owner_id' => $spouse->id,
        'joint_owner_name' => null,
        'mortgage_joint_owner_id' => $spouse->id,
        'mortgage_joint_owner_name' => null,
    ]))->assertCreated();

    expect((float) Property::sole()->ownership_percentage)->toBe(50.0)
        ->and((float) Mortgage::sole()->ownership_percentage)->toBe(50.0);
});
