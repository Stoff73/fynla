<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Estate\UndividedShareDiscount;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * W-0500. The undivided-share discount turns on `properties.joint_owner_is_spouse`,
 * and only one of its three states changes a number:
 *
 *   NULL  never asked        no discount
 *   true  co-owner is spouse no discount (IHTA 1984 s161)
 *   false co-owner is not    DISCOUNT APPLIES
 *
 * So "letting a surface record the answer" reduces to "letting it write `false`" —
 * the value that reduces a stated tax liability. `/m` and native could record
 * nothing, and Fyn was their only write path.
 *
 * CSJ's direction, 2026-08-26: the answer comes from a structured question the user
 * answers, never from the model's reading of conversation. These pin both halves.
 */
it('lets Fyn confirm a spousal co-owner, which grants no discount', function () {
    $canonical = app(PropertyNormaliser::class)->fromFyn([
        'address' => '1 Test Street',
        'ownership_type' => 'joint',
        'joint_owner_is_spouse' => true,
    ]);

    expect($canonical['joint_owner_is_spouse'])->toBeTrue();
});

it('never lets Fyn write the value that turns the discount on', function () {
    // The banned inference: a model reading "I own it with Ruth" and concluding she
    // is not the spouse. `UndividedShareDiscount`'s docblock forbids deriving this
    // from a name or from marital status, and an LLM doing it is the same act.
    $canonical = app(PropertyNormaliser::class)->fromFyn([
        'address' => '1 Test Street',
        'ownership_type' => 'joint',
        'joint_owner_is_spouse' => false,
    ]);

    expect($canonical)->not->toHaveKey('joint_owner_is_spouse');
});

it('drops anything that is not exactly true, however it is dressed', function () {
    foreach ([0, '0', 'false', 'no', null, '', 'true', 1] as $value) {
        $canonical = app(PropertyNormaliser::class)->fromFyn([
            'address' => '1 Test Street',
            'ownership_type' => 'joint',
            'joint_owner_is_spouse' => $value,
        ]);

        // Strict identity, so a truthy string cannot smuggle a boolean through.
        expect($canonical)->not->toHaveKey('joint_owner_is_spouse');
    }
});

it('accepts the negative from the user through the property endpoint', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    $property = Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 50,
        'joint_owner_id' => null,
        'joint_owner_name' => 'Ruth Chen',
        'joint_owner_is_spouse' => null,
    ]);

    // The route `/m`'s property detail PUTs to when the user presses No.
    $this->actingAs($user)->putJson("/api/properties/{$property->id}", ['joint_owner_is_spouse' => false])
        ->assertOk();

    expect($property->fresh()->joint_owner_is_spouse)->toBeFalse();
});

it('leaves the column null when nobody answers, so no discount applies', function () {
    $user = User::factory()->create();

    $property = Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 50,
        'joint_owner_id' => null,
        'joint_owner_name' => 'Ruth Chen',
        'joint_owner_is_spouse' => null,
    ]);

    // Not answering IS "not sure", and the conservative direction is the default:
    // no discount, tax stated at the higher figure.
    expect($property->fresh()->joint_owner_is_spouse)->toBeNull()
        ->and(app(UndividedShareDiscount::class)->applies($property->fresh(), $user))->toBeFalse();
});

it('asks the question on /m and PUTs the answer, rather than leaving it web-only', function () {
    // Rule 19. The surface has no property form, so the question is a control of its
    // own on the detail view; this reads the file rather than the rendered component
    // because the defect was the ABSENCE of any route to the answer.
    $view = (string) file_get_contents(base_path('resources/mobile/views/modules/PropertyDetail.vue'));

    expect($view)->toContain('joint_owner_is_spouse')
        ->and($view)->toContain('answerCoOwnerSpouse')
        ->and($view)->toContain('apiPut');
});

/**
 * Found by driving `/m` on csjones, 2026-09-04, not by this file — which is the
 * point. The test above PUTs exactly this and asserts only the column it was
 * about, so it stayed green while the same request converted a jointly-owned
 * property to sole ownership: `ownership_type` joint -> individual and the
 * user's share 50 -> 100, doubling the value carried into the estate.
 *
 * `PropertyController::update()` resolves the effective ownership type from the
 * stored record and never writes it back into the validated payload, so
 * `PropertyNormaliser::fromForm()` injects its own default ('individual') for
 * the key the request omitted, and `SharedOwnership::applyTo()` follows it to 100.
 */
it('answering the question does not convert the property to sole ownership', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    $property = Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => null,
        'joint_owner_name' => 'Ruth Chen',
        'joint_owner_is_spouse' => null,
    ]);

    $this->actingAs($user)->putJson("/api/properties/{$property->id}", ['joint_owner_is_spouse' => true])
        ->assertOk();

    $fresh = $property->fresh();

    expect($fresh->joint_owner_is_spouse)->toBeTrue()
        ->and($fresh->ownership_type)->toBe('joint')
        ->and((float) $fresh->ownership_percentage)->toBe(50.0);
});

it('a partial update keeps a stated split that is not the 50/50 default', function () {
    // The same root cause with a different tell: the controller only preserves the
    // stored share when a LINKED joint owner exists, so a shared property whose
    // co-owner holds no account is re-defaulted to 50 by any update that says
    // nothing about the split.
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    $property = Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 70,
        'joint_owner_id' => null,
        'joint_owner_name' => 'Ruth Chen',
        'joint_owner_is_spouse' => null,
    ]);

    $this->actingAs($user)->putJson("/api/properties/{$property->id}", ['joint_owner_is_spouse' => false])
        ->assertOk();

    $fresh = $property->fresh();

    expect($fresh->ownership_type)->toBe('tenants_in_common')
        ->and((float) $fresh->ownership_percentage)->toBe(70.0);
});
