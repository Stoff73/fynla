/**
 * The one home for rendering a holding's unit count.
 *
 * Two tables display it — `Investment/HoldingsTable.vue` and the Holdings tab of
 * `NetWorth/PensionDetailInline.vue` — and neither displayed it before W-0442,
 * so this exists before a second copy of it can (Rule 20).
 *
 * `currencyMixin.formatNumber` is deliberately NOT used: it returns the string
 * `'0'` for a null, and "no unit count recorded" and "zero units held" are not
 * the same fact. That is the `?? null` versus `|| 0` distinction in
 * `app/Http/CLAUDE.md` — a zero-default collapses an absence into a figure, and
 * the reader cannot tell them apart.
 */

/**
 * Format a stored unit count for display.
 *
 * `holdings.quantity` is `decimal(20,6)`, so a whole number arrives as
 * `4211.000000`. Trailing zeros are noise and go; a genuine fraction is a real
 * holding and stays, to all six places the column keeps.
 *
 * @param {number|string|null|undefined} quantity
 * @returns {string} the formatted count, or an em dash when none is recorded
 */
export function formatUnits(quantity) {
  if (quantity === null || quantity === undefined || quantity === '') return '—';

  const units = parseFloat(quantity);
  if (!Number.isFinite(units)) return '—';

  return units.toLocaleString('en-GB', { maximumFractionDigits: 6 });
}
