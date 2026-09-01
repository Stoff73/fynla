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
