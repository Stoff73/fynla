<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Found live on csjones 2026-08-19: three dependants recorded through Fyn by
 * name — Rosie, Tom, Mia — came back from /api/user/family-members as
 * `name: "Unknown"`, which is the heading the web Family Details page renders.
 *
 * `family_members.name` is NOT NULL DEFAULT 'Unknown'. handleCaptureDependants
 * writes it; handleCreateFamilyMember never did, and the two tools reach the
 * same table. Everything reading `first_name` looked fine, which is why it
 * survived — /m happened to read first_name, the web page reads name.
 */
it('stores a display name, not the column default', function (): void {
    $user = User::factory()->create(['surname' => 'McTest']);

    $result = (new ReflectionMethod(CoordinatingAgent::class, 'handleCreateFamilyMember'))
        ->invoke(app(CoordinatingAgent::class), [
            'first_name' => 'Rosie',
            'relationship' => 'child',
            'date_of_birth' => '2016-04-12',
        ], $user, false);

    $member = FamilyMember::find($result['entity_id']);

    expect($member->name)->toBe('Rosie McTest')
        ->and($member->name)->not->toBe('Unknown')
        // The receipt Fyn reads back to the user and the stored row agree.
        ->and($result['name'])->toBe($member->name);
});

it('stores the first name alone when there is no surname to use', function (): void {
    $user = User::factory()->create(['surname' => null]);

    $result = (new ReflectionMethod(CoordinatingAgent::class, 'handleCreateFamilyMember'))
        ->invoke(app(CoordinatingAgent::class), [
            'first_name' => 'Mia',
            'relationship' => 'child',
        ], $user, false);

    expect(FamilyMember::find($result['entity_id'])->name)->toBe('Mia');
});

it('produces the same row from either tool that writes this table', function (): void {
    $viaCapture = User::factory()->create(['surname' => 'McTest']);
    $viaCreate = User::factory()->create(['surname' => 'McTest']);

    (new ReflectionMethod(CoordinatingAgent::class, 'handleCaptureDependants'))
        ->invoke(app(CoordinatingAgent::class), [
            'dependants' => [['first_name' => 'Tom', 'relationship' => 'child', 'date_of_birth' => '2019-03-03']],
        ], $viaCapture);

    (new ReflectionMethod(CoordinatingAgent::class, 'handleCreateFamilyMember'))
        ->invoke(app(CoordinatingAgent::class), [
            'first_name' => 'Tom',
            'relationship' => 'child',
            'date_of_birth' => '2019-03-03',
        ], $viaCreate, false);

    $captured = FamilyMember::where('user_id', $viaCapture->id)->sole();
    $created = FamilyMember::where('user_id', $viaCreate->id)->sole();

    expect($captured->name)->toBe('Tom McTest')
        ->and($created->name)->toBe($captured->name)
        ->and($created->last_name)->toBe($captured->last_name);
});

// The two Fyn tools were never the whole problem: of the eight places that
// create these rows, four set the name parts and not `name` — both
// spouse-linking paths and both Fyn onboarding paths. Deriving on the model is
// what closes the ones nobody edited.
it('derives the name for a writer that never sets it', function (): void {
    $user = User::factory()->create();

    $member = FamilyMember::create([
        'user_id' => $user->id,
        'relationship' => 'spouse',
        'first_name' => 'Jane',
        'middle_name' => 'Elizabeth',
        'last_name' => 'Doe',
    ]);

    expect($member->fresh()->name)->toBe('Jane Elizabeth Doe');
});

it('leaves a name alone when there are no parts to derive it from', function (): void {
    // OnboardingService takes a whole name and no first_name.
    $user = User::factory()->create();

    $member = FamilyMember::create([
        'user_id' => $user->id,
        'name' => 'Patricia Bennett',
        'relationship' => 'parent',
    ]);

    expect($member->fresh()->name)->toBe('Patricia Bennett');
});

it('never takes over a name a caller set on purpose', function (): void {
    // The update endpoint accepts a display name, and the factory sets one
    // independently of the parts. Filling the default must not touch either.
    $user = User::factory()->create();
    $member = FamilyMember::create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'name' => 'Child One',
        'first_name' => 'Ethyl',
        'last_name' => 'Bashirian',
    ]);

    expect($member->fresh()->name)->toBe('Child One');

    $member->update(['name' => 'Updated Child Name']);

    expect($member->fresh()->name)->toBe('Updated Child Name');
});
