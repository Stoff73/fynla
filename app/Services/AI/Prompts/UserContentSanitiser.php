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
 *   1. clean()  — strip everything outside [A-Za-z0-9\s'.,\-] so an
 *      attacker cannot smuggle template braces, angle brackets,
 *      semicolons, backticks, or prompt-override punctuation into the
 *      system prompt.
 *
 *   2. wrap()   — surround the cleaned value with
 *      <user_provided>...</user_provided> markers so Claude can tell
 *      structurally where system-controlled labels end and untrusted
 *      content begins. The markers themselves are stripped from any
 *      attacker payload by clean(), so they remain a reliable boundary.
 *
 * Out of scope (per spec): widening the allowed character set (locks
 * out non-ASCII names like "François" by design — we accept that
 * trade-off for the prompt-injection floor); sanitising at the DB
 * layer or in the UI; structural separation for non-prompt contexts.
 *
 * Punctuation set is fixed by spec — apostrophe, period, comma, hyphen
 * are the only allowed non-alphanumeric characters (plus whitespace).
 * That intentionally permits "O'Brien" and "Smith-Jones" while blocking
 * `{` `}` `<` `>` `;` `"` `(` `)` `[` `]` `{{ }}` etc.
 */
final class UserContentSanitiser
{
    public static function clean(string $value): string
    {
        return preg_replace("/[^A-Za-z0-9\s'.,\-]/u", '', $value) ?? '';
    }

    public static function wrap(string $value): string
    {
        return '<user_provided>'.self::clean($value).'</user_provided>';
    }
}
