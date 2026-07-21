<?php

declare(strict_types=1);

use App\Services\Insights\BlockValidator;

beforeEach(fn () => $this->validator = new BlockValidator);

it('passes valid blocks', function () {
    $blocks = [
        ['type' => 'heading', 'level' => 2, 'text' => 'Hello'],
        ['type' => 'paragraph', 'html' => '<p>World</p>'],
        ['type' => 'callout', 'variant' => 'tip', 'html' => '<p>Tip</p>'],
    ];

    expect($this->validator->validate($blocks))->toBe([]);
});

it('rejects unknown block types', function () {
    $errors = $this->validator->validate([['type' => 'bogus']]);

    expect($errors[0])->toContain('Unknown block type: bogus');
});

it('rejects heading without required fields', function () {
    $errors = $this->validator->validate([['type' => 'heading']]);

    expect($errors)->not->toBeEmpty();
});

it('rejects callout with invalid variant', function () {
    $errors = $this->validator->validate([
        ['type' => 'callout', 'variant' => 'orange', 'html' => '<p>x</p>'],
    ]);

    expect($errors[0])->toContain('variant');
});

it('rejects non-array input', function () {
    $errors = $this->validator->validate('not an array');

    expect($errors)->not->toBeEmpty();
});
