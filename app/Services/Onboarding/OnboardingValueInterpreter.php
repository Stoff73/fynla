<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Carbon\Carbon;

/**
 * Parsers for free-text answers during Fyn-driven onboarding.
 *
 * The director calls these instead of delegating to Claude for every
 * structured free-text state (DOB, income, expenditure, retirement date,
 * and the enum free-text fallbacks for marital/employment).
 *
 * Each parser returns null when the input cannot be confidently interpreted
 * — the director uses null as the signal to emit a retry prompt without
 * advancing state.
 *
 * Plan: April/April15Updates/fynOnboardFix.md §2 + §5.
 */
final class OnboardingValueInterpreter
{
    private const MIN_AGE_YEARS = 18;

    private const MAX_AGE_YEARS = 105;

    /**
     * Parse a free-text date of birth into a Y-m-d string.
     *
     * Accepted examples:
     *   "12 January 1985", "12/01/1985", "1985-01-12", "12 Jan 1980"
     *   "19/02/82", "19 Feb 82" (two-digit years resolve to the century
     *   giving an age inside the 18–105 bounds)
     *   "My date of birth is 12 March 1985 and I am married"
     *   "I was born on 12-03-1985", "12th March 1985", "March 12, 1985"
     *
     * When the input contains extra prose around the date (as in the
     * common multi-field onboarding answer above), the parser extracts
     * the date-like substring first and passes only that to Carbon —
     * `Carbon::parse` on the full sentence would throw for most prose.
     *
     * Rejects relative references ("yesterday") and dates that would make
     * the user younger than 18 or older than 105.
     */
    public static function parseDateOfBirth(?string $input): ?string
    {
        $cleaned = self::cleanString($input);
        if ($cleaned === '') {
            return null;
        }

        // Reject obvious relative phrases — Carbon happily parses them
        $lowered = mb_strtolower($cleaned);
        foreach (['yesterday', 'today', 'tomorrow', 'now', 'next', 'last week', 'last month', 'last year'] as $banned) {
            if (str_contains($lowered, $banned)) {
                return null;
            }
        }

        $dateCandidate = self::extractDateSubstring($cleaned) ?? $cleaned;

        try {
            // UK convention for slashed / dashed / dotted numeric dates:
            // "12/03/1985" is 12 March, not December 3rd. Carbon's default
            // for "12/03/1985" is MDY, so handle DMY explicitly. Two-digit
            // years ("19/02/82") resolve to the century that gives a
            // plausible age — strtotime's 00-69 → 20xx pivot would reject
            // anyone born in the 1950s-60s who types '55.
            if (preg_match('#^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})$#', $dateCandidate, $m) === 1) {
                $d = (int) $m[1];
                $mo = (int) $m[2];
                if ($d < 1 || $d > 31 || $mo < 1 || $mo > 12) {
                    return null;
                }
                $date = strlen($m[3]) === 2
                    ? self::resolveTwoDigitYearDob($d, $mo, (int) $m[3])
                    : Carbon::create((int) $m[3], $mo, $d, 0, 0, 0);
                if ($date === null) {
                    return null;
                }
            } elseif (preg_match('#^(\d{1,2})(?:st|nd|rd|th)?\s+([a-z]+)\.?,?\s+(\d{2})$#i', $dateCandidate, $m) === 1) {
                // "19 Feb 82" — textual month with a two-digit year. Resolve
                // the century by age bounds, then let Carbon parse the month.
                $date = self::resolveTwoDigitYearTextualDob((int) $m[1], $m[2], (int) $m[3]);
                if ($date === null) {
                    return null;
                }
            } else {
                $date = Carbon::parse($dateCandidate);
            }
        } catch (\Throwable $e) {
            return null;
        }

        $age = $date->diffInYears(Carbon::now());
        if ($age < self::MIN_AGE_YEARS || $age > self::MAX_AGE_YEARS) {
            return null;
        }

        if ($date->isFuture()) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Pull a date-like substring out of a longer message. Returns the first
     * match or null if no recognisable date pattern is present.
     *
     * Patterns, longest/most specific first so "12 March 1985" matches
     * before "March 1985" would swallow it:
     *   - "12 March 1985" / "12th March 1985" / "12 Mar 1985" / "12 Mar 85"
     *   - "March 12 1985" / "March 12, 1985" / "Mar 12 1985"
     *   - "12/03/1985" / "12-03-1985" / "12.03.1985" / "19/02/82"
     *   - "1985-03-12" / "1985/03/12"
     */
    private static function extractDateSubstring(string $text): ?string
    {
        $months = '(?:january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)';

        $patterns = [
            // DD Month YYYY / DD Month YY — "12 March 1985", "12 Mar 85"
            '/\b(\d{1,2})(?:st|nd|rd|th)?\s+'.$months.'\s+(?:\d{4}|\d{2})\b/i',
            // Month DD YYYY — "March 12 1985", "March 12, 1985"
            '/\b'.$months.'\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4}\b/i',
            // YYYY-MM-DD / YYYY/MM/DD
            '/\b\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\b/',
            // DD/MM/YYYY / DD-MM-YYYY / DD.MM.YYYY / DD/MM/YY
            '/\b\d{1,2}[\/\-.]\d{1,2}[\/\-.](?:\d{4}|\d{2})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return $m[0];
            }
        }

        return null;
    }

