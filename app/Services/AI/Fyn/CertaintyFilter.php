<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * Keeps Fyn from stating how certain it is (CLAUDE.md Rule 12; CSJ ruling
 * 2026-09-29: "we cannot have Fyn stating any certainty, all figures given must
 * be empirical"). Asked "how sure are you", the model graded its own answers
 * ("Certainty: very high", "the amounts are presented as firm rather than
 * estimates") despite a prompt rule, so the rule is enforced on the output.
 *
 * The ONE output filter for model text, applied where every provider streams it
 * (HasAiChat), so web, /m and iOS receive and store the same filtered reply. It
 * holds streamed text back to the end of each sentence or line and drops any
 * sentence that grades certainty or confidence, or gives a score out of 10 or
 * 100. Figures and what they rest on pass through untouched.
 *
 * It also keeps Fyn from saying it saved something it did not (TODO item 7a,
 * walking release #1071: "I have updated your date of birth" with no write;
 * the prompt's "never fabricate a confirmation" did not stop it). In the
 * read-only advice state, where nothing can be saved, a sentence claiming a
 * save is dropped, the first replaced by NOT_SAVED.
 */
final class CertaintyFilter
{
    /** What Fyn says in place of a save it did not make. */
    public const NOT_SAVED = 'That has not been saved yet.';

    /** A sentence matching any of these says something was saved. */
    private const WRITE_CLAIM_PATTERNS = [
        // "I have updated", "I've saved", "we've recorded", "I have now added"
        '/\b(I|we)\s*(\x{2019}|\')?\s*(ve|have)\s+(now\s+|just\s+|also\s+|successfully\s+)?(updated|saved|recorded|added|changed|amended|noted|stored|entered|logged|deleted|removed)\b/iu',
        // "I updated your date of birth"
        '/\b(I|we)\s+(updated|saved|recorded|added|changed|amended|stored|entered|logged|deleted|removed)\s+(your|the|it|that|this|them)\b/iu',
        // Fyn's own acknowledgement line: "Recorded — …", "Updated: …"
        '/^\s*\**(Recorded|Updated|Saved|Added|Deleted|Removed)\**\s*[\x{2014}\x{2013}:-]/iu',
        // "Your date of birth has been updated"
        '/\b(has|have)\s+been\s+(updated|saved|changed|amended|deleted|removed)\b/iu',
        // "is now recorded as 3 May 1990" (a record described as it stands, "is
        // recorded as", passes: only "now" makes it a change)
        '/\b(is|are)\s+now\s+(recorded|saved|updated|set|stored|amended|changed|listed)\b/iu',
        // "I've made that change", "the change has been made"
        '/\b(I|we)\s*(\x{2019}|\')?\s*(ve|have)\s+made\s+(that|the|this|your)\s+(change|update|correction)s?\b/iu',
        '/\b(the|that|this|your)\s+(change|update|correction)s?\s+(has|have|is|are)\s+(been\s+)?(made|saved|applied|done)\b/iu',
    ];

    private bool $claimReplaced = false;

    public function __construct(private readonly bool $writeClaimsAreFalse = false) {}

    /** A sentence matching any of these states certainty and is dropped. */
    private const PATTERNS = [
        // "Certainty: high", "**Confidence level** — very high", "certainty rating"
        '/\b(certainty|confidence)(\s+(level|rating|score))?\W{0,4}\s*[:\x{2013}\x{2014}-]/iu',
        // "very high certainty", "a high degree of confidence"
        '/\b(high|low|medium|moderate|strong|full|complete|very\s+high)\s+(degree\s+of\s+)?(certainty|confidence)\b/iu',
        // "I am confident", "I'm fairly sure", "we are certain"
        '/\b(I|I\x{2019}m|I\'m|we|we\'re|we\x{2019}re)\s+(am\s+|are\s+)?(very\s+|fairly\s+|quite\s+|highly\s+|completely\s+|reasonably\s+)?(confident|certain|sure)\b/iu',
        // "how sure / certain / confident" in Fyn's own voice about its figures
        '/\b(figures?|amounts?|savings?|numbers?|calculations?|results?|estimates?|arithmetic|maths)\s+(is|are)\s+(fixed|firm|certain|definite|exact|reliable|locked\s+in)\b/iu',
        // "fixed rather than estimates", "exact, not an estimate"
        '/\b(fixed|firm|certain|definite|exact)\W{0,3}\s*(rather\s+than|not)\s+(an?\s+)?estimates?\b/iu',
        '/\bpresented\s+as\s+(firm|certain|definite)\b/iu',
        '/\blocked\s+in\s+as\s+soon\s+as\b/iu',
        // A grade out of 10 or 100 ("7/10", "85 / 100"), never a date or fraction of a date
        '/(?<![\d\/])\d{1,3}(\.\d+)?\s*\/\s*(10|100)\b(?!\s*\/)/u',
    ];

    private string $buffer = '';

    /** Text from the stream; returns what is safe to send now. */
    public function push(string $chunk): string
    {
        $this->buffer .= $chunk;
        $cut = self::lastBoundary($this->buffer);
        if ($cut === null) {
            return '';
        }
        $ready = substr($this->buffer, 0, $cut);
        $this->buffer = substr($this->buffer, $cut);

        return $this->filter($ready);
    }

    /** Whatever is still held, at the end of a stream. */
    public function flush(): string
    {
        $rest = $this->buffer;
        $this->buffer = '';

        return $this->filter($rest);
    }

    private function filter(string $text): string
    {
        $text = self::clean($text);
        if (! $this->writeClaimsAreFalse) {
            return $text;
        }

        $out = '';
        foreach (self::segments($text) as $segment) {
            if (! self::claimsWrite($segment)) {
                $out .= $segment;

                continue;
            }
            if (! $this->claimReplaced) {
                $this->claimReplaced = true;
                $out .= self::NOT_SAVED.(preg_match('/(\s+)$/u', $segment, $m) === 1 ? $m[1] : '');

                continue;
            }
            $out .= str_repeat("\n", min(2, substr_count($segment, "\n")));
        }

        return $out;
    }

    public static function claimsWrite(string $sentence): bool
    {
        foreach (self::WRITE_CLAIM_PATTERNS as $pattern) {
            if (preg_match($pattern, $sentence) === 1) {
                return true;
            }
        }

        return false;
    }

    /** A whole text with every certainty sentence removed. */
    public static function clean(string $text): string
    {
        $out = '';
        foreach (self::segments($text) as $segment) {
            if (! self::statesCertainty($segment)) {
                $out .= $segment;

                continue;
            }
            // A dropped line keeps its line break, so paragraphs stay apart.
            $out .= str_repeat("\n", min(2, substr_count($segment, "\n")));
        }

        return $out;
    }

    public static function statesCertainty(string $sentence): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $sentence) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sentences and lines, each keeping its trailing whitespace, so dropping one
     * leaves the rest of the text as it was.
     *
     * @return list<string>
     */
    private static function segments(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])(?=\s)|(?<=\n)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $segments = [];
        foreach ($parts as $part) {
            // Leading whitespace belongs to the sentence before it.
            if ($segments !== [] && preg_match('/^(\s+)(.*)$/su', $part, $m) === 1) {
                $segments[count($segments) - 1] .= $m[1];
                $part = $m[2];
                if ($part === '') {
                    continue;
                }
            }
            $segments[] = $part;
        }

        return $segments;
    }

    /** Byte offset just past the last sentence end or newline, or null. */
    private static function lastBoundary(string $text): ?int
    {
        $newline = strrpos($text, "\n");
        $sentence = preg_match_all('/[.!?](?=\s)/u', $text, $m, PREG_OFFSET_CAPTURE) > 0
            ? end($m[0])[1] + 1
            : null;
        $cut = max($newline === false ? -1 : $newline + 1, $sentence ?? -1);

        return $cut > 0 ? $cut : null;
    }
}
