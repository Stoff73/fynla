<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticCorpusLoader;
use App\Services\AI\Memory\SemanticRetriever;
use Illuminate\Support\Carbon;

it('loads the real shipped corpus without throwing (deploy-time fail-closed gate)', function (): void {
    // Uses the real config('fyn.memory.semantic_path'); proves the shipped corpus is valid.
    expect(fn () => app(SemanticCorpusLoader::class)->all())->not->toThrow(Throwable::class);
});

it('contains no £ figures in any semantic fact (v0.5 — figures are procedural pointers, not frozen facts)', function (): void {
    $facts = app(SemanticCorpusLoader::class)->all();

    expect($facts)->toBeArray(); // baseline assertion so the guard is never zero-assertion/risky

    foreach ($facts as $fact) {
        expect($fact->body)->not->toMatch(
            '/£\s?\d/',
            "semantic fact {$fact->factId} ({$fact->category}) contains a £ figure — under the v0.5 pointer model every figure belongs in its live source via a procedural pointer, never frozen in the corpus"
        );
    }
});

// Walk fix plan T14 (M5): every shipped fact names its source.
it('gives every shipped fact a source', function (): void {
    foreach (app(SemanticCorpusLoader::class)->all() as $fact) {
        expect($fact->source)->not->toBe('', "semantic fact {$fact->factId} has no source");
    }
});

// Walk fix plan T14 (F17): a question about tax on gifts before death reaches
// the corpus entry that answers it.
it('finds the gifts-within-seven-years entry for a question about tax on a gift', function (): void {
    $facts = app(SemanticRetriever::class)
        ->retrieve('Who pays the Inheritance Tax on a gift if I die within seven years of giving it?', Carbon::now());

    expect(array_map(fn ($f) => $f->factId, $facts))->toContain('tax-iht-gifts-within-seven-years');
});
