<?php

declare(strict_types=1);

use App\Models\Chattel;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * W-0025 — a joint chattel saved with no joint owner and no error, leaving 50%
 * of the asset attributed to nobody: invisible to the spouse (every joint read
 * is `WHERE user_id = ? OR joint_owner_id = ?`) and missing from every
 * household total.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $this->spouse->id]);
    $this->spouse->update(['spouse_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

it('refuses a joint chattel that names nobody to share it with', function () {
    $this->postJson('/api/chattels', [
        'chattel_type' => 'art',
        'name' => 'Contemporary Art Collection',
        'current_value' => 35000,
        'ownership_type' => 'joint',
        // Joint Owner left on "Select joint owner" — this used to save 201.
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['joint_owner_id']);

    $this->assertDatabaseMissing('chattels', ['name' => 'Contemporary Art Collection']);
});

it('accepts a joint chattel shared with a linked spouse', function () {
    $this->postJson('/api/chattels', [
        'chattel_type' => 'art',
        'name' => 'Contemporary Art Collection',
        'current_value' => 35000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $this->spouse->id,
    ])->assertCreated();

    $chattel = Chattel::where('name', 'Contemporary Art Collection')->firstOrFail();

    expect($chattel->ownership_type)->toBe('joint')
        ->and($chattel->joint_owner_id)->toBe($this->spouse->id)
        ->and((float) $chattel->ownership_percentage)->toEqual(50.00)
        ->and((float) $chattel->current_value)->toEqual(35000.00);
});

it('accepts a joint chattel shared with someone off the platform, by name', function () {
    // The persona's tenants-in-common co-owner is exactly this case: a real
    // counterparty who has no account. `joint_owner_name` is how the chattels,
    // properties and mortgages tables express it.
    $this->postJson('/api/chattels', [
        'chattel_type' => 'antique',
        'name' => 'Georgian Writing Desk',
        'current_value' => 8500,
        'ownership_type' => 'joint',
        'joint_owner_name' => 'Mike Barrett',
    ])->assertCreated();

    $chattel = Chattel::where('name', 'Georgian Writing Desk')->firstOrFail();

    expect($chattel->joint_owner_id)->toBeNull()
        ->and($chattel->joint_owner_name)->toBe('Mike Barrett')
        ->and((float) $chattel->ownership_percentage)->toEqual(50.00);
});

it('refuses a stated 100 on a joint chattel instead of halving it', function () {
    // This used to return 201 with 50 stored: a submitted 100 was read as an
    // uncleared individual default. "I own all of it" is individual ownership,
    // not a joint 50/50 record, so it is refused rather than rewritten (W-0040).
    $this->postJson('/api/chattels', [
        'chattel_type' => 'vehicle',
        'name' => 'BMW X5 xDrive40i',
        'current_value' => 42000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $this->spouse->id,
        'ownership_percentage' => 100,
    ])->assertStatus(422)->assertJsonValidationErrors('ownership_percentage');

    expect(Chattel::where('name', 'BMW X5 xDrive40i')->exists())->toBeFalse();
});

it('gives a joint chattel a 50/50 split when the form states no share', function () {
    // The chattel modal states a share only where it shows the input, so every
    // other type arrives with nothing and is defaulted here (W-0040).
    $this->postJson('/api/chattels', [
        'chattel_type' => 'vehicle',
        'name' => 'Land Rover Defender',
        'current_value' => 55000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $this->spouse->id,
    ])->assertCreated();

    expect((float) Chattel::where('name', 'Land Rover Defender')->firstOrFail()->ownership_percentage)
        ->toEqual(50.00);
});

it('keeps a deliberate uneven chattel split', function () {
    $this->postJson('/api/chattels', [
        'chattel_type' => 'collectible',
        'name' => 'First Edition Books',
        'current_value' => 12000,
        'ownership_type' => 'joint',
        'joint_owner_id' => $this->spouse->id,
        'ownership_percentage' => 70,
    ])->assertCreated();

    expect((float) Chattel::where('name', 'First Edition Books')->firstOrFail()->ownership_percentage)
        ->toEqual(70.00);
});

it('refuses an update that would strip the counterparty off an existing joint chattel', function () {
    $chattel = Chattel::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $this->spouse->id,
        'joint_owner_name' => null,
    ]);

    // A partial update naming only one half of the pair must still be resolved
    // against the stored record, or it silently orphans the asset.
    $this->putJson("/api/chattels/{$chattel->id}", ['joint_owner_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['joint_owner_id']);

    expect($chattel->fresh()->joint_owner_id)->toBe($this->spouse->id);
});

it('leaves an individually owned chattel alone', function () {
    $this->postJson('/api/chattels', [
        'chattel_type' => 'jewelry',
        'name' => 'Engagement Ring',
        'current_value' => 6000,
        'ownership_type' => 'individual',
    ])->assertCreated();

    $chattel = Chattel::where('name', 'Engagement Ring')->firstOrFail();

    expect($chattel->ownership_type)->toBe('individual')
        ->and((float) $chattel->ownership_percentage)->toEqual(100.00)
        ->and($chattel->joint_owner_id)->toBeNull();
});

it('returns a success response when a chattel is deleted, not a 500', function () {
    // destroy() returned noContent() against a declared : JsonResponse type, so
    // the row was removed and the request THEN threw — the user was shown an
    // error for an action that had already succeeded (W-0041).
    $chattel = Chattel::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $this->deleteJson("/api/chattels/{$chattel->id}")
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertSoftDeleted('chattels', ['id' => $chattel->id]);
});

it('returns a success response when deleting a joint chattel too', function () {
    // The joint path takes a different branch (it invalidates the co-owner's
    // cache) and reaches the same return.
    $chattel = Chattel::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $this->spouse->id,
    ]);

    $this->deleteJson("/api/chattels/{$chattel->id}")
        ->assertOk()
        ->assertJson(['success' => true]);
});
