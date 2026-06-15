<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\Learning\SemanticFactPromoter;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->userDir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->userDir]);
});
afterEach(fn () => File::deleteDirectory($this->userDir));

it('learning never writes the global semantic corpus', function () {
    $corpus = (string) config('fyn.memory.semantic_path');
    $before = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;

    $user = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'x',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);
    app(SemanticFactPromoter::class)->approve($fact, $user->id);

    $after = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;
    expect($after)->toBe($before); // global corpus untouched by the only write path
});

it('a pending fact never reaches the per-user store without approval', function () {
    $user = User::factory()->create();
    ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'y',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);

    // No approval called → store must be empty.
    expect(app(UserSemanticStore::class)->forUser($user->id))->toBe([]);
});

it('a rejected fact is never written to the per-user store', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'z',
        'title' => 't', 'body' => 'b', 'status' => 'pending',
    ]);
    app(SemanticFactPromoter::class)->reject($fact, $reviewer->id);

    expect(app(UserSemanticStore::class)->forUser($user->id))->toBe([])
        ->and($fact->fresh()->status)->toBe('rejected');
});

it('approval writes exactly one per-user fact and the global corpus stays untouched', function () {
    $corpus = (string) config('fyn.memory.semantic_path');
    $before = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;

    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id, 'category' => 'user_profile', 'fact_id' => 'retires-2041',
        'title' => 'Retires 2041', 'body' => 'User plans to retire in 2041.', 'status' => 'pending',
    ]);
    app(SemanticFactPromoter::class)->approve($fact, $reviewer->id);

    $after = File::isDirectory($corpus) ? count(File::allFiles($corpus)) : 0;
    expect($after)->toBe($before)
        ->and(app(UserSemanticStore::class)->forUser($user->id))->toHaveCount(1);
});
