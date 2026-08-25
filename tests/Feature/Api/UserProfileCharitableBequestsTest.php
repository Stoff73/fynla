<?php

declare(strict_types=1);

use App\Http\Requests\UpdatePersonalInfoRequest;
use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Schema;

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
 * These tests used to set `users.charitable_bequest` to whichever value would
 * produce the WRONG answer, so an endpoint that consulted the column could not pass
 * them.
 *
 * **W-0221 dropped the column**, so the decoy can no longer be set — and does not
 * need to be. A column that does not exist cannot be read, which is a stronger
 * guarantee than any fixture. What remains here is the positive case: the endpoint
 * answers from the will, in each of the states a will can be in. The last case
 * pins the column's absence, because the risk this file exists to manage was never
 * really the old readers — it was a plausibly-named column with a working endpoint
 * growing a fourth answer back.
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

it('answers yes from the recorded will on an account that never answered a toggle', function () {
    // Priya Raman's exact position when this was raised: legacy recorded, and the
    // column that used to disagree with it now gone (W-0221).
    $user = User::factory()->create();
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

// W-0221. This case used to press No on the toggle and prove the endpoint ignored
// it. With the column dropped there is nothing left to ignore, so the case that
// matters is the one that stops it coming back: the next feature wanting an answer
// about charity must not find a plausibly-named column to read.
it('keeps the users table free of a charitable_bequest column for anything to read', function () {
    expect(Schema::hasColumn('users', 'charitable_bequest'))->toBeFalse();
});

it('answers no where a will is recorded but carries no charitable legacy', function () {
    $user = User::factory()->create();
    Will::create(['user_id' => $user->id, 'has_will' => true]);

    expect($this->actingAs($user)->getJson('/api/user/profile')->json('data.charitable_bequests.has_bequests'))
        ->toBeFalse();
});

it('publishes the block for a user with no will, so the card always has an answer to render', function () {
    // NULL and false were indistinguishable to the old card and both rendered
    // "Not set". There is no third state to render now — the will either records a
    // gift or it does not.
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk();

    expect($response->json('data.charitable_bequests'))->toMatchArray([
        'has_bequests' => false,
        'count' => 0,
        'has_estate_share' => false,
    ]);
});

// W-0221 acceptance 1: the write path had to close BEFORE or WITH the drop, on the
// stated reasoning that "a column dropped while its endpoint still accepts the field
// trades a silent discard for a 500".
//
// Measured, that is NOT what happens on this path, and the reason is worth recording.
// Eloquent's isGuarded() calls isGuardableColumn(), which consults the live table
// schema — so once the column is gone, isFillable('charitable_bequest') returns false
// and mass assignment silently SKIPS it. Re-adding the validation rule does not
// produce a 500; it produces the same silent discard as before. (Verified by putting
// the rule back and re-running this file: it stayed green.)
//
// So the endpoint case below cannot guard the rule's removal, and is not claimed to.
// It guards the outcome — no 500, neighbouring fields still land — and the rule
// itself is guarded directly underneath.
it('accepts a personal-information update carrying the retired charitable_bequest field, and ignores it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/user/profile/personal', [
            'charitable_bequest' => true,
            'marital_status' => 'single',
        ])
        ->assertOk();

    // The neighbouring field still lands, so this is not passing because the whole
    // request was rejected.
    expect($user->fresh()->marital_status)->toBe('single')
        ->and(Schema::hasColumn('users', 'charitable_bequest'))->toBeFalse();
});

// The rule's absence, asserted directly — the schema check above cannot see it, and
// a rule accepting a field nothing can store is how the next reader concludes the
// column must exist somewhere.
it('no longer validates charitable_bequest on the personal-information request', function () {
    // rules() reads $this->user()->id for the email uniqueness rule, so the request
    // needs a resolver before it can be asked what it validates.
    $user = User::factory()->create();
    $request = UpdatePersonalInfoRequest::create('/api/user/profile/personal', 'PUT');
    $request->setUserResolver(fn () => $user);

    $rules = $request->rules();

    expect($rules)->not->toHaveKey('charitable_bequest')
        ->and($rules)->toHaveKey('is_registered_blind');
});
