<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0132 — the Family settings card asked a question it already had the answer to.
 *
 * `/settings/family` rendered "Do you wish to leave anything to charity?" with the
 * answer "Not set", on an account holding a £10,000 charitable legacy the estate
 * calculation was already using to apply the reduced Inheritance Tax rate. The card
 * was reading `users.charitable_bequest` — written by a toggle on /estate, never
 * loaded back into the client — so it was a fourth answer to a question the will
 * already answers, and the only one that was wrong.
 *
 * The page now reads `data.charitable_bequests` off the profile it already loads,
 * which `WillAnalysisService::charitableBequestSummary()` builds from the will.
 * These tests set `users.charitable_bequest` to whichever value would produce the
 * WRONG answer, so an endpoint that consulted the column cannot pass them.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

function willWithCharitableLegacy(User $user, float $amount = 10000): void
{
    $will = Will::create(['user_id' => $user->id, 'has_will' => true]);

    Bequest::create([
        'will_id' => $will->id,
        'user_id' => $user->id,
        'beneficiary_name' => 'Cancer Research UK',
        'beneficiary_type' => 'charity',
        'bequest_type' => 'specific_amount',
        'specific_amount' => $amount,
        'priority_order' => 1,
    ]);
}

it('answers yes from the recorded will on an account whose toggle was never answered', function () {
    // Priya Raman's exact position when this was raised: legacy recorded, column NULL.
    $user = User::factory()->create(['charitable_bequest' => null]);
    willWithCharitableLegacy($user);

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk();

    expect($response->json('data.charitable_bequests'))->toMatchArray([
        'has_bequests' => true,
        'count' => 1,
        'has_estate_share' => false,
    ]);
    expect($response->json('data.charitable_bequests.fixed_total'))->toEqual(10000);
});

it('answers yes even where the user pressed No on the toggle', function () {
    $user = User::factory()->create(['charitable_bequest' => false]);
    willWithCharitableLegacy($user);

    expect($this->actingAs($user)->getJson('/api/user/profile')->json('data.charitable_bequests.has_bequests'))
        ->toBeTrue();
});

it('answers no where the user pressed Yes on the toggle but recorded no legacy', function () {
    $user = User::factory()->create(['charitable_bequest' => true]);
    Will::create(['user_id' => $user->id, 'has_will' => true]);

    expect($this->actingAs($user)->getJson('/api/user/profile')->json('data.charitable_bequests.has_bequests'))
        ->toBeFalse();
});

it('publishes the block for a user with no will, so the card always has an answer to render', function () {
    // NULL and false were indistinguishable to the old card and both rendered
    // "Not set". There is no third state to render now — the will either records a
    // gift or it does not.
    $user = User::factory()->create(['charitable_bequest' => null]);

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk();

    expect($response->json('data.charitable_bequests'))->toMatchArray([
        'has_bequests' => false,
        'count' => 0,
        'has_estate_share' => false,
    ]);
});
