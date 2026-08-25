/**
 * The one home for "do these holdings add up to something an account can hold?"
 *
 * W-0257: an account whose holdings exceeded 100% could not be saved and nothing
 * said why. Three components ask this question — InlineHoldingsEditor renders the
 * state, AccountForm and DCPensionForm block the save on it — so it is answered
 * here once and imported, rather than reimplemented three times and drifting
 * (Rule 20).
 */

/** An account's holdings may account for at most all of it. */
export const ALLOCATION_TOTAL = 100;

/**
 * Floating-point slack.
 *
 * A perfectly valid set of allocations does not necessarily sum to exactly 100 in
 * binary: 68.18 + 31.76 + 0.06 evaluates to 100.00000000000001, and it is one of
 * many such sets at two decimal places. Comparing that against 100 with `>` would
 * refuse to save an account that is completely correct — the same disease as the
 * bug this fixes, a wrong answer delivered politely.
 *
 * 0.01 is far above the ~1e-14 that binary addition introduces and far below any
 * mistake a user could make.
 */
export const ALLOCATION_TOLERANCE = 0.01;

/**
 * Sum the allocation percentages, treating blank and unparseable entries as zero.
 *
 * @param {Array<{allocation_percent?: number|string|null}>} holdings
 * @returns {number}
 */
export function allocationTotal(holdings) {
  if (!Array.isArray(holdings)) return 0;

  return holdings.reduce((sum, holding) => {
    const value = parseFloat(holding?.allocation_percent);
    return sum + (Number.isFinite(value) ? value : 0);
  }, 0);
}

/**
 * How far past 100% the holdings reach, or 0 when they are within tolerance.
 *
 * Deliberately NOT `Math.max(0, 100 - total)`. That is the shape the editor's
 * "Cash (auto-allocated)" row already uses, and clamping at zero is exactly what
 * made this defect invisible: over-allocation and perfect allocation both
 * rendered as "nothing left over". The excess is the quantity the clamp discards,
 * so it is measured here where it is still visible.
 *
 * @param {Array} holdings
 * @returns {number}
 */
export function allocationExcess(holdings) {
  const over = allocationTotal(holdings) - ALLOCATION_TOTAL;
  return over > ALLOCATION_TOLERANCE ? over : 0;
}

/** @param {Array} holdings @returns {boolean} */
export function isOverAllocated(holdings) {
  return allocationExcess(holdings) > 0;
}

/**
 * The message shown to the user, or null when there is nothing wrong.
 *
 * States the total, the target and the difference, because "invalid" on its own
 * does not tell anyone which number to change or by how much.
 *
 * @param {Array} holdings
 * @returns {string|null}
 */
export function allocationErrorMessage(holdings) {
  const excess = allocationExcess(holdings);
  if (excess === 0) return null;

  const total = allocationTotal(holdings).toFixed(1).replace(/\.0$/, '');
  const over = excess.toFixed(1).replace(/\.0$/, '');

  return `These holdings add up to ${total}% of the account. Reduce them by ${over}% so they total 100% or less.`;
}
