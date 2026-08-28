<?php

declare(strict_types=1);

use App\Http\Resources\UserResource;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Tax\TaxOptimisationService;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The expenditure endpoint crosses the Premium capability boundary, which
    // resolves a tier and throws ModelNotFoundException without these — the
    // handler then turns that into a 404 that reads exactly like a missing route.
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

/**
 * CSJ, 2026-08-19: everything is retained when an account is deleted — that is
 * a regulatory requirement, dependants included. A linked spouse account
 * remains and can still log in, but from that moment it must not see the
 * deleted partner's information.
 *
 * Found live on csjones: user 17's profile payload reported `spouse: null` and
 * then returned three of the deleted spouse's children in the same response,
 * tagged `owner: 'spouse'`. The relation filters soft-deleted users; the
 * sharing query read the raw spouse_id column and bypassed it.
 */
function linkedCouple(): array
{
    $user = User::factory()->create(['marital_status' => 'married']);
    $spouse = User::factory()->create(['marital_status' => 'married']);
    $user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    FamilyMember::create([
        'user_id' => $spouse->id,
        'relationship' => 'child',
        'first_name' => 'Amelia',
        'date_of_birth' => '2015-06-01',
        'is_dependent' => true,
    ]);

    return [$user->fresh(), $spouse];
}

it('shares a live spouse\'s children with their partner', function (): void {
    [$user] = linkedCouple();

    $shared = collect(app(UserProfileService::class)->getCompleteProfile($user)['family_members'])
        ->where('owner', 'spouse');

    expect($shared)->toHaveCount(1)
        ->and($shared->first()['first_name'])->toBe('Amelia');
});

it('stops sharing them the moment that account is deleted', function (): void {
    [$user, $spouse] = linkedCouple();

    $spouse->delete();

    $profile = app(UserProfileService::class)->getCompleteProfile($user->fresh());

    expect($profile['spouse'])->toBeNull()
        ->and(collect($profile['family_members'])->where('owner', 'spouse'))->toBeEmpty();
});

it('retains the deleted spouse\'s records rather than removing them', function (): void {
    [, $spouse] = linkedCouple();

    $spouse->delete();

    // Retention is the requirement — the rows stay, they just stop being shared.
    expect(FamilyMember::where('user_id', $spouse->id)->count())->toBe(1)
        ->and(User::withTrashed()->find($spouse->id))->not->toBeNull();
});

it('reports no live spouse id once the account is deleted', function (): void {
    [$user, $spouse] = linkedCouple();

    expect($user->fresh()->liveSpouseId())->toBe($spouse->id);

    $spouse->delete();

    $survivor = $user->fresh();
    expect($survivor->spouse_id)->toBe($spouse->id)
        ->and($survivor->liveSpouseId())->toBeNull();
});

// ─── The sharing gate (D1/D2) ────────────────────────────────────────────────

it('keeps sharing on while both accounts are live', function (): void {
    [$user] = linkedCouple();

    expect($user->fresh()->hasAcceptedSpousePermission())->toBeTrue();
});

it('turns sharing off when the partner account is deleted, and leaves the permission row alone', function (): void {
    [$user, $spouse] = linkedCouple();

    SpousePermission::create([
        'user_id' => $user->id,
        'spouse_id' => $spouse->id,
        'status' => 'accepted',
    ]);

    $spouse->delete();

    expect($user->fresh()->hasAcceptedSpousePermission())->toBeFalse()
        // Retained, not voided — the row is part of the regulatory record.
        ->and(SpousePermission::where('user_id', $user->id)->where('status', 'accepted')->exists())->toBeTrue();
});

// ─── What clients are told (D3) ──────────────────────────────────────────────

it('publishes the historical link and a live one that goes null on deletion', function (): void {
    [$user, $spouse] = linkedCouple();

    $live = (new UserResource($user->fresh()))->toArray(request());
    expect($live['spouse_id'])->toBe($spouse->id)
        ->and($live['live_spouse_id'])->toBe($spouse->id)
        ->and($live['has_spouse'])->toBeTrue();

    $spouse->delete();

    $after = (new UserResource($user->fresh()))->toArray(request());
    expect($after['spouse_id'])->toBe($spouse->id)
        ->and($after['live_spouse_id'])->toBeNull()
        ->and($after['has_spouse'])->toBeFalse();
});

it('does not start publishing the spouse block just by asking whether they are live', function (): void {
    // liveSpouseId() resolves the spouse. If it did that through setRelation(),
    // relationLoaded('spouse') would flip to true and UserResource — which
    // builds has_spouse BEFORE its spouse block — would begin including the
    // spouse's id, name and email in payloads that previously omitted them.
    [$user] = linkedCouple();

    // resolve(), not toArray() — toArray leaves the unmet when() conditions in
    // place as MissingValue and only resolve() strips them, so asserting on
    // toArray would pass whatever happened.
    $payload = (new UserResource($user->fresh()))->resolve(request());

    expect($payload['has_spouse'])->toBeTrue()
        ->and($payload)->not->toHaveKey('spouse');
});

// ─── Lazy-load traps (CSJ, 2026-08-20) ───────────────────────────────────────