    /**
     * Resolve a two-digit birth year to the century that gives an age inside
     * the DOB bounds (18–105). Exactly one century can qualify — the two
     * candidates are 100 years apart. Returns null when neither does.
     */
    private static function resolveTwoDigitYearDob(int $day, int $month, int $twoDigitYear): ?Carbon
    {
        foreach ([1900 + $twoDigitYear, 2000 + $twoDigitYear] as $year) {
            try {
                $candidate = Carbon::create($year, $month, $day, 0, 0, 0);
            } catch (\Throwable $e) {
                continue;
            }
            $age = $candidate->diffInYears(Carbon::now());
            if (! $candidate->isFuture() && $age >= self::MIN_AGE_YEARS && $age <= self::MAX_AGE_YEARS) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * "19 Feb 82" — resolve the two-digit year by age bounds, delegating the
     * month-name parse to Carbon on the expanded candidate string.
     */
    private static function resolveTwoDigitYearTextualDob(int $day, string $monthWord, int $twoDigitYear): ?Carbon
    {
        foreach ([1900 + $twoDigitYear, 2000 + $twoDigitYear] as $year) {
            try {
                $candidate = Carbon::parse("{$day} {$monthWord} {$year}");
            } catch (\Throwable $e) {
                continue;
            }
            $age = $candidate->diffInYears(Carbon::now());
            if (! $candidate->isFuture() && $age >= self::MIN_AGE_YEARS && $age <= self::MAX_AGE_YEARS) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Parse a retirement date. Accepts the same formats as DOB but doesn't
     * enforce the age bounds — the user may enter a past or future date.
     * Rejects relative references.
     */
    public static function parseRetirementDate(?string $input): ?string
    {
        $cleaned = self::cleanString($input);
        if ($cleaned === '') {
            return null;
        }

        $lowered = mb_strtolower($cleaned);
        foreach (['yesterday', 'today', 'tomorrow', 'now'] as $banned) {
            if (str_contains($lowered, $banned)) {
                return null;
            }
        }

        // "Year only" shortcut: "2020" → 2020-01-01
        if (preg_match('/^(19|20)\d{2}$/', $cleaned) === 1) {
            return $cleaned.'-01-01';
        }

        try {
            $date = Carbon::parse($cleaned);
        } catch (\Throwable $e) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Parse an annual income amount from free text.
     *
     * Accepts: "£75,000", "75k", "£75k", "75000", "£75,000.50".
     * Rejects: "seventy-five thousand" (we don't word-parse), non-numeric,
     * negative, or absurd (> £99,999,999) values.
     */
    public static function parseIncomeAmount(?string $input): ?float
    {
        return self::parseMoney($input, 0.0, 99_999_999.0);
    }

    /**
     * Parse a monthly expenditure amount. Uses a narrower ceiling than
     * income (nobody spends £10m/month).
     */
    public static function parseExpenditureAmount(?string $input): ?float
    {
        return self::parseMoney($input, 0.0, 999_999.0);
    }

    /**
     * Parse a free-text marital status answer into the enum value.
     *
     * Canonical values match the users.marital_status enum after the
     * 2026_04_15 civil_partnership migration: 'single', 'married',
     * 'civil_partnership', 'divorced', 'widowed'.
     */
    public static function parseMaritalFromText(?string $input): ?string
    {
        $cleaned = mb_strtolower(self::cleanString($input));
        if ($cleaned === '') {
            return null;
        }

        if (str_contains($cleaned, 'civil partnership') || str_contains($cleaned, 'civil partner')) {
            return 'civil_partnership';
        }
        if (str_starts_with($cleaned, 'married') || $cleaned === 'married') {
            return 'married';
        }
        if (str_starts_with($cleaned, 'single')) {
            return 'single';
        }
        if (str_starts_with($cleaned, 'divorced') || str_starts_with($cleaned, 'separated')) {
            return 'divorced';
        }
        if (str_starts_with($cleaned, 'widowed') || str_starts_with($cleaned, 'widow')) {
            return 'widowed';
        }

        return null;
    }

    /**
     * Parse a free-text employment status into the enum value.
     *
     * Canonical values match the users.employment_status enum:
     * 'employed', 'full_time', 'part_time', 'self_employed', 'retired',
     * 'unemployed', 'other'.
     */
    public static function parseEmploymentFromText(?string $input): ?string
    {
        $cleaned = mb_strtolower(self::cleanString($input));
        if ($cleaned === '') {
            return null;
        }

        if (str_contains($cleaned, 'self-employed') || str_contains($cleaned, 'self employed') || str_contains($cleaned, 'self_employed')) {
            return 'self_employed';
        }
        if (str_contains($cleaned, 'part-time') || str_contains($cleaned, 'part time') || str_contains($cleaned, 'part_time')) {
            return 'part_time';
        }
        if (str_contains($cleaned, 'full-time') || str_contains($cleaned, 'full time') || str_contains($cleaned, 'full_time')) {
            return 'full_time';
        }
        if (str_starts_with($cleaned, 'retired') || $cleaned === 'retired') {
            return 'retired';
        }
        if (str_starts_with($cleaned, 'unemployed') || str_starts_with($cleaned, 'not working') || str_starts_with($cleaned, 'out of work')) {
            return 'unemployed';
        }
        if (str_starts_with($cleaned, 'student')) {
            return 'other';
        }
        if (str_starts_with($cleaned, 'employed') || $cleaned === 'employed') {
            return 'employed';
        }
        if (str_starts_with($cleaned, 'other')) {
            return 'other';
        }

        return null;
    }

    /**
     * Attempt a shared money parser. Finds the first currency/numeric
     * pattern in the input so phrases like "About £2,800 per month" and
     * "roughly 75k" both work. Accepts bare numbers only when they have a
     * k/m suffix or are the ONLY content (prevents "age 42" matching as
     * £42 income).
     */
    private static function parseMoney(?string $input, float $minInclusive, float $maxInclusive): ?float
    {
        $cleaned = self::cleanString($input);
        if ($cleaned === '') {
            return null;
        }

        $lower = mb_strtolower($cleaned);
        $numeric = null;
        $multiplier = 1.0;

        // Pattern 1: explicit currency marker — most reliable
        if (preg_match('/£\s*(\d[\d,]*(?:\.\d+)?)\s*(k|m)?/i', $cleaned, $matches) === 1) {
            $numeric = str_replace(',', '', $matches[1]);
            $suffix = isset($matches[2]) ? mb_strtolower($matches[2]) : '';
            $multiplier = match ($suffix) {
                'k' => 1_000.0,
                'm' => 1_000_000.0,
                default => 1.0,
            };
        }
        // Pattern 2: numeric with k/m suffix — also reliable
        elseif (preg_match('/\b(\d+(?:\.\d+)?)\s*(k|m)\b/i', $lower, $matches) === 1) {
            $numeric = $matches[1];
            $multiplier = $matches[2] === 'k' ? 1_000.0 : 1_000_000.0;
        }
        // Pattern 3: pure numeric (input is just digits + optional decimal,
        // optional commas, nothing else). Prevents "age 42" from matching.
        else {
            $stripped = str_replace([',', ' '], '', $cleaned);
            if (preg_match('/^\d+(\.\d+)?$/', $stripped) === 1) {
                $numeric = $stripped;
            }
        }

        if ($numeric === null) {
            return null;
        }

        $value = (float) $numeric * $multiplier;

        if (! is_finite($value)) {
            return null;
        }

        if ($value < $minInclusive || $value > $maxInclusive) {
            return null;
        }

        return round($value, 2);
    }

    private static function cleanString(?string $input): string
    {
        if ($input === null) {
            return '';
        }

        return trim($input);
    }
}
