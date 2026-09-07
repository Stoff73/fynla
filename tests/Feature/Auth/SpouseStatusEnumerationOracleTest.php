<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0476. W-0349 closed the oracle on `POST /api/user/family-members` — both branches
 * return an identical response — and the disclosure re-formed one call away on
 * `GET /api/spouse-permission/status`, then once more on `POST .../revoke`.
 *
 * The mechanism was structural: only a REGISTERED invitee has a user id to key a
 * `SpousePermission` row on, and every branch that varied with the row's existence
 * answered "is that address registered?" for any address the caller typed.
 *
 * Closed 2026-09-01, at both places at once, because closing one alone moves it:
 *   - `status()` returns one shape for an unanswered invitation from an unlinked
 *     caller, withholding the permission row (its `spouse_id` IS the invitee's
 *     account id) and matching key sets.
 *   - `revoke()` no longer 404s on a missing row. Revocation is idempotent, and the
 *     404 distinguished the two addresses even with identical status payloads.
 *
 * This file was a tripwire measuring the open oracle. It is now the assertion that it
 * is shut, and the history is kept above so nobody re-opens it thinking the shapes
 * merely happened to line up.
 *
 * Not closed by retention: W-0472 decided the invited address is NOT stored.
 */
it('returns one indistinguishable status shape for a registered and an unregistered invitee', function () {
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
        'first_name' => 'Registered',
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

    expect($registeredKeys)->toBe($unregisteredKeys)
        // The sharper half, and the one W-0349 withholds from the POST response: the
        // registered branch used to disclose the invitee's USER ID, which exists only
        // because the address is registered.
        ->and($afterRegistered['permission'] ?? null)->toBeNull()
        ->and($afterUnregistered['permission'] ?? null)->toBeNull()
        // Both callers entered the same name, so the two payloads are identical in
        // full — not merely the same shape with different contents.
        ->and($afterRegistered)->toBe($afterUnregistered);
});

it('answers a withdraw identically whether or not there is a permission row', function () {
    // The oracle re-formed here after the status shapes were unified: `revoke()`
    // returned 404 "No permission found to revoke" with no row, which is every
    // unregistered invitation, and the registered branch renders the Withdraw button
    // that reaches it.
    $registered = User::factory()->create(['email' => 'registered-revoke@example.com']);

    $caller = User::factory()->create(['marital_status' => 'married']);
    Sanctum::actingAs($caller);
    $this->postJson('/api/user/family-members', [
        'first_name' => 'Registered',
        'last_name' => 'Partner',
        'relationship' => 'spouse',
        'email' => $registered->email,
    ]);
    $withRow = $this->postJson('/api/spouse-permission/revoke');

    $other = User::factory()->create(['marital_status' => 'married']);
    Sanctum::actingAs($other);
    $this->postJson('/api/user/family-members', [
        'first_name' => 'Registered',
        'last_name' => 'Partner',
        'relationship' => 'spouse',
        'email' => 'nobody-revoke-w0476@example.com',
    ]);
    $withoutRow = $this->postJson('/api/spouse-permission/revoke');

    expect($withRow->status())->toBe($withoutRow->status())
        ->and($withRow->json())->toBe($withoutRow->json());
});
