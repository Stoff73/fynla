<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0476. W-0349 closed the oracle on `POST /api/user/family-members` — both branches
 * return an identical response — and the disclosure re-formed one call away on
 * `GET /api/spouse-permission/status`.
 *
 * **This test does not assert the oracle is closed, because it is not.** It pins the
 * measurement, so the item cannot be closed on a payload change that leaves the
 * distinction observable elsewhere, and so a future fix has something that goes green
 * when it actually lands.
 *
 * The mechanism is structural: only a REGISTERED invitee has a user id to key a
 * `SpousePermission` row on, and `status()` branches on that row's existence. Making the
 * two payloads identical would not be enough — the registered branch renders a Withdraw
 * request button, and `revoke()` returns 404 "No permission found to revoke" when there
 * is no row, which is every unregistered invitation. The oracle re-forms in the withdraw
 * response.
 *
 * That is why acceptance 2 says this closes with **W-0472**: without a record of an
 * invitation to an address with no account, the two states cannot behave identically,
 * and retention is CSJ's and compliance-lead's call.
 */
it('measures the enumeration oracle on the status endpoint (W-0476, still open)', function () {
    $registered = User::factory()->create(['email' => 'registered@example.com']);

    $caller = User::factory()->create(['marital_status' => 'married']);
    Sanctum::actingAs($caller);

    $this->postJson('/api/user/family-members', [
        'first_name' => 'Registered',
        'last_name' => 'Partner',
        'relationship' => 'spouse',
        'email' => $registered->email,
    ]);

    $afterRegistered = $this->getJson('/api/spouse-permission/status')->json('data');

    // A second caller, inviting an address that has no account.
    $other = User::factory()->create(['marital_status' => 'married']);
    Sanctum::actingAs($other);

    $this->postJson('/api/user/family-members', [
        'first_name' => 'Unregistered',
        'last_name' => 'Partner',
        'relationship' => 'spouse',
        'email' => 'nobody-w0476@example.com',
    ]);

    $afterUnregistered = $this->getJson('/api/spouse-permission/status')->json('data');

    // Key sets compared, not listed — acceptance 3. A payload that adds a key for one
    // branch and not the other is the oracle, whatever the values are.
    $registeredKeys = array_keys($afterRegistered ?? []);
    $unregisteredKeys = array_keys($afterUnregistered ?? []);
    sort($registeredKeys);
    sort($unregisteredKeys);

    // CURRENT, MEASURED STATE — the oracle is open, and this test is deliberately NOT
    // skipped. A skipped test proves nothing and rots quietly; this one is a tripwire.
    // When W-0472's retention decision lands and the two shapes are unified, this
    // assertion goes RED and forces whoever fixed it to flip it to `toBe(...)`
    // deliberately, rather than closing the oracle with nothing recording that it ever
    // existed.
    //
    // Measured 2026-09-01, not read off the controller:
    //   registered   -> 5 keys, `permission` is a full row, `awaiting_their_response`
    //   unregistered -> 6 keys, `permission` null, `requires_account_link`, `message`
    expect($registeredKeys)->not->toBe($unregisteredKeys);

    // The sharper half, and the one W-0349 explicitly withholds from the POST response:
    // the registered branch discloses the invitee's USER ID, which only exists because
    // the address is registered.
    expect($afterRegistered['permission']['spouse_id'] ?? null)->not->toBeNull();
    expect($afterUnregistered['permission'] ?? null)->toBeNull();
});
