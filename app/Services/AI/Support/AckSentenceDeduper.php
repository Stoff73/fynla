<?php

declare(strict_types=1);

namespace App\Services\AI\Support;

/**
 * A2 — collapse the repeated short acks the model emits once per
 * agent-loop pass ("Got it — recording those now.Recorded." family).
 * On each continuation the model re-narrates its acknowledgment and the
 * buffered prose stacks; the May-18 captureTurnCompleteDirective dampens
 * this but does not eliminate it.
 *
 * Shared by the OnboardingChatDirector (live-stream dedupe) and HasAiChat
 * (persistence of delegated data_capture turns) so what streams and what
 * reloads from the transcript are the SAME text — before this, the live
 * stream deduped but the persisted row kept the doubled ack verbatim.
 *
 * A consecutive sentence is dropped when, against its predecessor, it is:
 *   (a) identical (case-insensitive); or
 *   (b) a short (<=6 words) textual prefix-duplicate of it
 *       ("Recorded the ISA." + "Recorded."); or
 *   (c) a bare standalone acknowledgment ("Recorded.", "Got it.",
 *       "Done.", "Noted.") following ANY prior sentence — a bare ack
 *       carries no information regardless of what preceded it; the
 *       closed set in isBareAck() never matches informative content.
 *
 * The split pattern uses `\s*` (not `\s+`) so "now.Recorded." splits into
 * two sentences despite the missing space. Legitimate multi-sentence
 * answers survive untouched: a substantive follow-on ("It saves Y.", "Now
 * your savings outside an ISA.") is neither a prefix-duplicate nor a bare
 * ack, and the new record-stating ack ("Recorded — two ISAs …") is long
 * enough never to qualify as the short predecessor in (c).
 */
final class AckSentenceDeduper
{
    public static function dedupe(string $text): string
    {
        // Split after sentence punctuation followed by whitespace OR directly
        // by an uppercase/£ start ("now.Recorded." has no space). A full stop
        // followed by a digit or lowercase is NOT a boundary — "3.25%" and
        // "e.g. rates" must survive intact (live-browser regression 2026-06-11:
        // the old `\s*` split decimals and re-joined them as "3. 25%").
        $sentences = preg_split('/(?<=[.!?])\s+|(?<=[.!?])(?=[A-Z£])/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $deduped = [];
        foreach ($sentences as $s) {
            $prev = $deduped === [] ? null : end($deduped);
            if ($prev !== null && (strcasecmp($prev, $s) === 0
                || (str_word_count($s) <= 6 && str_word_count($prev) <= 6
                    && (str_starts_with(strtolower($prev), strtolower(rtrim($s, '.!?')))
                        || str_starts_with(strtolower($s), strtolower(rtrim($prev, '.!?')))))
                || self::isBareAck($s))) {
                continue;
            }
            $deduped[] = $s;
        }

        return implode(' ', $deduped);
    }

    /**
     * A2 — is the sentence a bare standalone acknowledgment with no payload?
     * The closed re-narration family the model stacks ("Recorded.", "Got it.",
     * "Done.", "Noted.", "Got it — recording those now."). Matched on the
     * sentence's lowercased alphabetic content so trailing punctuation, the
     * em-dash, and casing don't matter. A record-stating ack that names what
     * was captured ("Recorded — two ISAs totalling £22,000.") carries extra
     * words and is NOT bare, so it never matches.
     */
    private static function isBareAck(string $sentence): bool
    {
        $core = strtolower(trim(preg_replace('/[^\p{L}\s]+/u', ' ', $sentence) ?? ''));
        $core = trim(preg_replace('/\s+/', ' ', $core) ?? '');

        return in_array($core, [
            'recorded',
            'got it',
            'done',
            'noted',
            'got it recording those now',
        ], true);
    }
}
