<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('create_family_member persists a child FamilyMember', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'surname' => 'Carter',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Lily',
        'relationship' => 'child',
        'date_of_birth' => '2018-05-12',
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('family_member');

    $fm = FamilyMember::find($result['entity_id']);
    expect($fm)->not->toBeNull();
    expect($fm->first_name)->toBe('Lily');
    expect($fm->last_name)->toBe('Carter'); // surname defaulted from user
    expect($fm->relationship)->toBe('child');
    expect($fm->is_dependent)->toBeTrue();
});

it('create_family_member infers education_status from age for child', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $sevenYearsAgo = now()->subYears(7)->toDateString();

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Tom',
        'relationship' => 'child',
        'date_of_birth' => $sevenYearsAgo,
    ], $user);

    expect(FamilyMember::find($result['entity_id'])->education_status)->toBe('primary');
});

it('create_family_member maps step_child to child with mapping note', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Sam',
        'relationship' => 'step_child',
    ], $user);

    $fm = FamilyMember::find($result['entity_id']);
    expect($fm->relationship)->toBe('child');
    expect($fm->notes)->toContain('Step child');
});

/**
 * W-0113 — this used to read "persists a spouse without email" and asserted the
 * defect as correct behaviour: a spouse row with `linked_user_id` NULL, no
 * account, and `users.spouse_id` untouched, while the same intent expressed
 * through `capture_spouse_details` linked the household properly. The tool, not
 * the assertion, was wrong. Both tools now enter one path onto
 * SpouseLinkingService.
 */
it('create_family_member refuses a spouse with no email rather than writing an unlinked row', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'relationship' => 'spouse',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['message'])->toContain('email address')
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->spouse_id)->toBeNull();
});

it('create_family_member links the household when given the spouse email', function (): void {
    Mail::fake();
    $user = User::factory()->create(['is_preview_user' => false, 'surname' => 'Carter']);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'relationship' => 'spouse',
        'email' => 'emily@example.com',
        'date_of_birth' => '1985-03-04',
    ], $user);

    expect($result['success'])->toBeTrue();

    $spouseUser = User::where('email', 'emily@example.com')->first();
    $fm = FamilyMember::find($result['entity_id']);

    expect($spouseUser)->not->toBeNull()
        ->and($fm->relationship)->toBe('spouse')
        ->and($fm->linked_user_id)->toBe($spouseUser->id)
        ->and($fm->isLinkedAccount())->toBeTrue()
        ->and($user->fresh()->spouse_id)->toBe($spouseUser->id)
        ->and($spouseUser->spouse_id)->toBe($user->id);

    expect(SpousePermission::where('user_id', $user->id)->where('spouse_id', $spouseUser->id)->value('status'))->toBe('accepted')
        ->and(SpousePermission::where('user_id', $spouseUser->id)->where('spouse_id', $user->id)->value('status'))->toBe('accepted');
});

it('create_family_member produces the same household as capture_spouse_details', function (): void {
    Mail::fake();
    $viaCreate = User::factory()->create(['is_preview_user' => false, 'surname' => 'Carter']);
    $viaCapture = User::factory()->create(['is_preview_user' => false, 'surname' => 'Raman']);

    app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'relationship' => 'spouse',
        'email' => 'emily@example.com',
        'date_of_birth' => '1985-03-04',
    ], $viaCreate);

    app(CoordinatingAgent::class)->executeTool('capture_spouse_details', [
        'first_name' => 'Arjun',
        'last_name' => 'Raman',
        'email' => 'arjun@example.com',
        'date_of_birth' => '1977-06-02',
    ], $viaCapture);

    $shape = function (User $u): array {
        $fm = FamilyMember::where('user_id', $u->id)->where('relationship', 'spouse')->first();
        $spouse = User::find($u->fresh()->spouse_id);

        return [
            'rows' => FamilyMember::where('user_id', $u->id)->where('relationship', 'spouse')->count(),
            'linked' => $fm?->linked_user_id === $spouse?->id,
            'reciprocal_user' => $spouse?->spouse_id === $u->id,
            'reciprocal_row' => FamilyMember::where('user_id', $spouse?->id)->where('linked_user_id', $u->id)->count(),
            'permissions' => SpousePermission::where('user_id', $u->id)->where('spouse_id', $spouse?->id)->value('status')
                .'/'.SpousePermission::where('user_id', $spouse?->id)->where('spouse_id', $u->id)->value('status'),
        ];
    };

    // Rule 20: two entrances, one mechanism — so one outcome.
    expect($shape($viaCreate))->toBe($shape($viaCapture))
        ->and($shape($viaCreate))->toBe([
            'rows' => 1,
            'linked' => true,
            'reciprocal_user' => true,
            'reciprocal_row' => 1,
            'permissions' => 'accepted/accepted',
        ]);
});

it('create_family_member adopts an existing unlinked spouse row rather than adding a second', function (): void {
    Mail::fake();
    $user = User::factory()->create(['is_preview_user' => false, 'surname' => 'Carter']);

    // A row of the shape the old handler wrote, or Fyn's free-text capture writes.
    $orphan = FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'spouse',
        'linked_user_id' => null,
        'first_name' => 'Emily',
        'last_name' => 'Carter',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'relationship' => 'spouse',
        'email' => 'emily@example.com',
    ], $user);

    expect($result['success'])->toBeTrue()
        ->and($result['entity_id'])->toBe($orphan->id)
        ->and(FamilyMember::where('user_id', $user->id)->where('relationship', 'spouse')->count())->toBe(1)
        ->and($orphan->fresh()->linked_user_id)->toBe(User::where('email', 'emily@example.com')->value('id'));
});

it('create_family_member refuses a spouse email belonging to another household and writes nothing', function (): void {
    Mail::fake();
    $user = User::factory()->create(['is_preview_user' => false]);
    $taken = User::factory()->create(['email' => 'taken@example.com']);
    $taken->update(['spouse_id' => User::factory()->create()->id]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Someone',
        'relationship' => 'spouse',
        'email' => 'taken@example.com',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['message'])->toContain('another Fynla household')
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->spouse_id)->toBeNull();
});

it('create_family_member rejects a malformed spouse email before touching the database', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'Emily',
        'relationship' => 'spouse',
        'email' => 'not-an-email',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->spouse_id)->toBeNull();
});

it('create_family_member returns validation_failed without first_name', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'relationship' => 'child',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_family_member blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);
    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'X', 'relationship' => 'child',
    ], $user);
    expect($result['blocked'])->toBeTrue();
});

it('create_family_member return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $result = app(CoordinatingAgent::class)->executeTool('create_family_member', [
        'first_name' => 'X', 'relationship' => 'child',
    ], $user);
    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
});
