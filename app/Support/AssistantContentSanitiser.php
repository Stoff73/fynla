<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Defence-in-depth scrubber for assistant-generated content.
 *
 * Some LLM providers occasionally emit tool-call markup as plain text
 * inside the content stream (e.g. `<function_call name="x">...</function_call>`)
 * instead of using the structured tool_use API. When that happens the
 * markup leaks into the SSE wire AND into the persisted ai_messages row,
 * which (a) shows the user XML in their chat bubble and (b) breaks any
 * downstream assertion that visible chat text contains no `<` / `>`.
 *
 * This sanitiser strips those leaked tool-call blocks deterministically.
 * It does NOT try to recover the intended tool call — that's the
 * write-intent classifier's job. This is purely a "if it leaked through,
 * don't render it to the user" guard.
 */
final class AssistantContentSanitiser
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

        // Collapse the double-newlines the strip leaves behind without
        // mangling intentional paragraph breaks elsewhere in the content.
        return trim(preg_replace('/\n{3,}/', "\n\n", $stripped) ?? $stripped);
    }
}
