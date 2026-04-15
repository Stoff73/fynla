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
     * Accepted examples: "12 January 1985", "12/01/1985", "1985-01-12",
     * "12 Jan 1980". Rejects relative references ("yesterday") and dates
     * that would make the user younger than 18 or older than 105.
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

        try {
            $date = Carbon::parse($cleaned);
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
     * Attempt a shared money parser. Strips £/$ and commas, handles "k"
     * suffix (e.g. "75k" → 75000), enforces finite bounds.
     */
    private static function parseMoney(?string $input, float $minInclusive, float $maxInclusive): ?float
    {
        $cleaned = self::cleanString($input);
        if ($cleaned === '') {
            return null;
        }

        // Strip currency symbols and thousands separators
        $cleaned = preg_replace('/[£\$,]/', '', $cleaned) ?? '';
        $cleaned = preg_replace('/\s+/', '', $cleaned) ?? '';
        if ($cleaned === '') {
            return null;
        }

        $lower = mb_strtolower($cleaned);
        $multiplier = 1.0;

        // Suffix: "k" = thousand, "m" = million
        if (preg_match('/^(\d+(?:\.\d+)?)k$/', $lower, $matches) === 1) {
            $multiplier = 1_000.0;
            $numeric = $matches[1];
        } elseif (preg_match('/^(\d+(?:\.\d+)?)m$/', $lower, $matches) === 1) {
            $multiplier = 1_000_000.0;
            $numeric = $matches[1];
        } else {
            // Plain numeric — must match a simple number pattern
            if (preg_match('/^\d+(\.\d+)?$/', $cleaned) !== 1) {
                return null;
            }
            $numeric = $cleaned;
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
