<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Strips leaked `<function_call>` markup from assistant-generated content.
 *
 * xAI's chat completions API occasionally emits tool-call markup as plain
 * text inside the content stream (e.g.
 * `<function_call name="x">...</function_call>`) instead of via the
 * structured tool_use channel. When that happens the markup leaks into the
 * SSE wire AND into the persisted ai_messages row, which (a) shows the user
 * XML in their chat bubble and (b) breaks any downstream assertion that
 * visible chat text contains no `<` / `>`.
 *
 * Scope is narrow on purpose: this class strips the specific xAI leak
 * pattern from assistant OUTPUT and nothing else. It is NOT a prompt-
 * injection guard, NOT a general HTML sanitiser, and does NOT validate
 * user INPUT. Renamed from AssistantContentSanitiser (P0.10) so the name
 * matches what the class actually does — the old name implied a broader
 * threat-model coverage that did not exist.
 */
final class XaiFunctionCallLeakStripper
{
    /**
     * Strip leaked `<function_call ...>...</function_call>` blocks from
     * assistant content. Idempotent. Returns the input unchanged if no
     * markup is present.
     */
    public static function stripLeakedToolCallMarkup(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        // Greedy-non-greedy regex: matches the opening tag (with any attrs)
        // through the matching closing tag, including everything in between
        // — even nested `<argument>` children. The `s` flag lets `.` cross
        // newlines (xAI emits these blocks across multiple lines).
        $stripped = preg_replace(
            '/<function_call\b[^>]*>.*?<\/function_call>/is',
            '',
            $content,
        );

        if ($stripped === null) {
            return $content;
        }

        // Collapse the triple-plus newlines the strip leaves behind without
        // mangling intentional paragraph breaks elsewhere in the content.
        return trim(preg_replace('/\n{3,}/', "\n\n", $stripped) ?? $stripped);
    }
}
