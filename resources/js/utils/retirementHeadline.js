/**
 * What the retirement dashboard card leads with — the ONE home for that rule.
 *
 * The web dashboard and the `/m` dashboard each built this figure themselves,
 * from different fallback chains: web fell back to `income_gap` then a bare
 * `value`, `/m` fell back to the net worth pension total first. Two answers to
 * one question, so the same household could read differently on the two
 * surfaces (W-0238). Both now call this, per the `ownership.js` precedent for a
 * shared rule (`/m` imports it by relative path).
 *
 * **A pot and an income are different kinds of number and the card has to say
 * which it is showing.** A defined-benefit-only household has no balance to
 * display; showing them £0 and "Plan your retirement" beside a retirement page
 * reading "Guaranteed Retirement Income £35,000/year" is what this fixes. Where
 * there is no pot but there is secured income, the card shows the income and
 * labels it per year — it never prints an annual income as though it were a
 * balance.
 *
 * @param {object} retirement - the `modules.retirement` block of
 *   `GET /api/v1/mobile/dashboard`.
 * @returns {{value: number, isAnnualIncome: boolean, caption: string}}
 */
export function retirementHeadline(retirement) {
  const module = retirement || {};

  const toNumber = (v) => {
    const n = typeof v === 'number' ? v : parseFloat(v);
    return Number.isFinite(n) ? n : 0;
  };

  const pot = toNumber(module.pot_value);
  const guaranteedIncome = toNumber(module.guaranteed_income);
  const target = toNumber(module.target_income);

  const isAnnualIncome = pot <= 0 && guaranteedIncome > 0;
  const value = isAnnualIncome ? guaranteedIncome : pot;

  let caption;
  if (isAnnualIncome) {
    caption = 'Guaranteed retirement income';
  } else if (target > 0) {
    caption = 'Towards your target';
  } else if (value > 0) {
    caption = 'Your pension pot';
  } else {
    caption = 'Plan your retirement';
  }

  return { value, isAnnualIncome, caption };
}

/**
 * What the retirement MODULE PAGE's hero leads with — the same distinction as
 * the dashboard card above, applied to the module page's inputs.
 *
 * The web module page (`components/NetWorth/PensionList.vue`) already swaps its
 * hero for a "Guaranteed Retirement Income" panel when the household has no
 * defined contribution pot. `/m` did not: its hero preferred
 * `planning_projection.planning_total_at_target_age`, which models pots only and
 * so returns a literal 0 for a household whose whole provision is a final salary
 * scheme. The page therefore read "Projected retirement income £0 a year" to a
 * user holding an NHS scheme paying £35,000 (W-0244). **A projection of a pot
 * that does not exist is not a projection of zero income.**
 *
 * `guaranteedIncome` must come from the backend's `guaranteed_annual_income`,
 * computed once in `RetirementAgent`, never re-derived on a surface.
 *
 * @param {object} input
 * @param {number|null} input.potValue - current defined contribution pot.
 * @param {number|null} input.guaranteedIncome - annual income already secured by
 *   defined benefit schemes and the State Pension.
 * @param {number|null} input.projectedIncome - projected annual income, or null
 *   when nothing could be projected.
 * @returns {{value: number|null, isGuaranteed: boolean, label: string}}
 */
export function retirementIncomeHeadline({ potValue, guaranteedIncome, projectedIncome } = {}) {
  const toNumber = (v) => {
    const n = typeof v === 'number' ? v : parseFloat(v);
    return Number.isFinite(n) ? n : 0;
  };

  const pot = toNumber(potValue);
  const guaranteed = toNumber(guaranteedIncome);

  if (pot <= 0 && guaranteed > 0) {
    return {
      value: guaranteed,
      isGuaranteed: true,
      label: 'Guaranteed retirement income',
    };
  }

  const projected = projectedIncome == null ? null : toNumber(projectedIncome);

  return {
    value: projected,
    isGuaranteed: false,
    label: 'Projected retirement income',
  };
}
