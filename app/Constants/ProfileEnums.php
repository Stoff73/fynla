<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * The canonical values for the three Health & Lifestyle enum columns on `users`,
 * and the display labels for `education_level`.
 *
 * These lists exist because the request rules and the columns had drifted apart
 * twice over, in both directions — and, once those were pinned, because the
 * user-facing copy then drifted the same way (W-0080, see EDUCATION_LEVEL_LABELS):
 *
 * - W-0006: `UpdatePersonalInfoRequest` validated `good_health` / `smoker`, two
 *   columns that do not exist, and never mentioned the real ones — so every
 *   submitted health and smoking value was stripped by validated() in silence.
 * - W-0031: the same rule then allowed `doctorate`, `foundation` and `hnd` for
 *   `education_level`, which the column enum cannot hold. Validation passed and
 *   the write died as a QueryException — a 500, not a 422 — and it was reachable
 *   from a live select on the Personal Information page.
 *
 * Every backend consumer composes from here, and
 * `tests/Unit/Constants/ProfileEnumsMatchColumnsTest.php` asserts these lists
 * are byte-identical to the live column definitions. That test is the thing that
 * stops the two drifting again: change a column without changing this file (or
 * the reverse) and it goes red.
 *
 * The `users` column definitions these mirror:
 *   health_status    enum('yes','yes_previous','no_previous','no_existing','no_both') NULL
 *   smoking_status   enum('never','quit_recent','quit_long_ago','yes') NULL
 *   education_level  enum('secondary','a_level','undergraduate','postgraduate','professional','other') NULL
 *
 * No defaults: null is "not answered" (item 8a, CSJ 2026-10-06). The old
 * defaults ('never', 'yes') read every unasked user as a non-smoker in good
 * health. These two columns are the ONE home for smoking and health: the
 * protection profile's own `smoker_status` / `health_status` had no input on
 * any surface and were dropped.
 */
final class ProfileEnums
{
    /** @var list<string> */
    public const HEALTH_STATUSES = [
        'yes',
        'yes_previous',
        'no_previous',
        'no_existing',
        'no_both',
    ];

    /** @var list<string> */
    public const SMOKING_STATUSES = [
        'never',
        'quit_recent',
        'quit_long_ago',
        'yes',
    ];

    /** @var list<string> */
    public const EDUCATION_LEVELS = [
        'secondary',
        'a_level',
        'undergraduate',
        'postgraduate',
        'professional',
        'other',
    ];

    /**
     * The display label for each education level — the single home for this copy.
     *
     * It lives here, and not beside the select that renders it, because there are
     * four renderers: the desktop constants, the `/m` constants, and
     * `ComprehensiveProtectionPlanService`, which held its own `match` and so kept
     * showing "Secondary (GCSE/O-Levels)" — an acronym Rule 9 forbids — after the
     * two selects had been corrected. Nothing bound it to them.
     *
     * The two frontend copies exist because `/m` is an isolated Vite bundle and
     * cannot import from `resources/js/`; they are pinned to this list, labels
     * included, by `ProfileOptionsParity.spec.js` and `profileOptionsParity.spec.js`.
     * Change a label here and both go red until they follow.
     *
     * Keys are ordered to match EDUCATION_LEVELS, which the selects render in order.
     *
     * @var array<string, string>
     */
    public const EDUCATION_LEVEL_LABELS = [
        'secondary' => 'Secondary School',
        'a_level' => 'Advanced Level or Vocational',
        'undergraduate' => 'Undergraduate Degree',
        'postgraduate' => 'Postgraduate Degree',
        'professional' => 'Professional Qualification',
        'other' => 'Other',
    ];

    /**
     * Display labels, the same words the web and /m selects show
     * (`profileOptions.js` formatHealthStatus / formatSmokingStatus).
     *
     * @var array<string, string>
     */
    public const HEALTH_STATUS_LABELS = [
        'yes' => 'Yes, good health',
        'yes_previous' => 'Yes, previous health conditions',
        'no_previous' => 'No, previous health conditions',
        'no_existing' => 'No, existing health conditions',
        'no_both' => 'No, previous and existing health conditions',
    ];

    /** @var array<string, string> */
    public const SMOKING_STATUS_LABELS = [
        'never' => 'Never smoked',
        'quit_recent' => 'No, gave up 12 months or sooner',
        'quit_long_ago' => 'No, gave up more than 12 months ago',
        'yes' => 'Yes',
    ];

    /**
     * Whether the person counts as a smoker, or null when not answered.
     *
     * Insurers count anyone who has smoked, vaped or used nicotine replacement
     * "at all in the last 12 months" as a smoker (Legal & General,
     * https://www.legalandgeneral.com/insurance/life-insurance/health/life-insurance-for-smokers/),
     * which is the line the select's two "gave up" answers are drawn on.
     */
    public static function isSmoker(?string $smokingStatus): ?bool
    {
        return $smokingStatus === null ? null : in_array($smokingStatus, ['yes', 'quit_recent'], true);
    }

    /**
     * Whether the person has a health condition, now or in the past, or null
     * when not answered. Annuity providers price on health conditions,
     * "lifestyle—such as smoking or drinking—and your medical history"
     * (Legal & General, https://www.legalandgeneral.com/retirement/pension-annuity/guides/enhanced-annuities/),
     * so every answer other than good health with none in the past counts.
     */
    public static function hasHealthHistory(?string $healthStatus): ?bool
    {
        return $healthStatus === null ? null : $healthStatus !== 'yes';
    }

    /**
     * The fields whose selects submit '' for "not answered". The global
     * ConvertEmptyStringsToNull middleware turns that into null before a request
     * is seen; an unanswered select means "leave it alone", so the key is
     * dropped rather than clearing an answer already given.
     *
     * @var list<string>
     */
    public const OPTIONAL_SELECT_FIELDS = [
        'health_status',
        'smoking_status',
        'education_level',
    ];
}
