<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticRetriever;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    // Point the loader at the REAL repo corpus — this test pins the shipped
    // house_view files, not a synthetic fixture.
    config(['fyn.memory.semantic_path' => base_path('fyn-memory/semantic')]);
});

it('loads house_view rationale on a recommendation-intent turn', function (): void {
    $facts = app(SemanticRetriever::class)->retrieve(
        'What should I do to save tax — any strategies or recommendations for my pension and ISA?',
        Carbon::now(),
    );

    $categories = array_map(static fn ($f) => $f->category, $facts);

    expect($facts)->not->toBeEmpty()
        ->and($categories)->toContain('house_view');
});

it('does not load house_view rationale on an unrelated turn (lean-prompt law)', function (): void {
    $facts = app(SemanticRetriever::class)->retrieve(
        'How do I change the email address on my login?',
        Carbon::now(),
    );

    $categories = array_map(static fn ($f) => $f->category, $facts);

    expect($categories)->not->toContain('house_view');
});
