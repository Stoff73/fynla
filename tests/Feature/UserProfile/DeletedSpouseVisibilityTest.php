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
