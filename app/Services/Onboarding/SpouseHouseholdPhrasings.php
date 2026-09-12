<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * The ONE place the SaveTax spouse step reads a pension pot and a pension
 * contribution out of the user's own words. The model maps them to
 * capture_spouse_household_data most of the time; when it drops one (live
 * prod 2026-09-11: "an Aviva pension with 75680 in it and she contributes
 * 500 per month" lost the pot on one run and the contributions on the
 * next), the director backfills from here. Numbers only, never guessed:
 * a sentence without an amount and a cadence yields null.
 */
final class SpouseHouseholdPhrasings
{
    private const AMOUNT = '£?\s*(\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)\s*(k)?';

    /** Gross annual pension contribution in pounds, or null when not stated. */
    public static function pensionContributionAnnual(string $text): ?float
    {
        $lower = mb_strtolower($text);
        // "contributes 500 per month", "pays £500 a month into her pension",
        // "puts in 6,000 a year", "contributions of 500 monthly".
        $pattern = '/\b(?:contribut(?:es|ing|ion(?:s)?\s+(?:of|are|is))|pays?\s+(?:in)?|puts?\s+(?:in|away)|saves?)\s*(?:about|around|roughly)?\s*'.self::AMOUNT
            .'\s*(?:into\s+(?:her|his|their|the)\s+pension\s*)?(?:(per|a|each|every)\s+(month|year|annum)|(monthly|annually|yearly|pcm|pa|p\.a\.))?/u';
        if (preg_match($pattern, $lower, $m) !== 1) {
            return null;
        }
        $amount = self::amount($m[1], $m[2] ?? '');
        $cadence = $m[4] ?? $m[5] ?? '';
        if ($cadence === '') {
            return null;
        }
        $monthly = in_array($cadence, ['month', 'monthly', 'pcm'], true);

        return $monthly ? $amount * 12 : $amount;
    }

    /** Current value of the pension pot(s) the spouse holds, or null when not stated. */
    public static function pensionPotValue(string $text): ?float
    {
        $lower = mb_strtolower($text);
        // "pension with 75680 in it", "pension pot of £75,680", "pension worth
        // 75k", "75,680 in her pension".
        $patterns = [
            '/\bpension\b[^.,;]{0,40}?\b(?:with|of|worth|valued\s+at|currently|at|holding)\s*'.self::AMOUNT.'(?:\s+in\s+it)?/u',
            '/'.self::AMOUNT.'\s+in\s+(?:her|his|their|a|the)\s+pension\b/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower, $m) === 1) {
                return self::amount($m[1], $m[2] ?? '');
            }
        }

        return null;
    }

    /** Words that look like a provider but are not one. */
    private const NOT_A_PROVIDER = ['the', 'a', 'an', 'her', 'his', 'their', 'my', 'our', 'no', 'one', 'it', 'its', 'she', 'he', 'they', 'and', 'with', 'in', 'of', 'workplace', 'personal', 'private', 'state', 'company', 'employer', 'stakeholder', 'sipp', 'cash', 'stocks', 'shares', 'lifetime', 'junior'];

    /** Provider holding the spouse's ISA ("an ISA with Halifax", "Halifax ISA"), or null. */
    public static function isaProvider(string $text): ?string
    {
        // "ISA" arrives as "isa", and as the phone's autocorrections "USA" /
        // "it's" (live prod 2026-09-11) — the provider is what follows "with".
        foreach ([
            '/\b(?i:isa|usa|it\x{2019}s|it\x{0027}s)\b[^.;]{0,20}?\bwith\s+([A-Z][\w&\x{0027}-]*(?:\s+[A-Z][\w&\x{0027}-]*)?)/u',
            '/\b([A-Z][\w&\x{0027}-]*(?:\s+[A-Z][\w&\x{0027}-]*)?)\s+(?:cash\s+|stocks\s+(?:and|&)\s+shares\s+)?(?i:isa)\b/u',
        ] as $pattern) {
            if (preg_match($pattern, $text, $m) === 1 && self::plausibleProvider($m[1])) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /** The spouse's pension provider or scheme ("an Aviva pension", "pension with Aviva"), or null. */
    public static function pensionProvider(string $text): ?string
    {
        foreach ([
            '/\b([A-Z][\w&\x{0027}-]*(?:\s+[A-Z][\w&\x{0027}-]*)?)\s+(?i:pension)\b/u',
            '/\b(?i:pension)\b[^.;]{0,20}?\bwith\s+([A-Z][\w&\x{0027}-]*(?:\s+[A-Z][\w&\x{0027}-]*)?)/u',
        ] as $pattern) {
            if (preg_match($pattern, $text, $m) === 1 && self::plausibleProvider($m[1])) {
                return trim($m[1]);
            }
        }

        return null;
    }

    private static function plausibleProvider(string $candidate): bool
    {
        $words = preg_split('/\s+/', trim($candidate)) ?: [];
        foreach ($words as $word) {
            if (in_array(mb_strtolower($word), self::NOT_A_PROVIDER, true)) {
                return false;
            }
        }

        return $candidate !== '' && preg_match('/\d/', $candidate) !== 1;
    }

    private static function amount(string $raw, string $thousands): float
    {
        $value = (float) str_replace(',', '', $raw);

        return $thousands === 'k' ? $value * 1000 : $value;
    }
}
