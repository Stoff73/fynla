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

it('agrees with the other tool that writes this table', function (): void {
    $user = User::factory()->create(['surname' => 'McTest']);

    (new ReflectionMethod(CoordinatingAgent::class, 'handleCaptureDependants'))
        ->invoke(app(CoordinatingAgent::class), [
            'dependants' => [['first_name' => 'Tom', 'relationship' => 'child', 'date_of_birth' => '2019-03-03']],
        ], $user);

    expect(FamilyMember::where('user_id', $user->id)->pluck('name'))->not->toContain('Unknown');
});
