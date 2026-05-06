<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;

it('detects direct advice phrasings', function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeTrue();
})->with([
    'Should I contribute more to my pension?',
    'What about my emergency fund?',
    'How much can I afford?',
    'Am I on track for retirement?',
    'Can you explain the ISA allowance?',
    'Why is my pension projection low?',
    'Recommend a savings rate please',
    'I need advice on my investments',
    'Compare SIPP vs workplace pension',
    'Run a projection for age 65',
    'Give me a forecast for next year',
]);

it('does not flag clean data-entry phrasings as advice-shaped', function (string $message) {
    expect(QuerySchemas::isAdviceShaped($message))->toBeFalse();
})->with([
    'Add my Nationwide cash ISA at 5000',
    'Create a pension with Scottish Widows value 50k',
    'Record a gift of 3000 to my daughter',
    'Store a life event wedding 2027 cost 15000',
]);

it('flags mixed-intent question-mark messages as advice-shaped', function () {
    expect(QuerySchemas::isAdviceShaped('Add my Nationwide ISA 5k, thoughts?'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('Add my SIPP 50k DC. Is that enough?'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('Log my pension Scottish Widows 50k. What do you think?'))->toBeTrue();
});

it('does not flag single-clause questions without advice phrases as advice', function () {
    // Simple data-entry clarifications with a trailing ? — one clause only,
    // no advice phrase. Should NOT route through advice.
    expect(QuerySchemas::isAdviceShaped('Can I add it?'))->toBeFalse()
        ->and(QuerySchemas::isAdviceShaped('OK?'))->toBeFalse();
});

it('is case-insensitive', function () {
    expect(QuerySchemas::isAdviceShaped('SHOULD I SAVE MORE'))->toBeTrue()
        ->and(QuerySchemas::isAdviceShaped('should i save more'))->toBeTrue();
});
