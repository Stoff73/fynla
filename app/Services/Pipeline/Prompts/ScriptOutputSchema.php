<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Prompts;

use RuntimeException;

/**
 * Canonical JSON output shape for the video script generator + a simple
 * validator. Kept alongside BrandVoicePrompt so the two are versioned in
 * lockstep.
 */
class ScriptOutputSchema
{
    /** @var list<string> Required top-level fields. */
    public const REQUIRED_FIELDS = [
        'title',
        'hook',
        'script',
        'analogy_used',
        'key_concept',
        'cta_text',
        'estimated_duration_seconds',
    ];

    public static function promptInstruction(): string
    {
        return <<<'PROMPT'
        # Output

        Respond with a single JSON object and NOTHING ELSE. No preamble, no code
        fence, no trailing commentary. Just the JSON.

        Schema:

        {
          "title": "Under 80 characters. Punchy. Works as a video title on YouTube.",
          "hook": "The first sentence of the script. 5–8 seconds spoken.",
          "script": "The full 150–160 word script as one continuous piece of prose. No section headers.",
          "analogy_used": "One sentence naming the analogy or story you used (for reviewer sanity checking).",
          "key_concept": "One sentence stating the single takeaway.",
          "cta_text": "The closing call-to-action, exactly as it appears in the script.",
          "estimated_duration_seconds": 60
        }

        Every field is required. `estimated_duration_seconds` must be an integer
        between 45 and 75.
        PROMPT;
    }

    /**
     * Validate a decoded array against the schema. Throws on any violation.
     *
     * @param  array<string,mixed>  $data
     */
    public static function validate(array $data): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                throw new RuntimeException("Script JSON missing required field: {$field}");
            }
        }

        if (! is_string($data['title']) || $data['title'] === '' || strlen($data['title']) > 120) {
            throw new RuntimeException('Script title must be a non-empty string under 120 chars.');
        }
        if (! is_string($data['hook']) || trim($data['hook']) === '') {
            throw new RuntimeException('Script hook must be a non-empty string.');
        }
        if (! is_string($data['script']) || str_word_count($data['script']) < 100) {
            throw new RuntimeException('Script body must be at least 100 words.');
        }
        if (! is_string($data['analogy_used']) || trim($data['analogy_used']) === '') {
            throw new RuntimeException('Analogy_used must be a non-empty string.');
        }
        if (! is_string($data['key_concept']) || trim($data['key_concept']) === '') {
            throw new RuntimeException('Key_concept must be a non-empty string.');
        }
        if (! is_string($data['cta_text']) || trim($data['cta_text']) === '') {
            throw new RuntimeException('Cta_text must be a non-empty string.');
        }
        if (! is_int($data['estimated_duration_seconds']) || $data['estimated_duration_seconds'] < 45 || $data['estimated_duration_seconds'] > 75) {
            throw new RuntimeException('Estimated_duration_seconds must be an integer 45–75.');
        }
    }

    /**
     * Parse and validate the model's raw text response. Tolerates a few common
     * "helpful" wrappers (code fence, leading prose) before giving up.
     *
     * @return array<string,mixed>
     */
    public static function parse(string $raw): array
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|```\s*$/i', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        if (! str_starts_with($trimmed, '{')) {
            $firstBrace = strpos($trimmed, '{');
            $lastBrace = strrpos($trimmed, '}');
            if ($firstBrace !== false && $lastBrace !== false) {
                $trimmed = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
            }
        }

        $data = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException('Script response was not a JSON object.');
        }

        self::validate($data);

        return $data;
    }
}
