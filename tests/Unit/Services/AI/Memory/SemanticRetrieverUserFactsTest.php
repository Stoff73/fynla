<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticRetriever;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = storage_path('app/test-user-semantic-'.uniqid());
    config(['fyn.memory.user_semantic_path' => $this->dir]);
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('includes an approved per-user fact in retrieval for that user', function () {
    app(UserSemanticStore::class)->put(7, 'retires-2041', 'Retires 2041', 'User plans to retire in 2041.');

    $facts = app(SemanticRetriever::class)->retrieveForUser(7, 'when will I retire');

    // adapt: if retrieval returns SemanticFact objects, pluck ->body; if arrays, ['body']
    expect(collect($facts)->map(fn ($f) => is_object($f) ? $f->body : $f['body'])->implode(' '))
        ->toContain('2041');
});

it('returns the same results as retrieve() when no per-user facts exist', function () {
    $retriever = app(SemanticRetriever::class);
    $now = Carbon::now();

    $globalOnly = $retriever->retrieve('pension annual allowance', $now);
    $withUser = $retriever->retrieveForUser(99999, 'pension annual allowance');

    expect(collect($withUser)->map(fn ($f) => $f->factId)->all())
        ->toBe(collect($globalOnly)->map(fn ($f) => $f->factId)->all());
});

it('does not include an irrelevant per-user fact', function () {
    app(UserSemanticStore::class)->put(7, 'pets-fact', 'Pets', 'User owns a golden retriever named Max.');

    $facts = app(SemanticRetriever::class)->retrieveForUser(7, 'pension annual allowance');

    $bodies = collect($facts)->map(fn ($f) => is_object($f) ? $f->body : $f['body'])->implode(' ');
    expect($bodies)->not->toContain('golden retriever');
});

it('does not leak per-user facts from one user to another', function () {
    app(UserSemanticStore::class)->put(7, 'retires-2041', 'Retires 2041', 'User plans to retire in 2041.');

    // Fetch for user 8 — should NOT include user 7's fact
    $facts = app(SemanticRetriever::class)->retrieveForUser(8, 'when will I retire');

    $bodies = collect($facts)->map(fn ($f) => is_object($f) ? $f->body : $f['body'])->implode(' ');
    expect($bodies)->not->toContain('2041');
});
