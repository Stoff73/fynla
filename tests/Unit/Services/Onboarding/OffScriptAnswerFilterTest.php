<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingChatDirector;

/**
 * A1 — the $allowAnswer arm of OnboardingChatDirector::filterOffScriptContent.
 *
 * Contract:
 *   - answers are allowed through when $allowAnswer is true;
 *   - personal figures (£ amounts) are NEVER let through, even when answering;
 *   - the default path ($allowAnswer false) is byte-identical to the original
 *     FR-M14 filter.
 *
 * Reflection on the private method follows the repo's established pattern
 * (see AssetCaptureEntityExtractorTest et al.).
 */
function invokeFilter(string $text, string $selection, bool $allowAnswer): string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'filterOffScriptContent');
    $ref->setAccessible(true);

    return (string) $ref->invoke($director, $text, $selection, $allowAnswer);
}

describe('filterOffScriptContent — allowAnswer arm (A1)', function () {
    it('lets a two-sentence definitional answer through when allowAnswer is true', function () {
        $answer = 'Salary sacrifice means your employer reduces your salary and pays that amount into your pension instead. '
            .'It saves National Insurance for both of you.';

        $result = invokeFilter($answer, 'savetax', true);

        // The whole definitional answer survives — "salary", "pension" and
        // "income"-adjacent concepts are no longer stripped on an answer turn.
        expect($result)->toContain('Salary sacrifice means your employer reduces your salary')
            ->and($result)->toContain('It saves National Insurance for both of you.');
    });

    it('lets an answer that re-asks the capture question through (question mark allowed on answer turns)', function () {
        $answer = 'Salary sacrifice swaps salary for a pension contribution and saves National Insurance. '
            .'Do you contribute to a pension through salary sacrifice?';

        $result = invokeFilter($answer, 'savetax', true);

        // The re-ask sentence (with its "?") survives because the user asked
        // a question and the turn is permitted to answer + re-ask.
        expect($result)->toContain('Do you contribute to a pension through salary sacrifice?');
    });

    it('still strips a sentence quoting the users own figures even when allowAnswer is true', function () {
        $text = 'Salary sacrifice swaps salary for a pension contribution and saves National Insurance. '
            .'Based on your £110,000 salary you would save £2,200.';

        $result = invokeFilter($text, 'savetax', true);

        // The definitional sentence survives...
        expect($result)->toContain('Salary sacrifice swaps salary for a pension contribution');
        // ...but the personal-figure sentence is dropped (FCA — no figures-based
        // personal advice mid-capture).
        expect($result)->not->toContain('£110,000')
            ->and($result)->not->toContain('£2,200')
            ->and($result)->not->toContain('you would save');
    });

    it('strips a personal-figure sentence with a single currency amount', function () {
        $text = 'Salary sacrifice lowers your taxable pay. You would save £2,200 a year.';

        $result = invokeFilter($text, 'savetax', true);

        expect($result)->toContain('Salary sacrifice lowers your taxable pay.')
            ->and($result)->not->toContain('£2,200');
    });
});

describe('filterOffScriptContent — default path unchanged (A1)', function () {
    it('strips questions on the default path exactly as before (allowAnswer false)', function () {
        // Existing FR-M14 fixture: an ack followed by an off-script question.
        $text = 'Got it — recording those now. Do you own any property?';

        $result = invokeFilter($text, 'family', false);

        expect($result)->toBe('Got it — recording those now.');
    });

    it('strips off-script topic sentences on the default path (allowAnswer false)', function () {
        // The exact Test 6 regression fixture (question without a "?").
        $text = 'Got it — recording those now. With your £50,000 gross income and £3,500 monthly outgoings, '
            .'it looks like you have capacity to build protection cover.';

        $result = invokeFilter($text, 'family', false);

        // The off-script "income" sentence is dropped; the ack survives. This
        // is the original behaviour — the default path is untouched.
        expect($result)->toBe('Got it — recording those now.');
    });

    it('default path does NOT strip a £-only sentence (figures rule is answer-only)', function () {
        // On a normal capture turn the user just stated a balance; the prompt
        // permits echoing figures the user provided THIS message, and the
        // filter never had a £-rule. Keep that behaviour: a £-bearing sentence
        // with no off-script keyword survives on the default path.
        $text = 'Recorded the Halifax ISA at £10,000.';

        $result = invokeFilter($text, 'savings', false);

        expect($result)->toBe('Recorded the Halifax ISA at £10,000.');
    });

    it('default path preserves a clean confirmation unchanged', function () {
        $text = 'Got it — recording those now.';

        expect(invokeFilter($text, 'family', false))->toBe('Got it — recording those now.');
    });
});
