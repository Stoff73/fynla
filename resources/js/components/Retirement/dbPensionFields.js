/**
 * The one definition of the Defined Benefit pension field set.
 *
 * Two forms capture the same `db_pensions` record — DCPensionForm's unified
 * "Add Pension" flow and DBPensionForm's edit/onboarding flow — and they had
 * drifted apart, each carrying its own copy of the options and its own mapper.
 * Both now read the enums and the payload shape from here, so a change lands in
 * one place for every entry point (W-0017).
 *
 * Labels are user-facing: acronyms are spelled out (CLAUDE.md Rule 9).
 */

/**
 * `db_pensions.scheme_status` — nullable, one of 'active' | 'deferred' | 'in_payment'
 * (migration 2026_08_21_180000, W-0032). Both forms asked this question for months
 * and threw the answer away, because no column existed to hold it.
 *
 * It decides whether the pension counts as income today: `DBPension::isInPayment()`
 * prefers a stated status over comparing the user's age with the scheme's Normal
 * Retirement Age, which is wrong in both directions for someone drawing early or
 * deferring past the scheme age.
 *
 * Values are the stored ones, not the labels — the labels are what the user reads.
 */
export const DB_SCHEME_STATUS_OPTIONS = [
  { value: 'active', label: 'Active — still building up benefits' },
  { value: 'deferred', label: 'Deferred — left the scheme, not yet drawing' },
  { value: 'in_payment', label: 'In Payment — being paid now' },
];

/**
 * Render a stored `scheme_status` for display. Unset reads as unknown rather than
 * being guessed at as "Active", which is what the detail view used to show for
 * every Defined Benefit pension ever saved.
 *
 * @param {string|null|undefined} value
 * @returns {string}
 */
export function formatSchemeStatus(value) {
  const labels = {
    active: 'Active',
    deferred: 'Deferred',
    in_payment: 'In Payment',
  };

  return labels[value] || 'Not recorded';
}

/** `db_pensions.scheme_type` — enum('final_salary','career_average','public_sector'). */
export const DB_SCHEME_TYPE_OPTIONS = [
  { value: 'final_salary', label: 'Final Salary' },
  { value: 'career_average', label: 'Career Average' },
  { value: 'public_sector', label: 'Public Sector' },
];

/**
 * `db_pensions.inflation_protection` — enum('cpi','rpi','fixed','none'), NOT NULL
 * default 'none'. This is what PensionProjector::getRevaluationRate() branches on
 * when it revalues the accrued pension to the scheme retirement age; the numeric
 * revaluation rate is only read for 'fixed'.
 */
export const DB_INFLATION_PROTECTION_OPTIONS = [
  { value: 'cpi', label: 'Consumer Prices Index' },
  { value: 'rpi', label: 'Retail Prices Index' },
  { value: 'fixed', label: 'Fixed rate' },
  { value: 'none', label: 'No inflation protection' },
];

/**
 * Map a form's Defined Benefit fields onto the `db_pensions` API payload.
 *
 * Accepts the canonical field names; each form normalises its own local names
 * (DCPensionForm prefixes them `db_`) before calling this.
 *
 * @param {object} fields
 * @param {string} fields.schemeName        Employer / scheme name
 * @param {string} fields.schemeType        One of DB_SCHEME_TYPE_OPTIONS values
 * @param {string} fields.schemeStatus      One of DB_SCHEME_STATUS_OPTIONS values; '' when
 *                                          the user has not answered, which persists as null
 * @param {number} fields.annualIncome      Projected annual pension at the scheme retirement age
 * @param {number} fields.serviceYears      Pensionable service years
 * @param {number} fields.pensionableSalary
 * @param {number} fields.normalRetirementAge
 * @param {number} fields.spousePensionPercent
 * @param {string} fields.inflationProtection One of DB_INFLATION_PROTECTION_OPTIONS values
 * @param {number} fields.revaluationRate   Fixed rate, % a year — only meaningful when
 *                                          inflationProtection === 'fixed'
 * @param {number} fields.lumpSum           Pension Commencement Lump Sum entitlement
 */
export function buildDbPensionPayload(fields) {
  return {
    scheme_name: fields.schemeName,
    scheme_type: fields.schemeType || 'final_salary',
    scheme_status: fields.schemeStatus || null,
    accrued_annual_pension: fields.annualIncome,
    pensionable_service_years: fields.serviceYears,
    pensionable_salary: fields.pensionableSalary ?? null,
    normal_retirement_age: fields.normalRetirementAge || null,
    spouse_pension_percent: fields.spousePensionPercent ?? null,
    inflation_protection: fields.inflationProtection || 'none',
    // Only carries a value for the fixed-rate option; PensionProjector parses the
    // percentage back out of this string.
    revaluation_method: fields.inflationProtection === 'fixed' && fields.revaluationRate
      ? `${fields.revaluationRate}%`
      : null,
    lump_sum_entitlement: fields.lumpSum ?? null,
  };
}
