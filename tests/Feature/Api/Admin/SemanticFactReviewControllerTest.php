<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Hermetic per-user semantic store: approve() writes here, never the committed corpus.
    $this->semanticPath = storage_path('app/test-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->semanticPath]);
});

afterEach(function (): void {
    File::deleteDirectory($this->semanticPath);
});

it('lists pending facts and approves one for an admin', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'retires-2041',
        'title' => 'Retires 2041', 'body' => 'User plans to retire 2041.', 'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/semantic-facts')
        ->assertOk()
        ->assertJsonFragment(['fact_id' => 'retires-2041']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/semantic-facts/{$fact->id}", ['action' => 'approve'])
        ->assertOk();

    expect($fact->fresh()->status)->toBe('approved')
        ->and($fact->fresh()->reviewed_by)->toBe($admin->id);
});

it('rejects a pending fact for an admin', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'drop-me',
        'title' => 'Drop me', 'body' => 'Noise.', 'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/semantic-facts/{$fact->id}", ['action' => 'reject'])
        ->assertOk();

    expect($fact->fresh()->status)->toBe('rejected')
        ->and($fact->fresh()->reviewed_by)->toBe($admin->id);
});

it('only lists pending facts, not already-reviewed ones', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'pending-one',
        'title' => 'Pending', 'body' => 'b', 'status' => 'pending',
    ]);
    ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'approved-one',
        'title' => 'Approved', 'body' => 'b', 'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/semantic-facts')
        ->assertOk()
        ->assertJsonFragment(['fact_id' => 'pending-one'])
        ->assertJsonMissing(['fact_id' => 'approved-one']);
});

it('forbids a non-admin', function (): void {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'is_advisor' => false]));

    $this->getJson('/api/admin/semantic-facts')->assertForbidden();
});

it('rejects an already-reviewed fact with 422', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'x',
        'title' => 't', 'body' => 'b', 'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/semantic-facts/{$fact->id}", ['action' => 'approve'])
        ->assertStatus(422);
});

it('rejects an unknown action with 422', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'y',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/semantic-facts/{$fact->id}", ['action' => 'nonsense'])
        ->assertStatus(422);

    expect($fact->fresh()->status)->toBe('pending');
});
