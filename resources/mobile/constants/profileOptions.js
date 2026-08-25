/**
 * Health & Lifestyle select options for the `/m` bundle.
 *
 * `/m` is an isolated Vite bundle — `vite.mobile.config.js` aliases only `@m`,
 * and root `CLAUDE.md` is explicit that `resources/mobile/` inherits nothing from
 * `resources/js/`. So this list cannot import the desktop one, and a second copy
 * is what the architecture requires rather than a Rule 20 violation.
 *
 * What stops it drifting is a test, not discipline:
 * `resources/mobile/__tests__/profileOptionsParity.spec.js` asserts these lists
 * are identical to `resources/js/constants/profileOptions.js` AND to
 * `App\Constants\ProfileEnums`, which is itself pinned to the live column
 * definitions. Change one, the others go red.
 *
 * Labels are deliberately identical to the desktop ones. Divergent wording for
 * the same field across surfaces is exactly what Rule 19 exists to prevent.
 *
 * The education labels have a single home —
 * `App\Constants\ProfileEnums::EDUCATION_LEVEL_LABELS` — which the parity spec now
 * compares against string for string, not just value for value. Edit a label there,
 * not here (W-0080).
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

const labelFrom = (options, value) => options.find((o) => o.value === value)?.label ?? 'Not recorded';

export const formatHealthStatus = (value) => (value === 'yes' ? 'Yes, good health' : labelFrom(HEALTH_STATUS_OPTIONS, value));
export const formatSmokingStatus = (value) => labelFrom(SMOKING_STATUS_OPTIONS, value);
export const formatEducationLevel = (value) => labelFrom(EDUCATION_LEVEL_OPTIONS, value);
