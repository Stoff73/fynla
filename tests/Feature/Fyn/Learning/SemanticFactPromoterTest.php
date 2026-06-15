<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\Learning\SemanticFactPromoter;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->dir]);
});
afterEach(fn () => File::deleteDirectory($this->dir));

it('promotes an approved fact to the per-user store and marks it approved', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'retires-2041',
        'title' => 'Retires 2041', 'body' => 'User plans to retire in 2041.', 'status' => 'pending',
    ]);

    app(SemanticFactPromoter::class)->approve($fact, $reviewer->id);

    expect($fact->fresh()->status)->toBe('approved')
        ->and($fact->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and($fact->fresh()->reviewed_at)->not->toBeNull()
        ->and(app(UserSemanticStore::class)->forUser($user->id))->toHaveCount(1);
});

it('rejects a fact without writing the store', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'x',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);

    app(SemanticFactPromoter::class)->reject($fact, $reviewer->id);

    expect($fact->fresh()->status)->toBe('rejected')
        ->and($fact->fresh()->reviewed_at)->not->toBeNull()
        ->and(app(UserSemanticStore::class)->forUser($user->id))->toBe([]);
});

it('promote command approves a pending fact via artisan', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'goal-house',
        'title' => 'Goal: house', 'body' => 'User wants to buy a house by 2030.', 'status' => 'pending',
    ]);

    $this->artisan('fyn:semantic:promote', [
        'fact' => $fact->id,
        '--reviewer' => $reviewer->id,
    ])->assertSuccessful();

    expect($fact->fresh()->status)->toBe('approved')
        ->and($fact->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and(app(UserSemanticStore::class)->forUser($user->id))->toHaveCount(1);
});

it('promote command returns failure for a non-existent fact', function () {
    $this->artisan('fyn:semantic:promote', [
        'fact' => 99999999,
    ])->assertFailed();
});

it('promote command refuses a non-pending fact and writes nothing', function () {
    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'already-approved',
        'title' => 'Already approved', 'body' => 'This fact was already approved.', 'status' => 'approved',
    ]);

    $this->artisan('fyn:semantic:promote', [
        'fact' => $fact->id,
    ])->assertExitCode(1);

    expect(app(UserSemanticStore::class)->forUser($user->id))->toBe([]);
});
