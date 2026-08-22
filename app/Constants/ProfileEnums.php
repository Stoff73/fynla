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
 *   health_status    enum('yes','yes_previous','no_previous','no_existing','no_both') NULL DEFAULT 'yes'
 *   smoking_status   enum('never','quit_recent','quit_long_ago','yes') NOT NULL DEFAULT 'never'
 *   education_level  enum('secondary','a_level','undergraduate','postgraduate','professional','other') NULL
 *
 * Note `smoking_status` is NOT NULL. An unanswered select must drop the key
 * rather than send null — see UpdatePersonalInfoRequest::prepareForValidation().
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
     * The fields whose selects submit '' for "not answered". The global
     * ConvertEmptyStringsToNull middleware turns that into null before a request
     * is seen, which fails Rule::in on the nullable columns and is an outright
     * 500 on the NOT NULL one — so the key is dropped instead.
     *
     * @var list<string>
     */
    public const OPTIONAL_SELECT_FIELDS = [
        'health_status',
        'smoking_status',
        'education_level',
    ];
}
