<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\PointerRegistry;

it('binds the three proof handlers into the whitelist', function (): void {
    $reg = app(FetchHandlerRegistry::class);
    expect($reg->ids())->toContain('tax_allowance')->toContain('user_financial')->toContain('recommendations');
});

it('resolves PointerRegistry from the container', function (): void {
    expect(app(PointerRegistry::class))->toBeInstanceOf(PointerRegistry::class);
});

it('loads the real shipped pointer corpus without throwing (every handler resolves)', function (): void {
    expect(fn () => app(PointerRegistry::class)->all())->not->toThrow(Throwable::class);
});
