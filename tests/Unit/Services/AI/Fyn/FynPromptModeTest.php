<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynPromptMode;

it('defaults to legacy', function (): void {
    config()->set('fyn.prompt_architecture', 'legacy');
    expect(FynPromptMode::isUnified())->toBeFalse();
});

it('detects unified', function (): void {
    config()->set('fyn.prompt_architecture', 'unified');
    expect(FynPromptMode::isUnified())->toBeTrue();
});

it('treats unknown values as legacy (fail-safe)', function (): void {
    config()->set('fyn.prompt_architecture', 'banana');
    expect(FynPromptMode::isUnified())->toBeFalse();
});
