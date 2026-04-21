<?php

declare(strict_types=1);

use App\ValueObjects\CaptureContext;

it('constructs with required fields only and defaults the rest', function () {
    $ctx = new CaptureContext(
        reason: 'retirement advice blocked on missing pension data',
        entityTypes: ['dc_pension', 'db_pension'],
    );

    expect($ctx->reason)->toBe('retirement advice blocked on missing pension data')
        ->and($ctx->entityTypes)->toBe(['dc_pension', 'db_pension'])
        ->and($ctx->fieldsNeeded)->toBe([])
        ->and($ctx->pendingAdviceQuestion)->toBeNull()
        ->and($ctx->originatingFocus)->toBeNull();
});

it('constructs with all five fields supplied', function () {
    $ctx = new CaptureContext(
        reason: 'user requested inline capture',
        entityTypes: ['savings_account'],
        fieldsNeeded: ['current_balance', 'is_isa'],
        pendingAdviceQuestion: 'What should I do about my pensions?',
        originatingFocus: 'savings',
    );

    expect($ctx->reason)->toBe('user requested inline capture')
        ->and($ctx->entityTypes)->toBe(['savings_account'])
        ->and($ctx->fieldsNeeded)->toBe(['current_balance', 'is_isa'])
        ->and($ctx->pendingAdviceQuestion)->toBe('What should I do about my pensions?')
        ->and($ctx->originatingFocus)->toBe('savings');
});

it('is immutable — readonly properties reject reassignment', function () {
    $ctx = new CaptureContext(reason: 'x', entityTypes: ['savings_account']);

    expect(fn () => $ctx->reason = 'y')->toThrow(\Error::class);
});

it('serialises to snake_case array', function () {
    $ctx = new CaptureContext(
        reason: 'user requested inline capture',
        entityTypes: ['savings_account'],
        fieldsNeeded: ['current_balance'],
        pendingAdviceQuestion: 'What should I do about my pensions?',
        originatingFocus: 'savings',
    );

    expect($ctx->toArray())->toBe([
        'reason' => 'user requested inline capture',
        'entity_types' => ['savings_account'],
        'fields_needed' => ['current_balance'],
        'pending_advice_question' => 'What should I do about my pensions?',
        'originating_focus' => 'savings',
    ]);
});

it('round-trips via fromArray and toArray', function () {
    $payload = [
        'reason' => 'retirement advice blocked',
        'entity_types' => ['dc_pension'],
        'fields_needed' => ['scheme_name', 'current_fund_value'],
        'pending_advice_question' => 'What should I do about my pensions?',
        'originating_focus' => null,
    ];

    $ctx = CaptureContext::fromArray($payload);

    expect($ctx->toArray())->toBe($payload);
});

it('hydrates from a minimal array with only the required keys', function () {
    $ctx = CaptureContext::fromArray([
        'reason' => 'r',
        'entity_types' => ['goal'],
    ]);

    expect($ctx->reason)->toBe('r')
        ->and($ctx->entityTypes)->toBe(['goal'])
        ->and($ctx->fieldsNeeded)->toBe([])
        ->and($ctx->pendingAdviceQuestion)->toBeNull()
        ->and($ctx->originatingFocus)->toBeNull();
});

it('throws when reason is missing from fromArray payload', function () {
    expect(fn () => CaptureContext::fromArray(['entity_types' => ['goal']]))
        ->toThrow(\InvalidArgumentException::class);
});

it('throws when entity_types is missing from fromArray payload', function () {
    expect(fn () => CaptureContext::fromArray(['reason' => 'r']))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects an empty reason in the constructor', function () {
    expect(fn () => new CaptureContext(reason: '', entityTypes: ['goal']))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects an empty entity_types list in the constructor', function () {
    expect(fn () => new CaptureContext(reason: 'r', entityTypes: []))
        ->toThrow(\InvalidArgumentException::class);
});
