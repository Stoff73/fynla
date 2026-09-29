<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * What a recommendation's `claim_tier` means, in words Fyn can say to a user —
 * the ONE vocabulary for it (Rule 20).
 *
 * `claim_tier` ('mechanical' | 'judgement') is how firmly a strategy may be
 * stated. Fyn was given the raw value in its tool data, and the words
 * "mechanical tier" in its voicing rules and house-view corpus, and repeated
 * them: a user was told about a "mechanical-tier strategy" (2026-09-26 walk).
 * The model now only ever sees the plain wording below: the tool-result
 * boundary rewrites the field (`HasAiChat::compressToolResultForModel`), the
 * voicing rules are composed from it, and the house-view corpus uses the same
 * two labels. The engines and the database keep `claim_tier` as it is.
 */
final class ClaimTier
{
    /** The field name the model sees in place of `claim_tier`. */
    public const MODEL_FIELD = 'basis';

    /** @var array<string, string> claim_tier => what it means, fit to say to a user */
    public const BASIS = [
        'mechanical' => 'Fixed arithmetic: it follows from your own figures and the published tax rules',
        'judgement' => 'A judgement call: it depends on your circumstances and preferences',
    ];

    /** The label before the colon ("Fixed arithmetic"), for the voicing rules and the corpus. */
    public static function label(string $tier): string
    {
        return explode(':', self::BASIS[$tier], 2)[0];
    }

    /**
     * Every `claim_tier` key in a tool result, at any depth, replaced by
     * MODEL_FIELD and its plain wording. An unknown or empty tier is dropped
     * rather than passed through raw.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function forModel(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($key === 'claim_tier') {
                if (is_string($value) && isset(self::BASIS[$value])) {
                    $out[self::MODEL_FIELD] = self::BASIS[$value];
                }

                continue;
            }
            $out[$key] = is_array($value) ? self::forModel($value) : $value;
        }

        return $out;
    }

    /**
     * forModel() over a JSON-encoded value (a fetched live-data block). Text
     * that is not a JSON object or array passes through unchanged.
     */
    public static function forModelJson(string $json): string
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $json;
        }

        return (string) json_encode(self::forModel($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
