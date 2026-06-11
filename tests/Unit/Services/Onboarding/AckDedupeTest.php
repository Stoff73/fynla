<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingChatDirector;

/**
 * A2 — OnboardingChatDirector::dedupeAckSentences collapses the repeated
 * short acks the model emits once per agent-loop pass
 * ("Got it — recording those now.Recorded." family) without touching a
 * legitimate multi-sentence answer.
 *
 * Reflection on the private method follows the repo's established pattern
 * (see OffScriptAnswerFilterTest / AssetCaptureEntityExtractorTest).
 */
function invokeDedupe(string $text): string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'dedupeAckSentences');
    $ref->setAccessible(true);

    return (string) $ref->invoke($director, $text);
}

describe('dedupeAckSentences (A2)', function () {
    it('collapses a no-space concatenated double-ack into one sentence', function () {
        // The headline defect: "now.Recorded." has no space, but the split
        // pattern still separates it, and the short prefix-duplicate ack is
        // merged away.
        $result = invokeDedupe('Got it — recording those now.Recorded.');

        expect($result)->toBe('Got it — recording those now.');
    });

    it('collapses two identical short acks into one', function () {
        expect(invokeDedupe('Recorded. Recorded.'))->toBe('Recorded.');
    });

    it('collapses three identical short acks into one', function () {
        expect(invokeDedupe('Recorded. Recorded. Recorded.'))->toBe('Recorded.');
    });

    it('leaves a legitimate two-sentence answer unchanged', function () {
        // Distinct sentences that are not ack-duplicates must survive intact.
        $answer = 'Salary sacrifice means X. It saves Y.';

        expect(invokeDedupe($answer))->toBe('Salary sacrifice means X. It saves Y.');
    });

    it('leaves a record-stating ack followed by a follow-on sentence unchanged', function () {
        // The new A2 ack ("Recorded — two ISAs …") is long (> 6 words), so it
        // can never be treated as a short prefix-duplicate; both sentences
        // survive.
        $text = 'Recorded — two ISAs totalling £22,000. Now your savings outside an ISA.';

        expect(invokeDedupe($text))->toBe('Recorded — two ISAs totalling £22,000. Now your savings outside an ISA.');
    });

    it('returns empty string unchanged', function () {
        expect(invokeDedupe(''))->toBe('');
    });

    it('returns a single sentence unchanged', function () {
        expect(invokeDedupe('Got it — recording those now.'))->toBe('Got it — recording those now.');
    });

    it('collapses a short prefix-duplicate even when casing differs', function () {
        // "Got it." then "got it" (no terminator) — short, prefix-duplicate,
        // case-insensitive match → merged.
        expect(invokeDedupe('Got it. Got it.'))->toBe('Got it.');
    });

    it('does not merge two distinct short sentences that are not prefix-duplicates', function () {
        // Both short, but neither is a prefix of the other → both kept.
        expect(invokeDedupe('All done. Next up.'))->toBe('All done. Next up.');
    });

    it('drops a bare trailing ack even after a long record-stating ack', function () {
        // Review gap: a 7+-word informative ack followed by a bare "Recorded."
        // — the bare ack adds nothing regardless of the previous sentence's
        // length, so branch (c) fires unconditionally.
        expect(invokeDedupe('Recorded — three ISA accounts totalling £22,000.Recorded.'))
            ->toBe('Recorded — three ISA accounts totalling £22,000.');
    });
});
