<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0051 — the repair for households already holding an orphan or duplicate
 * spouse row. Dry-run by default; idempotent; never hard-deletes.
 */
uses(RefreshDatabase::class);

function linkedPair(): array
{
    $primary = User::factory()->create(['first_name' => 'Priya', 'surname' => 'Raman']);
    $spouse = User::factory()->create(['first_name' => 'Arjun', 'surname' => 'Raman']);

    $primary->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $primary->id]);

    return [$primary->fresh(), $spouse->fresh()];
}

function spouseRow(User $owner, ?int $linkedUserId, array $overrides = []): FamilyMember
{
    return FamilyMember::factory()->create(array_merge([
        'user_id' => $owner->id,
        'relationship' => 'spouse',
        'linked_user_id' => $linkedUserId,
        'first_name' => 'Arjun',
        'last_name' => 'Raman',
        'name' => 'Arjun Raman',
    ], $overrides));
}

it('changes nothing without --force', function () {
    [$primary, $spouse] = linkedPair();
    $orphan = spouseRow($primary, null);

    $this->artisan('family:reconcile-spouse-links')
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect($orphan->fresh()->linked_user_id)->toBeNull();
});

it('adopts a lone orphan onto the live link', function () {
    [$primary, $spouse] = linkedPair();
    $orphan = spouseRow($primary, null);

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])->assertSuccessful();

    expect($orphan->fresh()->linked_user_id)->toBe($spouse->id);
});

it('folds a duplicate into the linked row and retires it without hard-deleting', function () {
    [$primary, $spouse] = linkedPair();

    // The exact live shape: the onboarding orphan holding a date of birth the
    // linked row never captured.
    $orphan = spouseRow($primary, null, ['date_of_birth' => '1977-06-02', 'gender' => 'male']);
    $linked = spouseRow($primary, $spouse->id, ['date_of_birth' => null, 'gender' => null]);

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])->assertSuccessful();

    expect(FamilyMember::where('user_id', $primary->id)->where('relationship', 'spouse')->count())->toBe(1)
        ->and($linked->fresh()->date_of_birth->format('Y-m-d'))->toBe('1977-06-02')
        ->and($linked->fresh()->gender)->toBe('male')
        ->and(FamilyMember::find($orphan->id))->toBeNull()
        // Retained, not destroyed (deleted-spouse-visibility §1).
        ->and(FamilyMember::withTrashed()->find($orphan->id))->not->toBeNull();
});

it('never overwrites a value the linked row already holds', function () {
    [$primary, $spouse] = linkedPair();

    spouseRow($primary, null, ['first_name' => 'Arjunn', 'date_of_birth' => '1970-01-01']);
    $linked = spouseRow($primary, $spouse->id, ['first_name' => 'Arjun', 'date_of_birth' => '1977-06-02']);

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])->assertSuccessful();

    expect($linked->fresh()->first_name)->toBe('Arjun')
        ->and($linked->fresh()->date_of_birth->format('Y-m-d'))->toBe('1977-06-02');
});

it('is idempotent', function () {
    [$primary, $spouse] = linkedPair();
    spouseRow($primary, null, ['date_of_birth' => '1977-06-02']);
    spouseRow($primary, $spouse->id);

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])->assertSuccessful();
    $after = FamilyMember::where('user_id', $primary->id)->where('relationship', 'spouse')->get();

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])
        ->expectsOutputToContain('Duplicate rows folded into the linked row and retired: 0')
        ->assertSuccessful();

    expect(FamilyMember::where('user_id', $primary->id)->where('relationship', 'spouse')->pluck('id')->all())
        ->toBe($after->pluck('id')->all());
});

it('leaves a spouse record alone when there is no account to link to', function () {
    $solo = User::factory()->create(['spouse_id' => null]);
    $record = spouseRow($solo, null);

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])
        ->expectsOutputToContain('Spouse rows left as ordinary records (no live account link): 1')
        ->assertSuccessful();

    expect($record->fresh()->linked_user_id)->toBeNull()
        ->and($record->fresh()->isLinkedAccount())->toBeFalse();
});

it('does not adopt onto a spouse whose account has been deleted', function () {
    [$primary, $spouse] = linkedPair();
    $orphan = spouseRow($primary, null);
    $spouse->delete();

    $this->artisan('family:reconcile-spouse-links', ['--force' => true])->assertSuccessful();

    expect($orphan->fresh()->linked_user_id)->toBeNull();
});

it('can be restricted to one user', function () {
    [$primaryA, $spouseA] = linkedPair();
    [$primaryB, $spouseB] = linkedPair();
    $orphanA = spouseRow($primaryA, null);
    $orphanB = spouseRow($primaryB, null);

    $this->artisan('family:reconcile-spouse-links', ['--force' => true, '--user' => $primaryA->id])
        ->assertSuccessful();

    expect($orphanA->fresh()->linked_user_id)->toBe($spouseA->id)
        ->and($orphanB->fresh()->linked_user_id)->toBeNull();
});
