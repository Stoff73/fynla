/**
 * The caption beneath a Monte Carlo projection chart.
 *
 * **W-0258 — the caption and the figure beside it are not the same quantity.**
 *
 * The risk profile's `expected_return` is an ARITHMETIC mean. The line the chart
 * draws is the MEDIAN of a simulated distribution, which compounds at the geometric
 * mean — lower by roughly half the variance. On David's portfolio a stated 7.07%
 * expected return produced a median implying 5.36%–6.09% a year: a gap of
 * σ²/2 = 0.1688² / 2 = 1.42%.
 *
 * Both numbers are correct. **A user checking one against the other concludes the
 * projection is broken**, because nothing on the card says they measure different
 * things. That is a disclosure defect, not a calculation one — no figure moves.
 *
 * Two components built this string independently and identically
 * (`InvestmentProjectionChart`, `PensionPotProjectionChart`), which is why the
 * disclosure had to be added twice or not at all. It lives here once (Rule 20).
 *
 * CSJ's remaining options on W-0258, if this is not the preferred one: caption the
 * geometric rate instead, or state the divergence as accepted and drop the sentence.
 * Both are a one-line change here rather than an edit to two components.
 */

/** Explains why a median path compounds below the quoted arithmetic mean. */
export const VOLATILITY_DRAG_NOTE = 'The line shows the middle outcome, which grows a '
  + 'little slower than this rate because returns vary from year to year.';

/**
 * @param {object} options
 * @param {string} options.levelDisplay Risk level, already formatted for display.
 * @param {number|string} options.expectedReturn Arithmetic expected return, percent.
 * @param {number} [options.feeDragPercent] Charges to disclose, percent. Omit or 0 to hide.
 * @returns {string}
 */
export function projectionRiskMessage({ levelDisplay, expectedReturn, feeDragPercent = 0 }) {
  const formattedReturn = Number(expectedReturn).toFixed(2);
  const fees = Number(feeDragPercent) || 0;

  const basis = fees > 0
    ? `Using ${levelDisplay} risk profile (${formattedReturn}% expected return, less ${fees.toFixed(2)}% in charges)`
    : `Using ${levelDisplay} risk profile (${formattedReturn}% expected return)`;

  return `${basis}. ${VOLATILITY_DRAG_NOTE}`;
}