/**
 * Model::preventLazyLoading(! app()->isProduction()) makes a lazy load a wasted
 * query in production and a thrown LazyLoadingViolationException everywhere
 * else — but ONLY for a model that came out of a collection of MORE THAN ONE
 * row. Measured, not assumed:
 *
 *   find()                    → no throw
 *   first()                   → no throw
 *   get()->first(), 1 row     → no throw
 *   get()->first(), 2+ rows   → THROWS
 *
 * That is why both traps sat unnoticed, and why the two guards below create a
 * second user they never use. Without it they pass against the broken code.
 */
it('builds the user payload from a collection without lazy loading', function (): void {
    User::factory()->count(2)->create();

    $users = User::query()->get();

    foreach ($users as $user) {
        $payload = (new UserResource($user))->resolve(request());
        expect($payload)->toHaveKey('has_spouse')
            // Not loaded, so not published — and, now, not touched either.
            ->and($payload)->not->toHaveKey('role')
            ->and($payload)->not->toHaveKey('subscription');
    }
});

it('reads the spouse from a collection-loaded user without lazy loading', function (): void {
    [$user] = linkedCouple();
    User::factory()->create(); // the collection must hold more than one row

    $fromCollection = User::query()->get()->firstWhere('id', $user->id);

    expect($fromCollection->liveSpouse()?->id)->toBe($user->spouse_id)
        ->and($fromCollection->hasAcceptedSpousePermission())->toBeTrue();
});

// ─── Planning stops treating them as a couple (D4) ───────────────────────────

it('stops planning the survivor as one of a couple', function (): void {
    [$user, $spouse] = linkedCouple();

    $spousalStrategy = fn (User $u) => (new ReflectionMethod(TaxOptimisationService::class, 'buildSpousalStrategy'))
        ->invoke(app(TaxOptimisationService::class), $u, 80000.0, 'higher');

    expect($spousalStrategy($user->fresh()))->not->toBeNull();

    $spouse->delete();
    $survivor = $user->fresh();

    expect($spousalStrategy($survivor))->toBeNull()
        // marital_status is untouched — that may still be the truth of their life.
        ->and($survivor->marital_status)->toBe('married')
        ->and($survivor->liveSpouseId())->toBeNull();
});

// ─── Write and read access to the retained record (D5) ───────────────────────

it('refuses to hand the survivor the deleted partner\'s profile', function (): void {
    [$user, $spouse] = linkedCouple();
    $spouse->delete();

    $this->actingAs($user->fresh(), 'sanctum')
        ->getJson("/api/users/{$spouse->id}")
        ->assertStatus(403);
});

it('refuses to let the survivor edit the deleted partner\'s expenditure', function (): void {
    [$user, $spouse] = linkedCouple();
    $spouse->delete();

    $this->actingAs($user->fresh(), 'sanctum')
        ->putJson("/api/users/{$spouse->id}/expenditure", ['monthly_expenditure' => 999])
        ->assertStatus(403);

    expect((float) User::withTrashed()->find($spouse->id)->monthly_expenditure)->not->toBe(999.0);
});

// ─── The counterpart: where reading the raw column is CORRECT (W-0368 C2c) ───
//
// Everything above exists because reading raw `spouse_id` bypassed the
// soft-delete filter and leaked a deleted partner's data. That makes this file
// the right home for the one question that must read it: **are these two
// married**, which is not the same question as **may I show their data**.
//
// IHTA 1984 s161 values a spouse's related property on a substituted basis, so
// an undivided share held with a spouse takes no marketability discount. Asking
// that through `liveSpouseId()` meant a deleted account switched the discount ON
// over a spouse's share and understated Inheritance Tax — measured on W-0368.
// Deleting an account is not a divorce. The link ends when it is genuinely
// broken, and `FamilyMembersController` nulls `spouse_id` on both sides then.

it('keeps naming the spouse for a relationship question after the account is deleted', function (): void {
    [$user, $spouse] = linkedCouple();
    $spouse->delete();

    $survivor = $user->fresh();

    // The two questions diverge here, and that divergence is the whole point.
    expect($survivor->spouseIdRegardlessOfAccountState())->toBe($spouse->id)
        ->and($survivor->liveSpouseId())->toBeNull();
});

it('names the spouse while the account is live too', function (): void {
    [$user, $spouse] = linkedCouple();

    expect($user->fresh()->spouseIdRegardlessOfAccountState())->toBe($spouse->id);
});

it('stops naming them once the link is genuinely broken', function (): void {
    [$user, $spouse] = linkedCouple();

    // What an unlink does — both sides, as FamilyMembersController writes it.
    $user->update(['spouse_id' => null]);
    $spouse->update(['spouse_id' => null]);

    expect($user->fresh()->spouseIdRegardlessOfAccountState())->toBeNull();
});

it('names nobody where there was never a link', function (): void {
    expect(User::factory()->create()->spouseIdRegardlessOfAccountState())->toBeNull();
});

it('is not the authorization check, which is soft-delete scoped', function (): void {
    // The trap this test exists to spring. `hasReciprocalSpouseLink()` is the
    // named home for "may I attach a joint_owner_id", and it looks like the
    // obvious thing to consolidate onto — but its existence check runs under
    // User's SoftDeletes global scope, so it goes false on deletion. Routing the
    // relationship question through it reinstates the W-0368 understatement in
    // silence. If someone does, this reddens.
    [$user, $spouse] = linkedCouple();
    $spouse->delete();

    $survivor = $user->fresh();

    expect($survivor->hasReciprocalSpouseLink($spouse->id))->toBeFalse()
        ->and($survivor->spouseIdRegardlessOfAccountState())->toBe($spouse->id);
});
