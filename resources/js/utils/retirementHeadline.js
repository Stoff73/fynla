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
