<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('POST /api/properties persists a Property via PropertyStore', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    $response = $this->actingAs($user)->postJson('/api/properties', [
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'current_value' => 350000,
        'country' => 'United Kingdom',
    ]);

    $response->assertCreated();
    expect(Property::where('user_id', $user->id)->count())->toBe(1);

    $property = Property::where('user_id', $user->id)->first();
    expect($property->property_type)->toBe('main_residence');
    expect($property->ownership_type)->toBe('individual');
    expect((string) $property->current_value)->toBe('350000.00');
});

it('PUT /api/properties/{id} updates a Property via PropertyStore', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 350000]);

    $response = $this->actingAs($user)->putJson("/api/properties/{$property->id}", [
        'current_value' => 425000,
    ]);

    $response->assertOk();
    expect((string) $property->fresh()->current_value)->toBe('425000.00');
});

it('DELETE /api/properties/{id} soft-deletes via PropertyStore', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/properties/{$property->id}");

    $response->assertOk();
    expect(Property::find($property->id))->toBeNull();
    expect(Property::withTrashed()->find($property->id))->not->toBeNull();
});

it('rejects updates from a non-owner', function () {
    $owner = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $stranger = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($stranger)->putJson("/api/properties/{$property->id}", [
        'current_value' => 999999,
    ]);

    $response->assertStatus(404);
});

it('returns the typed subscription destination when the free property cap is reached', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Property::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/api/properties', [
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '6 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AB',
        'current_value' => 250000,
        'country' => 'United Kingdom',
    ])->assertForbidden()
        ->assertJsonPath('error', 'tier_limit_reached')
        ->assertJsonPath('entity_key', 'property')
        ->assertJsonPath('destination.screen', 'subscription')
        ->assertJsonPath('destination.fallback', 'net_worth');

    expect(Property::where('user_id', $user->id)->count())->toBe(1);
});

it('refuses a stated 100% share on a joint property instead of rewriting it', function () {
    // W-0040, the other half of the rule the wizard test below relies on. A
    // caller stating "I own all of it" on a shared asset used to be answered 201
    // and stored as "I own half of it", while a caller stating 0 was refused —
    // an asymmetry nobody chose, it fell out of the coercion. A 100/0 split IS
    // individual ownership, so the boundary says so rather than altering the
    // figure. PropertyForm.vue only states a share where it offers an input for
    // one, so a real submission cannot reach this; a Fyn tool call or a hand-made
    // request can.
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    $this->actingAs($user)->postJson('/api/properties', [
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 100,
        'address_line_1' => '2 Wrongly Joint Road',
        'city' => 'Guildford',
        'postcode' => 'GU1 4RH',
        'current_value' => 500000,
        'country' => 'United Kingdom',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('ownership_percentage');

    // Refused means refused: nothing stored, nothing rewritten.
    expect(Property::where('user_id', $user->id)->count())->toBe(0);
});

it('the property wizard persists the entered mortgage term, rate fix end date and interest portion', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    $maturity = Carbon::now()->startOfDay()->addMonths(156);

    $this->actingAs($user)->postJson('/api/properties', [
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        // W-0040. This used to send `'ownership_percentage' => 100` and assert
        // the server rewrote it to 50, which was the wizard's uncleared
        // individual default being read as a choice. CSJ ruled a 100/0 split IS
        // individual ownership, so a stated 100 is now refused rather than
        // rewritten, and PropertyForm.vue omits the field on any type without a
        // share input. The payload below is what the form now sends: no share,
        // which still stores 50/50 — the assertion below is unchanged.
        'address_line_1' => '15 Chestnut Lane',
        'city' => 'Guildford',
        'postcode' => 'GU1 4RH',
        'current_value' => 850000,
        'country' => 'United Kingdom',
        'outstanding_mortgage' => 65000,
        'mortgage_lender_name' => 'HSBC',
        'mortgage_type' => 'repayment',
        'mortgage_original_loan_amount' => 450000,
        'mortgage_interest_rate' => 4.29,
        'mortgage_rate_type' => 'fixed',
        'mortgage_monthly_payment' => 550,
        'mortgage_maturity_date' => $maturity->toDateString(),
        'mortgage_rate_fix_end_date' => '2027-04-01',
        'mortgage_monthly_interest_portion' => 232.38,
        'mortgage_ownership_type' => 'joint',
        'mortgage_joint_owner_id' => $spouse->id,
    ])->assertCreated();

    $property = Property::where('user_id', $user->id)->latest('id')->firstOrFail();
    expect((float) $property->ownership_percentage)->toEqual(50.00);

    $mortgage = Mortgage::where('property_id', $property->id)->firstOrFail();

    expect($mortgage->remaining_term_months)->toBe(156)
        ->and($mortgage->maturity_date->toDateString())->toBe($maturity->toDateString())
        ->and($mortgage->rate_fix_end_date?->toDateString())->toBe('2027-04-01')
        ->and((float) $mortgage->monthly_interest_portion)->toEqual(232.38)
        ->and((float) $mortgage->ownership_percentage)->toEqual(50.00);
});
