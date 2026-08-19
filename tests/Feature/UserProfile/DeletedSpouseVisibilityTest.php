<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    $user = User::factory()->create();
    $spouse = User::factory()->create();
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
