<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

/**
 * S0.10 — User-prompt-field sanitisation + structural separation
 * (INV-2.10.4).
 *
 * Two-layer defence applied at every prompt-builder interpolation site
 * for user-controlled free-text fields (names, account names, goal
 * names, employer / institution / provider strings, addresses, etc.):
 *
 *   1. clean()  — strip injection-relevant characters (angle brackets,
 *      curly braces, square brackets, parens, semicolons, quotes,
 *      backticks, pipes, backslashes, control characters, zero-width
 *      separators) so an attacker cannot smuggle template injection,
 *      shell metacharacters, or prompt-override punctuation into the
 *      system prompt.
 *
 *   2. wrap()   — surround the cleaned value with
 *      <user_provided>...</user_provided> markers so Claude can tell
 *      structurally where system-controlled labels end and untrusted
 *      content begins. The markers themselves are stripped from any
 *      attacker payload by clean(), so they remain a reliable boundary.
 *
 * Charset policy (April30Updates F-2): we now use a denylist rather
 * than the original whitelist. The whitelist locked out non-ASCII names
 * (François → Franois, Müller → Mller, 李 → empty) which is a
 * real inclusivity bug AND a memory-consistency risk — the LLM saw a
 * different name than the DB stores, breaking the "do not re-ask known
 * facts" contract. The denylist preserves Unicode letters and accepted
 * punctuation while still blocking every character used in known
 * injection vectors.
 */
final class UserContentSanitiser
{
    /**
     * Characters we strip — injection-relevant punctuation, shell
     * metacharacters, control chars (except tab/LF/CR), and zero-width
     * / format / symbol characters commonly used to disguise payloads.
     *
     * Stripped:
     *   < >  — XML/HTML tag delimiters (incl. our own <user_provided> wrapper)
     *   { }  — Jinja/Twig/Mustache template injection
     *   [ ]  — Markdown link / array index injection
     *   ( )  — function-call disguise
     *   ;    — statement terminator (SQL/JS)
     *   "    — string-close + escape
     *   `    — backtick / template-literal / shell command
     *   |    — shell pipe
     *   \    — escape character
     *   $    — variable / template marker
     *
     *   @ # &  — directive / preprocessor / HTML-entity markers
     *   = +  — assignment / concat used in some injection chains
     *   * ^ ~ — wildcards / format specifiers
     *   / : ? — URL components used in link injection
     *   \x00-\x08\x0B\x0C\x0E-\x1F\x7F — control chars except \t \n \r
     *   \p{Cf} \p{Co} \p{Cn}  — format, private-use, unassigned
     *   \p{So}                 — emoji & other symbols
     *
     * Preserved (whitelisted from \p{C}):
     *   \t \n \r — tab, line feed, carriage return — needed for
     *   multi-line address fields and similar legit content.
     */
    private const DENYLIST_PATTERN = '/[<>{}\[\]();"`|\\\\$@#&=+*^~\/:?]|[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}|\p{Co}|\p{Cn}|\p{So}/u';

    public static function clean(string $value): string
    {
        return preg_replace(self::DENYLIST_PATTERN, '', $value) ?? '';
    }

    public static function wrap(string $value): string
    {
        return '<user_provided>'.self::clean($value).'</user_provided>';
    }

    /** Controls other than tab and line breaks, format (zero-width), private-use and unassigned characters. */
    private const INVISIBLE_PATTERN = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}|\p{Co}|\p{Cn}/u';

    /** Anything shaped like an opening or closing tag; the capture is its text without the brackets. */
    private const TAG_PATTERN = '/<\s*(\/?\s*[A-Za-z_][\w:.-]*(?:\s[^<>]*)?)\s*>/u';

    /**
     * The user's own chat message, as the model reads it in <user_message>
     * (T1, fix plan 2026-10-12). It stays exactly as typed: every "?", "@",
     * "+", "/", ":" and "&" is part of what the user said, and clean() above
     * stripped them all (production message 4060 reached the model as
     * "isenbretdev1011gmail.com", and every question lost its question mark).
     * Only two things go: invisible characters, and the angle brackets of
     * anything shaped like a tag, so a message cannot close the
     * <user_message> block or open a <context> block of its own. The tag
     * pass repeats until nothing tag-shaped is left: one pass turned
     * "<</user_message>>" into a real "</user_message>" (security review,
     * 2026-10-12). Each pass removes two characters, so it always ends.
     */
    public static function message(string $value): string
    {
        $text = preg_replace(self::INVISIBLE_PATTERN, '', $value) ?? '';

        do {
            $before = $text;
            $text = preg_replace(self::TAG_PATTERN, '$1', $text) ?? '';
        } while ($text !== $before);

        return $text;
    }
}
