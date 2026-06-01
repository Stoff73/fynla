<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticCorpusLoader;

it('loads the real shipped corpus without throwing (deploy-time fail-closed gate)', function (): void {
    // Uses the real config('fyn.memory.semantic_path'); proves the shipped corpus is valid.
    expect(fn () => app(SemanticCorpusLoader::class)->all())->not->toThrow(Throwable::class);
});

it('contains no £ figures in any semantic fact (v0.5 — figures are procedural pointers, not frozen facts)', function (): void {
    $facts = app(SemanticCorpusLoader::class)->all();

    foreach ($facts as $fact) {
        expect($fact->body)->not->toMatch(
            '/£\s?\d/',
            "semantic fact {$fact->factId} ({$fact->category}) contains a £ figure — under the v0.5 pointer model every figure belongs in its live source via a procedural pointer, never frozen in the corpus"
        );
    }
});
