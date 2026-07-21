<?php

declare(strict_types=1);

use App\Services\AI\Pointers\PointerRegistry;

beforeEach(function (): void {
    // Ensure the real corpus is used — not a temp dir left by another test run.
    app()->forgetInstance(PointerRegistry::class);
});

it('loads the real pointer corpus without throwing', function (): void {
    expect(fn () => app(PointerRegistry::class)->all())->not->toThrow(Throwable::class);
});

it('contains no £ figures in any pointer body (figures are fetched live, not frozen)', function (): void {
    $pointers = app(PointerRegistry::class)->all();
    expect($pointers)->toBeArray();
    foreach ($pointers as $p) {
        expect($p->body)->not->toMatch('/£\s?\d/', "pointer {$p->pointerId} body has a £ figure — values are fetched via the handler, never frozen in the .md");
    }
});
