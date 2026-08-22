/**
 * The one set of Health & Lifestyle select options for the desktop web app.
 *
 * Three selects were rendering this field with three different option lists:
 *
 * - `UserProfile/HealthInformation.vue` — the six the column holds.
 * - `Onboarding/steps/PersonalInfoStep.vue` — the same six.
 * - `UserProfile/PersonalInformation.vue` — offered `doctorate`, `foundation`
 *   and `hnd`, which `users.education_level` cannot hold (validation passed, the
 *   write died as a **500**), and omitted `secondary`, `a_level` and
 *   `professional`, which it can. Broken in both directions at once (W-0031).
 *
 * `values` here must stay in step with `App\Constants\ProfileEnums`, which is in
 * turn pinned to the live column definitions by
 * `tests/Unit/Database/ProfileEnumColumnsTest.php`. The cross-check that these
 * two lists agree lives in
 * `resources/js/components/__tests__/UserProfile/ProfileOptionsParity.spec.js`.
 *
 * Education labels are NOT authored here. They are mirrored from
 * `App\Constants\ProfileEnums::EDUCATION_LEVEL_LABELS`, which is their single home,
 * and the parity spec compares them string for string. A fourth renderer —
 * `ComprehensiveProtectionPlanService` — held its own copy and so kept showing
 * "Secondary (GCSE/O-Levels)" after both selects had been corrected; it now reads
 * the same constant (W-0080). Edit a label there, not here.
 */

export const HEALTH_STATUS_OPTIONS = [
  { value: 'yes', label: 'Yes' },
  { value: 'yes_previous', label: 'Yes, previous health conditions' },
  { value: 'no_previous', label: 'No, previous health conditions' },
  { value: 'no_existing', label: 'No, existing health conditions' },
  { value: 'no_both', label: 'No, previous and existing health conditions' },
];

export const SMOKING_STATUS_OPTIONS = [
  { value: 'never', label: 'Never smoked' },
  { value: 'quit_recent', label: 'No, gave up 12 months or sooner' },
  { value: 'quit_long_ago', label: 'No, gave up more than 12 months ago' },
  { value: 'yes', label: 'Yes' },
];

export const EDUCATION_LEVEL_OPTIONS = [
  { value: 'secondary', label: 'Secondary School' },
  { value: 'a_level', label: 'Advanced Level or Vocational' },
  { value: 'undergraduate', label: 'Undergraduate Degree' },
  { value: 'postgraduate', label: 'Postgraduate Degree' },
  { value: 'professional', label: 'Professional Qualification' },
  { value: 'other', label: 'Other' },
];

/**
 * Display helpers. `HealthInformation.vue` kept three private maps that had to be
 * edited alongside its own option lists; they read from the lists above instead,
 * so a value can never render as "Not specified" merely because a map was missed.
 */
const labelFrom = (options, value) => options.find((o) => o.value === value)?.label ?? 'Not specified';

export const formatHealthStatus = (value) => (value === 'yes' ? 'Yes, good health' : labelFrom(HEALTH_STATUS_OPTIONS, value));
export const formatSmokingStatus = (value) => labelFrom(SMOKING_STATUS_OPTIONS, value);
export const formatEducationLevel = (value) => labelFrom(EDUCATION_LEVEL_OPTIONS, value);
