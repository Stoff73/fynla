/**
 * A savings account's interest rate as the user sees it. The rate is stored
 * as a percentage (4.55 = 4.55%). A rate the user never gave is null and reads
 * "Not recorded", never "0.00%" (regression walk 2026-10-09, R10: an optional
 * Cash ISA rate left blank was stored as 0 and shown as 0.00%).
 *
 * @param {number|string|null|undefined} rate
 * @returns {string}
 */
export function formatInterestRate(rate) {
  if (rate === null || rate === undefined || rate === '' || Number.isNaN(Number(rate))) {
    return 'Not recorded';
  }
  return `${Number(rate).toFixed(2)}%`;
}

export function hasInterestRate(rate) {
  return formatInterestRate(rate) !== 'Not recorded';
}
